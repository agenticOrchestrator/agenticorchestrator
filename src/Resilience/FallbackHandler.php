<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Resilience;

use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fallback Handler - Executes fallback strategies when primary operations fail.
 *
 * Supports:
 * - Multiple fallback chain
 * - Condition-based fallback selection
 * - Default value fallback
 * - Custom fallback callbacks
 */
class FallbackHandler
{
    /**
     * Primary operation.
     */
    protected ?Closure $primary = null;

    /**
     * Fallback chain.
     *
     * @var array<array{callback: Closure, name: string|null, condition: Closure|null}>
     */
    protected array $fallbacks = [];

    /**
     * Default value if all fallbacks fail.
     */
    protected mixed $defaultValue = null;

    /**
     * Whether a default value is set.
     */
    protected bool $hasDefault = false;

    /**
     * Exception types to trigger fallback.
     *
     * @var array<class-string<Throwable>>
     */
    protected array $fallbackOn = [];

    /**
     * Exception types to not trigger fallback.
     *
     * @var array<class-string<Throwable>>
     */
    protected array $dontFallbackOn = [];

    /**
     * Callback for logging/tracking fallback usage.
     */
    protected ?Closure $onFallbackCallback = null;

    /**
     * Create a new fallback handler.
     */
    public function __construct(?Closure $primary = null)
    {
        $this->primary = $primary;
    }

    /**
     * Create a fallback handler with a primary operation.
     */
    public static function try(Closure $primary): static
    {
        return new static($primary);
    }

    /**
     * Set the primary operation.
     */
    public function primary(Closure $callback): static
    {
        $this->primary = $callback;

        return $this;
    }

    /**
     * Add a fallback option.
     */
    public function fallback(Closure $callback, ?string $name = null, ?Closure $condition = null): static
    {
        $this->fallbacks[] = [
            'callback' => $callback,
            'name' => $name,
            'condition' => $condition,
        ];

        return $this;
    }

    /**
     * Add a conditional fallback.
     */
    public function fallbackWhen(Closure $condition, Closure $callback, ?string $name = null): static
    {
        return $this->fallback($callback, $name, $condition);
    }

    /**
     * Add a fallback for a specific exception type.
     *
     * @param  class-string<Throwable>  $exceptionClass
     */
    public function fallbackFor(string $exceptionClass, Closure $callback, ?string $name = null): static
    {
        return $this->fallback(
            $callback,
            $name,
            fn (Throwable $e) => $e instanceof $exceptionClass
        );
    }

    /**
     * Set default value if all fallbacks fail.
     */
    public function default(mixed $value): static
    {
        $this->defaultValue = $value;
        $this->hasDefault = true;

        return $this;
    }

    /**
     * Specify exception types that should trigger fallback.
     *
     * @param  array<class-string<Throwable>>  $exceptions
     */
    public function fallbackOn(array $exceptions): static
    {
        $this->fallbackOn = $exceptions;

        return $this;
    }

    /**
     * Specify exception types that should not trigger fallback.
     *
     * @param  array<class-string<Throwable>>  $exceptions
     */
    public function dontFallbackOn(array $exceptions): static
    {
        $this->dontFallbackOn = $exceptions;

        return $this;
    }

    /**
     * Set callback when fallback is used.
     */
    public function onFallback(Closure $callback): static
    {
        $this->onFallbackCallback = $callback;

        return $this;
    }

    /**
     * Execute with fallback support.
     *
     * @template T
     *
     * @return T
     *
     * @throws Throwable
     */
    public function execute(): mixed
    {
        if ($this->primary === null) {
            throw new \RuntimeException('No primary operation defined');
        }

        $lastException = null;

        // Try primary operation
        try {
            return ($this->primary)();
        } catch (Throwable $e) {
            if (! $this->shouldFallback($e)) {
                throw $e;
            }

            $lastException = $e;

            Log::debug('Primary operation failed, trying fallbacks', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        // Try each fallback
        foreach ($this->fallbacks as $index => $fallback) {
            // Check condition if specified
            if ($fallback['condition'] !== null && ! ($fallback['condition'])($lastException)) {
                continue;
            }

            try {
                $result = ($fallback['callback'])($lastException);

                $this->recordFallbackUsed($fallback['name'] ?? "fallback_{$index}", $lastException);

                return $result;
            } catch (Throwable $e) {
                $lastException = $e;

                Log::debug('Fallback failed', [
                    'fallback' => $fallback['name'] ?? $index,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // Return default if set
        if ($this->hasDefault) {
            $this->recordFallbackUsed('default', $lastException);

            return $this->defaultValue;
        }

        // All fallbacks failed
        throw $lastException;
    }

    /**
     * Check if an exception should trigger fallback.
     */
    protected function shouldFallback(Throwable $e): bool
    {
        // Check "don't fallback on" list
        foreach ($this->dontFallbackOn as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return false;
            }
        }

        // If "fallback on" list is specified, exception must be in it
        if (! empty($this->fallbackOn)) {
            foreach ($this->fallbackOn as $exceptionClass) {
                if ($e instanceof $exceptionClass) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * Record that a fallback was used.
     */
    protected function recordFallbackUsed(string $name, ?Throwable $originalException): void
    {
        Log::info('Fallback used', [
            'fallback' => $name,
            'original_exception' => $originalException?->getMessage(),
        ]);

        if ($this->onFallbackCallback !== null) {
            ($this->onFallbackCallback)($name, $originalException);
        }
    }
}
