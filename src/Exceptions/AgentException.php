<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for all agent-related errors.
 *
 * Provides structured error information for debugging and recovery.
 */
class AgentException extends Exception
{
    /**
     * Additional context data for debugging.
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * Whether this exception is recoverable.
     */
    protected bool $recoverable = false;

    /**
     * Create a new agent exception.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Create an exception with context.
     *
     * @param  array<string, mixed>  $context
     */
    public static function withContext(string $message, array $context = []): static
    {
        return new static($message, 0, null, $context);
    }

    /**
     * Create a recoverable exception.
     *
     * @param  array<string, mixed>  $context
     */
    public static function recoverable(string $message, array $context = []): static
    {
        $exception = new static($message, 0, null, $context);
        $exception->recoverable = true;

        return $exception;
    }

    /**
     * Get the context data.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Check if the exception is recoverable.
     */
    public function isRecoverable(): bool
    {
        return $this->recoverable;
    }

    /**
     * Add additional context data.
     *
     * @param  array<string, mixed>  $context
     */
    public function addContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Convert exception to array for logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => static::class,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'context' => $this->context,
            'recoverable' => $this->recoverable,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
        ];
    }
}
