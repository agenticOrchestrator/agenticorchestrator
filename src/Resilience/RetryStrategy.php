<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Resilience;

use AgenticOrchestrator\Exceptions\AgentException;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Retry Strategy - Configurable retry logic with backoff.
 *
 * Supports exponential, linear, and constant backoff strategies
 * with configurable maximum attempts and jitter.
 */
class RetryStrategy
{
    /**
     * Backoff strategy types.
     */
    public const BACKOFF_CONSTANT = 'constant';

    public const BACKOFF_LINEAR = 'linear';

    public const BACKOFF_EXPONENTIAL = 'exponential';

    /**
     * Maximum number of retry attempts.
     */
    protected int $maxAttempts = 3;

    /**
     * Backoff strategy type.
     */
    protected string $backoffType = self::BACKOFF_EXPONENTIAL;

    /**
     * Base delay in milliseconds.
     */
    protected int $baseDelayMs = 1000;

    /**
     * Maximum delay in milliseconds.
     */
    protected int $maxDelayMs = 30000;

    /**
     * Jitter factor (0 to 1) for randomizing delays.
     */
    protected float $jitter = 0.1;

    /**
     * Exception types that should trigger a retry.
     *
     * @var array<class-string<Throwable>>
     */
    protected array $retryOn = [];

    /**
     * Exception types that should not trigger a retry.
     *
     * @var array<class-string<Throwable>>
     */
    protected array $dontRetryOn = [];

    /**
     * Custom callback to determine if exception is retryable.
     */
    protected ?Closure $shouldRetryCallback = null;

    /**
     * Callback executed before each retry.
     */
    protected ?Closure $onRetryCallback = null;

    /**
     * Create a new retry strategy.
     */
    public function __construct(?array $config = null)
    {
        if ($config !== null) {
            $this->configure($config);
        }
    }

    /**
     * Create with default configuration.
     */
    public static function default(): static
    {
        return new static([
            'max_attempts' => 3,
            'backoff' => self::BACKOFF_EXPONENTIAL,
            'base_delay_ms' => 1000,
            'max_delay_ms' => 30000,
            'jitter' => 0.1,
        ]);
    }

    /**
     * Create with no retries.
     */
    public static function none(): static
    {
        return new static(['max_attempts' => 1]);
    }

    /**
     * Create with constant backoff.
     */
    public static function constant(int $maxAttempts, int $delayMs): static
    {
        return new static([
            'max_attempts' => $maxAttempts,
            'backoff' => self::BACKOFF_CONSTANT,
            'base_delay_ms' => $delayMs,
            'jitter' => 0,
        ]);
    }

    /**
     * Create with linear backoff.
     */
    public static function linear(int $maxAttempts, int $baseDelayMs): static
    {
        return new static([
            'max_attempts' => $maxAttempts,
            'backoff' => self::BACKOFF_LINEAR,
            'base_delay_ms' => $baseDelayMs,
        ]);
    }

    /**
     * Create with exponential backoff.
     */
    public static function exponential(int $maxAttempts, int $baseDelayMs): static
    {
        return new static([
            'max_attempts' => $maxAttempts,
            'backoff' => self::BACKOFF_EXPONENTIAL,
            'base_delay_ms' => $baseDelayMs,
        ]);
    }

    /**
     * Configure the retry strategy.
     *
     * @param  array<string, mixed>  $config
     */
    public function configure(array $config): static
    {
        if (isset($config['max_attempts'])) {
            $this->maxAttempts = max(1, (int) $config['max_attempts']);
        }

        if (isset($config['backoff'])) {
            $this->backoffType = $config['backoff'];
        }

        if (isset($config['base_delay_ms'])) {
            $this->baseDelayMs = max(0, (int) $config['base_delay_ms']);
        }

        if (isset($config['max_delay_ms'])) {
            $this->maxDelayMs = max($this->baseDelayMs, (int) $config['max_delay_ms']);
        }

        if (isset($config['jitter'])) {
            $this->jitter = max(0, min(1, (float) $config['jitter']));
        }

        if (isset($config['retry_on'])) {
            $this->retryOn = (array) $config['retry_on'];
        }

        if (isset($config['dont_retry_on'])) {
            $this->dontRetryOn = (array) $config['dont_retry_on'];
        }

        return $this;
    }

    /**
     * Set maximum attempts.
     */
    public function maxAttempts(int $attempts): static
    {
        $this->maxAttempts = max(1, $attempts);

        return $this;
    }

    /**
     * Set backoff type.
     */
    public function backoff(string $type): static
    {
        $this->backoffType = $type;

        return $this;
    }

    /**
     * Set base delay in milliseconds.
     */
    public function baseDelay(int $ms): static
    {
        $this->baseDelayMs = max(0, $ms);

        return $this;
    }

    /**
     * Set maximum delay in milliseconds.
     */
    public function maxDelay(int $ms): static
    {
        $this->maxDelayMs = max($this->baseDelayMs, $ms);

        return $this;
    }

    /**
     * Set jitter factor.
     */
    public function withJitter(float $factor): static
    {
        $this->jitter = max(0, min(1, $factor));

        return $this;
    }

    /**
     * Specify exception types to retry on.
     *
     * @param  array<class-string<Throwable>>  $exceptions
     */
    public function retryOn(array $exceptions): static
    {
        $this->retryOn = $exceptions;

        return $this;
    }

    /**
     * Specify exception types to not retry on.
     *
     * @param  array<class-string<Throwable>>  $exceptions
     */
    public function dontRetryOn(array $exceptions): static
    {
        $this->dontRetryOn = $exceptions;

        return $this;
    }

    /**
     * Set custom callback to determine if exception is retryable.
     */
    public function shouldRetry(Closure $callback): static
    {
        $this->shouldRetryCallback = $callback;

        return $this;
    }

    /**
     * Set callback to execute before each retry.
     */
    public function onRetry(Closure $callback): static
    {
        $this->onRetryCallback = $callback;

        return $this;
    }

    /**
     * Execute a callback with retry logic.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     *
     * @throws Throwable
     */
    public function execute(Closure $callback): mixed
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxAttempts) {
            $attempts++;

            try {
                return $callback();
            } catch (Throwable $e) {
                $lastException = $e;

                if (! $this->shouldRetryException($e)) {
                    throw $e;
                }

                if ($attempts >= $this->maxAttempts) {
                    break;
                }

                $delayMs = $this->calculateDelay($attempts);

                Log::debug('Retry attempt', [
                    'attempt' => $attempts,
                    'max_attempts' => $this->maxAttempts,
                    'delay_ms' => $delayMs,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                if ($this->onRetryCallback !== null) {
                    ($this->onRetryCallback)($attempts, $e, $delayMs);
                }

                $this->sleep($delayMs);
            }
        }

        throw $lastException;
    }

    /**
     * Calculate delay for a given attempt.
     */
    public function calculateDelay(int $attempt): int
    {
        $delay = match ($this->backoffType) {
            self::BACKOFF_CONSTANT => $this->baseDelayMs,
            self::BACKOFF_LINEAR => $this->baseDelayMs * $attempt,
            self::BACKOFF_EXPONENTIAL => $this->baseDelayMs * (2 ** ($attempt - 1)),
            default => $this->baseDelayMs,
        };

        // Apply jitter
        if ($this->jitter > 0) {
            $jitterRange = $delay * $this->jitter;
            $delay += mt_rand((int) -$jitterRange, (int) $jitterRange);
        }

        // Clamp to max delay
        return (int) min(max(0, $delay), $this->maxDelayMs);
    }

    /**
     * Check if an exception should trigger a retry.
     */
    protected function shouldRetryException(Throwable $e): bool
    {
        // Custom callback takes precedence
        if ($this->shouldRetryCallback !== null) {
            return (bool) ($this->shouldRetryCallback)($e);
        }

        // Check "don't retry on" list
        foreach ($this->dontRetryOn as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return false;
            }
        }

        // If "retry on" list is specified, exception must be in it
        if (! empty($this->retryOn)) {
            foreach ($this->retryOn as $exceptionClass) {
                if ($e instanceof $exceptionClass) {
                    return true;
                }
            }

            return false;
        }

        // Check if exception is marked as recoverable
        if ($e instanceof AgentException) {
            return $e->isRecoverable();
        }

        // Default: retry on all exceptions
        return true;
    }

    /**
     * Sleep for the specified milliseconds.
     */
    protected function sleep(int $ms): void
    {
        usleep($ms * 1000);
    }

    /**
     * Get the configuration.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'max_attempts' => $this->maxAttempts,
            'backoff' => $this->backoffType,
            'base_delay_ms' => $this->baseDelayMs,
            'max_delay_ms' => $this->maxDelayMs,
            'jitter' => $this->jitter,
            'retry_on' => $this->retryOn,
            'dont_retry_on' => $this->dontRetryOn,
        ];
    }
}
