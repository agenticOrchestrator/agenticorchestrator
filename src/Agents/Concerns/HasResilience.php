<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Agents\Concerns;

use AgenticOrchestrator\Resilience\CircuitBreaker;
use AgenticOrchestrator\Resilience\ProviderFallbackChain;
use AgenticOrchestrator\Resilience\RetryStrategy;
use Closure;

/**
 * HasResilience - Adds retry, circuit breaker, and fallback support to agents.
 */
trait HasResilience
{
    /**
     * Retry strategy for this agent.
     */
    protected ?RetryStrategy $retryStrategy = null;

    /**
     * Circuit breaker instance.
     */
    protected ?CircuitBreaker $circuitBreaker = null;

    /**
     * Provider fallback chain.
     */
    protected ?ProviderFallbackChain $fallbackChain = null;

    /**
     * Default retry configuration.
     *
     * @var array<string, mixed>
     */
    protected array $retryConfig = [
        'max_attempts' => 3,
        'backoff' => RetryStrategy::BACKOFF_EXPONENTIAL,
        'base_delay_ms' => 1000,
        'max_delay_ms' => 30000,
        'jitter' => 0.1,
    ];

    /**
     * Default circuit breaker configuration.
     *
     * @var array<string, mixed>
     */
    protected array $circuitBreakerConfig = [
        'failure_threshold' => 5,
        'recovery_timeout' => 30,
        'success_threshold' => 2,
        'failure_window' => 60,
    ];

    /**
     * Configure retry strategy.
     *
     * @param  array<string, mixed>|RetryStrategy  $config
     */
    public function withRetry(array|RetryStrategy $config): static
    {
        if ($config instanceof RetryStrategy) {
            $this->retryStrategy = $config;
        } else {
            $this->retryStrategy = new RetryStrategy(array_merge($this->retryConfig, $config));
        }

        return $this;
    }

    /**
     * Disable retry.
     */
    public function withoutRetry(): static
    {
        $this->retryStrategy = RetryStrategy::none();

        return $this;
    }

    /**
     * Configure circuit breaker.
     *
     * @param  array<string, mixed>|CircuitBreaker  $config
     */
    public function withCircuitBreaker(array|CircuitBreaker $config): static
    {
        if ($config instanceof CircuitBreaker) {
            $this->circuitBreaker = $config;
        } else {
            $this->circuitBreaker = CircuitBreaker::for($this->getName())
                ->configure(array_merge($this->circuitBreakerConfig, $config));
        }

        return $this;
    }

    /**
     * Disable circuit breaker.
     */
    public function withoutCircuitBreaker(): static
    {
        $this->circuitBreaker = null;

        return $this;
    }

    /**
     * Configure provider fallback chain.
     *
     * @param  array<array{provider: string, model: string, config?: array<string, mixed>}>|ProviderFallbackChain  $chain
     */
    public function withFallbacks(array|ProviderFallbackChain $chain): static
    {
        if ($chain instanceof ProviderFallbackChain) {
            $this->fallbackChain = $chain;
        } else {
            $this->fallbackChain = new ProviderFallbackChain($chain);
        }

        return $this;
    }

    /**
     * Get the retry strategy.
     */
    public function getRetryStrategy(): RetryStrategy
    {
        if ($this->retryStrategy === null) {
            $this->retryStrategy = new RetryStrategy($this->retryConfig);
        }

        return $this->retryStrategy;
    }

    /**
     * Get the circuit breaker.
     */
    public function getCircuitBreaker(): ?CircuitBreaker
    {
        return $this->circuitBreaker;
    }

    /**
     * Get the fallback chain.
     */
    public function getFallbackChain(): ?ProviderFallbackChain
    {
        return $this->fallbackChain;
    }

    /**
     * Execute with resilience (retry + circuit breaker).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function executeWithResilience(Closure $callback): mixed
    {
        $operation = $callback;

        // Wrap with retry
        if ($this->retryStrategy !== null) {
            $originalOperation = $operation;
            $operation = fn () => $this->retryStrategy->execute($originalOperation);
        }

        // Wrap with circuit breaker
        if ($this->circuitBreaker !== null) {
            $originalOperation = $operation;
            $operation = fn () => $this->circuitBreaker->execute($originalOperation);
        }

        return $operation();
    }

    /**
     * Execute provider call with fallback chain.
     *
     * @template T
     *
     * @param  Closure(string $provider, string $model, array $config): T  $callback
     * @return T
     */
    protected function executeWithFallback(Closure $callback): mixed
    {
        if ($this->fallbackChain === null) {
            // No fallback chain, just execute with primary provider
            $provider = $this->getProvider();
            $model = $this->getModel();
            $config = $this->getProviderConfig();

            return $this->executeWithResilience(
                fn () => $callback($provider, $model, $config)
            );
        }

        return $this->fallbackChain->execute(
            fn ($provider, $model, $config) => $this->executeWithResilience(
                fn () => $callback($provider, $model, $config)
            )
        );
    }

    /**
     * Check if the agent is healthy (circuit breaker is not open).
     */
    public function isHealthy(): bool
    {
        if ($this->circuitBreaker === null) {
            return true;
        }

        return ! $this->circuitBreaker->isOpen();
    }

    /**
     * Reset resilience state (circuit breaker, etc.).
     */
    public function resetResilience(): void
    {
        $this->circuitBreaker?->reset();
        $this->fallbackChain?->reset();
    }

    /**
     * Get resilience statistics.
     *
     * @return array<string, mixed>
     */
    public function getResilienceStats(): array
    {
        return [
            'retry' => $this->retryStrategy?->toArray(),
            'circuit_breaker' => $this->circuitBreaker?->stats(),
            'fallback_chain' => $this->fallbackChain?->getHealthStatus(),
        ];
    }

    /**
     * Get provider name (to be implemented by using class).
     */
    abstract protected function getProvider(): string;

    /**
     * Get model name (to be implemented by using class).
     */
    abstract protected function getModel(): string;

    /**
     * Get provider configuration (to be implemented by using class).
     *
     * @return array<string, mixed>
     */
    abstract protected function getProviderConfig(): array;
}
