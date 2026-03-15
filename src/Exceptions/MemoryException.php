<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Exceptions;

use Throwable;

/**
 * Thrown when a memory operation fails.
 */
class MemoryException extends AgentException
{
    protected string $driver = '';

    protected ?string $operation = null;

    /**
     * Create a new memory exception.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = '',
        string $driver = '',
        ?string $operation = null,
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
    ) {
        $this->driver = $driver;
        $this->operation = $operation;

        $prefix = 'Memory error';
        if ($driver) {
            $prefix = "[{$driver}] Memory error";
        }
        if ($operation) {
            $prefix .= " during {$operation}";
        }

        $fullMessage = "{$prefix}: {$message}";

        parent::__construct($fullMessage, $code, $previous, array_merge($context, [
            'driver' => $driver,
            'operation' => $operation,
        ]));
    }

    /**
     * Create for connection failure.
     */
    public static function connectionFailed(
        string $driver,
        ?Throwable $previous = null,
    ): static {
        $exception = new static('Connection failed', $driver, 'connect', 0, $previous);
        $exception->recoverable = true;

        return $exception;
    }

    /**
     * Create for read failure.
     */
    public static function readFailed(
        string $driver,
        string $key,
        ?Throwable $previous = null,
    ): static {
        return new static("Failed to read key: {$key}", $driver, 'read', 0, $previous, [
            'key' => $key,
        ]);
    }

    /**
     * Create for write failure.
     */
    public static function writeFailed(
        string $driver,
        string $key,
        ?Throwable $previous = null,
    ): static {
        return new static("Failed to write key: {$key}", $driver, 'write', 0, $previous, [
            'key' => $key,
        ]);
    }

    /**
     * Create for search failure.
     */
    public static function searchFailed(
        string $driver,
        ?Throwable $previous = null,
    ): static {
        $exception = new static('Search operation failed', $driver, 'search', 0, $previous);
        $exception->recoverable = true;

        return $exception;
    }

    /**
     * Create for invalid driver.
     */
    public static function invalidDriver(string $driver): static
    {
        return new static("Invalid memory driver: {$driver}", $driver);
    }

    /**
     * Get the memory driver.
     */
    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * Get the failed operation.
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }
}
