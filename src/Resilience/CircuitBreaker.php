<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Resilience;

use AgenticOrchestrator\Exceptions\CircuitBreakerOpenException;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Circuit Breaker - Prevents cascade failures by stopping requests to failing services.
 *
 * States:
 * - CLOSED: Normal operation, requests pass through
 * - OPEN: Service is failing, requests are blocked
 * - HALF_OPEN: Testing if service has recovered
 */
class CircuitBreaker
{
    /**
     * Circuit states.
     */
    public const STATE_CLOSED = 'closed';

    public const STATE_OPEN = 'open';

    public const STATE_HALF_OPEN = 'half_open';

    /**
     * Service identifier.
     */
    protected string $service;

    /**
     * Number of failures before opening circuit.
     */
    protected int $failureThreshold = 5;

    /**
     * Time in seconds before attempting recovery.
     */
    protected int $recoveryTimeout = 30;

    /**
     * Number of successful requests in half-open state to close circuit.
     */
    protected int $successThreshold = 2;

    /**
     * Window in seconds to count failures.
     */
    protected int $failureWindow = 60;

    /**
     * Cache store name.
     */
    protected ?string $cacheStore = null;

    /**
     * Exception types that should trigger the circuit breaker.
     *
     * @var array<class-string<Throwable>>
     */
    protected array $tripOn = [];

    /**
     * Exception types that should not trigger the circuit breaker.
     *
     * @var array<class-string<Throwable>>
     */
    protected array $ignoreExceptions = [];

    /**
     * Custom callback to determine if exception should trip circuit.
     */
    protected ?Closure $shouldTripCallback = null;

    /**
     * Callback when circuit opens.
     */
    protected ?Closure $onOpenCallback = null;

    /**
     * Callback when circuit closes.
     */
    protected ?Closure $onCloseCallback = null;

    /**
     * Callback when circuit enters half-open state.
     */
    protected ?Closure $onHalfOpenCallback = null;

    /**
     * Create a new circuit breaker.
     */
    public function __construct(string $service, ?array $config = null)
    {
        $this->service = $service;

        if ($config !== null) {
            $this->configure($config);
        }
    }

    /**
     * Create a circuit breaker for a service.
     */
    public static function for(string $service): static
    {
        return new static($service);
    }

    /**
     * Configure the circuit breaker.
     *
     * @param  array<string, mixed>  $config
     */
    public function configure(array $config): static
    {
        if (isset($config['failure_threshold'])) {
            $this->failureThreshold = max(1, (int) $config['failure_threshold']);
        }

        if (isset($config['recovery_timeout'])) {
            $this->recoveryTimeout = max(1, (int) $config['recovery_timeout']);
        }

        if (isset($config['success_threshold'])) {
            $this->successThreshold = max(1, (int) $config['success_threshold']);
        }

        if (isset($config['failure_window'])) {
            $this->failureWindow = max(1, (int) $config['failure_window']);
        }

        if (isset($config['cache_store'])) {
            $this->cacheStore = $config['cache_store'];
        }

        if (isset($config['trip_on'])) {
            $this->tripOn = (array) $config['trip_on'];
        }

        if (isset($config['ignore_exceptions'])) {
            $this->ignoreExceptions = (array) $config['ignore_exceptions'];
        }

        return $this;
    }

    /**
     * Set failure threshold.
     */
    public function failureThreshold(int $count): static
    {
        $this->failureThreshold = max(1, $count);

        return $this;
    }

    /**
     * Set recovery timeout in seconds.
     */
    public function recoveryTimeout(int $seconds): static
    {
        $this->recoveryTimeout = max(1, $seconds);

        return $this;
    }

    /**
     * Set success threshold for closing from half-open.
     */
    public function successThreshold(int $count): static
    {
        $this->successThreshold = max(1, $count);

        return $this;
    }

    /**
     * Set failure counting window in seconds.
     */
    public function failureWindow(int $seconds): static
    {
        $this->failureWindow = max(1, $seconds);

        return $this;
    }

    /**
     * Specify exception types that should trip the circuit.
     *
     * @param  array<class-string<Throwable>>  $exceptions
     */
    public function tripOn(array $exceptions): static
    {
        $this->tripOn = $exceptions;

        return $this;
    }

    /**
     * Specify exception types to ignore.
     *
     * @param  array<class-string<Throwable>>  $exceptions
     */
    public function ignoreExceptions(array $exceptions): static
    {
        $this->ignoreExceptions = $exceptions;

        return $this;
    }

    /**
     * Set custom callback to determine if exception should trip circuit.
     */
    public function shouldTrip(Closure $callback): static
    {
        $this->shouldTripCallback = $callback;

        return $this;
    }

    /**
     * Set callback when circuit opens.
     */
    public function onOpen(Closure $callback): static
    {
        $this->onOpenCallback = $callback;

        return $this;
    }

    /**
     * Set callback when circuit closes.
     */
    public function onClose(Closure $callback): static
    {
        $this->onCloseCallback = $callback;

        return $this;
    }

    /**
     * Set callback when circuit enters half-open state.
     */
    public function onHalfOpen(Closure $callback): static
    {
        $this->onHalfOpenCallback = $callback;

        return $this;
    }

    /**
     * Execute a callback through the circuit breaker.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     *
     * @throws CircuitBreakerOpenException
     * @throws Throwable
     */
    public function execute(Closure $callback): mixed
    {
        $state = $this->getState();

        // If circuit is open, check if we should transition to half-open
        if ($state === self::STATE_OPEN) {
            if ($this->shouldAttemptRecovery()) {
                $this->transitionTo(self::STATE_HALF_OPEN);
                $state = self::STATE_HALF_OPEN;
            } else {
                throw new CircuitBreakerOpenException(
                    $this->service,
                    $this->getOpenUntil(),
                    $this->getFailureCount(),
                );
            }
        }

        try {
            $result = $callback();

            $this->recordSuccess();

            return $result;
        } catch (Throwable $e) {
            if ($this->shouldTripeException($e)) {
                $this->recordFailure($e);
            }

            throw $e;
        }
    }

    /**
     * Get current circuit state.
     */
    public function getState(): string
    {
        return $this->cache()->get($this->cacheKey('state'), self::STATE_CLOSED);
    }

    /**
     * Check if the circuit is open.
     */
    public function isOpen(): bool
    {
        return $this->getState() === self::STATE_OPEN;
    }

    /**
     * Check if the circuit is closed.
     */
    public function isClosed(): bool
    {
        return $this->getState() === self::STATE_CLOSED;
    }

    /**
     * Check if the circuit is half-open.
     */
    public function isHalfOpen(): bool
    {
        return $this->getState() === self::STATE_HALF_OPEN;
    }

    /**
     * Get failure count.
     */
    public function getFailureCount(): int
    {
        return (int) $this->cache()->get($this->cacheKey('failures'), 0);
    }

    /**
     * Get success count in half-open state.
     */
    public function getHalfOpenSuccessCount(): int
    {
        return (int) $this->cache()->get($this->cacheKey('half_open_successes'), 0);
    }

    /**
     * Get timestamp when circuit opened.
     */
    public function getOpenedAt(): ?int
    {
        return $this->cache()->get($this->cacheKey('opened_at'));
    }

    /**
     * Get timestamp when circuit will close (for half-open attempt).
     */
    public function getOpenUntil(): ?int
    {
        $openedAt = $this->getOpenedAt();

        return $openedAt !== null ? $openedAt + $this->recoveryTimeout : null;
    }

    /**
     * Manually reset the circuit to closed state.
     */
    public function reset(): void
    {
        $this->transitionTo(self::STATE_CLOSED);
        $this->cache()->forget($this->cacheKey('failures'));
        $this->cache()->forget($this->cacheKey('opened_at'));
        $this->cache()->forget($this->cacheKey('half_open_successes'));
    }

    /**
     * Force the circuit to open.
     */
    public function forceOpen(): void
    {
        $this->transitionTo(self::STATE_OPEN);
        $this->cache()->put($this->cacheKey('opened_at'), time(), 86400);
    }

    /**
     * Record a successful execution.
     */
    protected function recordSuccess(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            $successes = $this->getHalfOpenSuccessCount() + 1;
            $this->cache()->put(
                $this->cacheKey('half_open_successes'),
                $successes,
                $this->recoveryTimeout
            );

            if ($successes >= $this->successThreshold) {
                $this->transitionTo(self::STATE_CLOSED);
            }
        } elseif ($state === self::STATE_CLOSED) {
            // Reset failure count on success
            $this->cache()->forget($this->cacheKey('failures'));
        }
    }

    /**
     * Record a failed execution.
     */
    protected function recordFailure(Throwable $e): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            // Any failure in half-open state reopens the circuit
            $this->transitionTo(self::STATE_OPEN);
            $this->cache()->put($this->cacheKey('opened_at'), time(), 86400);

            Log::warning('Circuit breaker reopened from half-open', [
                'service' => $this->service,
                'exception' => $e::class,
            ]);
        } else {
            // Increment failure count
            $failures = $this->getFailureCount() + 1;
            $this->cache()->put(
                $this->cacheKey('failures'),
                $failures,
                $this->failureWindow
            );

            if ($failures >= $this->failureThreshold) {
                $this->transitionTo(self::STATE_OPEN);
                $this->cache()->put($this->cacheKey('opened_at'), time(), 86400);

                Log::warning('Circuit breaker opened', [
                    'service' => $this->service,
                    'failures' => $failures,
                    'threshold' => $this->failureThreshold,
                ]);
            }
        }
    }

    /**
     * Transition to a new state.
     */
    protected function transitionTo(string $state): void
    {
        $previousState = $this->getState();

        if ($previousState === $state) {
            return;
        }

        $this->cache()->put($this->cacheKey('state'), $state, 86400);

        Log::debug('Circuit breaker state transition', [
            'service' => $this->service,
            'from' => $previousState,
            'to' => $state,
        ]);

        match ($state) {
            self::STATE_OPEN => $this->onOpenCallback?->__invoke($this->service),
            self::STATE_CLOSED => $this->onCloseCallback?->__invoke($this->service),
            self::STATE_HALF_OPEN => $this->onHalfOpenCallback?->__invoke($this->service),
            default => null,
        };

        // Reset half-open success count when closing or opening
        if ($state !== self::STATE_HALF_OPEN) {
            $this->cache()->forget($this->cacheKey('half_open_successes'));
        }
    }

    /**
     * Check if we should attempt recovery.
     */
    protected function shouldAttemptRecovery(): bool
    {
        $openedAt = $this->getOpenedAt();

        if ($openedAt === null) {
            return true;
        }

        return time() >= ($openedAt + $this->recoveryTimeout);
    }

    /**
     * Check if an exception should trip the circuit.
     */
    protected function shouldTripeException(Throwable $e): bool
    {
        // Custom callback takes precedence
        if ($this->shouldTripCallback !== null) {
            return (bool) ($this->shouldTripCallback)($e);
        }

        // Check ignore list
        foreach ($this->ignoreExceptions as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return false;
            }
        }

        // If trip-on list is specified, exception must be in it
        if (! empty($this->tripOn)) {
            foreach ($this->tripOn as $exceptionClass) {
                if ($e instanceof $exceptionClass) {
                    return true;
                }
            }

            return false;
        }

        // Default: all exceptions trip the circuit
        return true;
    }

    /**
     * Get cache key for a given suffix.
     */
    protected function cacheKey(string $suffix): string
    {
        return "circuit_breaker:{$this->service}:{$suffix}";
    }

    /**
     * Get the cache repository.
     */
    protected function cache(): CacheRepository
    {
        return $this->cacheStore !== null
            ? Cache::store($this->cacheStore)
            : Cache::store();
    }

    /**
     * Get circuit breaker statistics.
     *
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        return [
            'service' => $this->service,
            'state' => $this->getState(),
            'failure_count' => $this->getFailureCount(),
            'failure_threshold' => $this->failureThreshold,
            'recovery_timeout' => $this->recoveryTimeout,
            'opened_at' => $this->getOpenedAt(),
            'open_until' => $this->getOpenUntil(),
            'half_open_successes' => $this->getHalfOpenSuccessCount(),
            'success_threshold' => $this->successThreshold,
        ];
    }
}
