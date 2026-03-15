<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Resilience;

use AgenticOrchestrator\Exceptions\ProviderException;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Provider Fallback Chain - Manages LLM provider fallbacks.
 *
 * Automatically switches to backup providers when the primary fails.
 */
class ProviderFallbackChain
{
    /**
     * Provider configurations in order of preference.
     *
     * @var array<array{provider: string, model: string, config: array<string, mixed>}>
     */
    protected array $providers = [];

    /**
     * Circuit breaker instances per provider.
     *
     * @var array<string, CircuitBreaker>
     */
    protected array $circuitBreakers = [];

    /**
     * Whether to use circuit breakers.
     */
    protected bool $useCircuitBreaker = true;

    /**
     * Circuit breaker configuration.
     *
     * @var array<string, mixed>
     */
    protected array $circuitBreakerConfig = [];

    /**
     * Retry strategy for each provider.
     */
    protected ?RetryStrategy $retryStrategy = null;

    /**
     * Callback when a provider fails.
     */
    protected ?Closure $onFailoverCallback = null;

    /**
     * Create a new provider fallback chain.
     *
     * @param  array<array{provider: string, model: string, config?: array<string, mixed>}>  $providers
     */
    public function __construct(array $providers = [])
    {
        foreach ($providers as $provider) {
            $this->addProvider(
                $provider['provider'],
                $provider['model'],
                $provider['config'] ?? []
            );
        }
    }

    /**
     * Create from configuration.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): static
    {
        $chain = new static;

        if (isset($config['providers'])) {
            foreach ($config['providers'] as $provider) {
                $chain->addProvider(
                    $provider['provider'],
                    $provider['model'],
                    $provider['config'] ?? []
                );
            }
        }

        if (isset($config['circuit_breaker'])) {
            $chain->withCircuitBreaker($config['circuit_breaker']);
        }

        if (isset($config['retry'])) {
            $chain->withRetry(new RetryStrategy($config['retry']));
        }

        return $chain;
    }

    /**
     * Add a provider to the chain.
     *
     * @param  array<string, mixed>  $config
     */
    public function addProvider(string $provider, string $model, array $config = []): static
    {
        $this->providers[] = [
            'provider' => $provider,
            'model' => $model,
            'config' => $config,
        ];

        return $this;
    }

    /**
     * Enable circuit breaker with configuration.
     *
     * @param  array<string, mixed>|bool  $config
     */
    public function withCircuitBreaker(array|bool $config = true): static
    {
        if (is_bool($config)) {
            $this->useCircuitBreaker = $config;
        } else {
            $this->useCircuitBreaker = true;
            $this->circuitBreakerConfig = $config;
        }

        return $this;
    }

    /**
     * Disable circuit breaker.
     */
    public function withoutCircuitBreaker(): static
    {
        $this->useCircuitBreaker = false;

        return $this;
    }

    /**
     * Set retry strategy.
     */
    public function withRetry(RetryStrategy $strategy): static
    {
        $this->retryStrategy = $strategy;

        return $this;
    }

    /**
     * Set callback when failover occurs.
     */
    public function onFailover(Closure $callback): static
    {
        $this->onFailoverCallback = $callback;

        return $this;
    }

    /**
     * Execute a callback with the fallback chain.
     *
     * @template T
     *
     * @param  Closure(string $provider, string $model, array $config): T  $callback
     * @return T
     *
     * @throws Throwable
     */
    public function execute(Closure $callback): mixed
    {
        if (empty($this->providers)) {
            throw new \RuntimeException('No providers configured in fallback chain');
        }

        $lastException = null;

        foreach ($this->providers as $index => $providerConfig) {
            $provider = $providerConfig['provider'];
            $model = $providerConfig['model'];
            $config = $providerConfig['config'];

            // Check circuit breaker
            if ($this->useCircuitBreaker) {
                $circuitBreaker = $this->getCircuitBreaker($provider);

                if ($circuitBreaker->isOpen()) {
                    Log::debug('Skipping provider due to open circuit', [
                        'provider' => $provider,
                        'model' => $model,
                    ]);

                    continue;
                }
            }

            try {
                $result = $this->executeWithProvider(
                    $callback,
                    $provider,
                    $model,
                    $config
                );

                // Record success with circuit breaker
                if ($this->useCircuitBreaker) {
                    // Success is recorded during execute
                }

                return $result;
            } catch (Throwable $e) {
                $lastException = $e;

                Log::warning('Provider failed', [
                    'provider' => $provider,
                    'model' => $model,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                // Notify failover
                if ($index < count($this->providers) - 1) {
                    $nextProvider = $this->providers[$index + 1];
                    $this->notifyFailover(
                        $provider,
                        $model,
                        $nextProvider['provider'],
                        $nextProvider['model'],
                        $e
                    );
                }
            }
        }

        throw $lastException ?? new ProviderException(
            'all',
            'All providers in fallback chain failed'
        );
    }

    /**
     * Execute callback with a specific provider.
     *
     * @template T
     *
     * @param  Closure(string $provider, string $model, array $config): T  $callback
     * @param  array<string, mixed>  $config
     * @return T
     */
    protected function executeWithProvider(
        Closure $callback,
        string $provider,
        string $model,
        array $config,
    ): mixed {
        $operation = fn () => $callback($provider, $model, $config);

        // Apply retry strategy if configured
        if ($this->retryStrategy !== null) {
            $operation = fn () => $this->retryStrategy->execute(
                fn () => $callback($provider, $model, $config)
            );
        }

        // Apply circuit breaker if enabled
        if ($this->useCircuitBreaker) {
            $circuitBreaker = $this->getCircuitBreaker($provider);

            return $circuitBreaker->execute($operation);
        }

        return $operation();
    }

    /**
     * Get or create circuit breaker for a provider.
     */
    protected function getCircuitBreaker(string $provider): CircuitBreaker
    {
        if (! isset($this->circuitBreakers[$provider])) {
            $this->circuitBreakers[$provider] = CircuitBreaker::for("provider:{$provider}")
                ->configure($this->circuitBreakerConfig);
        }

        return $this->circuitBreakers[$provider];
    }

    /**
     * Notify about a failover event.
     */
    protected function notifyFailover(
        string $fromProvider,
        string $fromModel,
        string $toProvider,
        string $toModel,
        Throwable $exception,
    ): void {
        Log::info('Provider failover', [
            'from' => ['provider' => $fromProvider, 'model' => $fromModel],
            'to' => ['provider' => $toProvider, 'model' => $toModel],
            'reason' => $exception->getMessage(),
        ]);

        if ($this->onFailoverCallback !== null) {
            ($this->onFailoverCallback)($fromProvider, $fromModel, $toProvider, $toModel, $exception);
        }
    }

    /**
     * Get the first available provider (checking circuit breakers).
     *
     * @return array{provider: string, model: string, config: array<string, mixed>}|null
     */
    public function getFirstAvailable(): ?array
    {
        foreach ($this->providers as $providerConfig) {
            if ($this->useCircuitBreaker) {
                $circuitBreaker = $this->getCircuitBreaker($providerConfig['provider']);

                if ($circuitBreaker->isOpen()) {
                    continue;
                }
            }

            return $providerConfig;
        }

        return null;
    }

    /**
     * Get all providers in the chain.
     *
     * @return array<array{provider: string, model: string, config: array<string, mixed>}>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Get provider health status.
     *
     * @return array<string, array{provider: string, model: string, healthy: bool, state: string}>
     */
    public function getHealthStatus(): array
    {
        $status = [];

        foreach ($this->providers as $index => $providerConfig) {
            $provider = $providerConfig['provider'];
            $state = 'unknown';

            if ($this->useCircuitBreaker && isset($this->circuitBreakers[$provider])) {
                $state = $this->circuitBreakers[$provider]->getState();
            }

            $status["provider_{$index}"] = [
                'provider' => $provider,
                'model' => $providerConfig['model'],
                'healthy' => $state !== CircuitBreaker::STATE_OPEN,
                'state' => $state,
            ];
        }

        return $status;
    }

    /**
     * Reset all circuit breakers.
     */
    public function reset(): void
    {
        foreach ($this->circuitBreakers as $circuitBreaker) {
            $circuitBreaker->reset();
        }
    }
}
