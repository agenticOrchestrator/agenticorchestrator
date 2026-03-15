<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Exceptions;

use Throwable;

/**
 * Thrown when a tool fails to execute.
 */
class ToolExecutionException extends AgentException
{
    protected string $toolName = '';

    /**
     * @var array<string, mixed>
     */
    protected array $arguments = [];

    /**
     * Create a new tool execution exception.
     *
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $toolName,
        string $message = '',
        array $arguments = [],
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
    ) {
        $this->toolName = $toolName;
        $this->arguments = $arguments;

        $fullMessage = "Tool '{$toolName}' execution failed: {$message}";

        parent::__construct($fullMessage, $code, $previous, array_merge($context, [
            'tool' => $toolName,
            'arguments' => $arguments,
        ]));
    }

    /**
     * Create from a tool name and error message.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function forTool(string $toolName, string $message, array $arguments = []): static
    {
        return new static($toolName, $message, $arguments);
    }

    /**
     * Create for timeout error.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function timeout(string $toolName, int $timeoutSeconds, array $arguments = []): static
    {
        $exception = new static(
            $toolName,
            "Execution timed out after {$timeoutSeconds} seconds",
            $arguments,
        );
        $exception->recoverable = true;

        return $exception;
    }

    /**
     * Create for validation error.
     *
     * @param  array<string, mixed>  $arguments
     * @param  array<string, string>  $errors
     */
    public static function validation(string $toolName, array $arguments, array $errors = []): static
    {
        $errorMessages = implode(', ', $errors);

        return new static(
            $toolName,
            "Validation failed: {$errorMessages}",
            $arguments,
            0,
            null,
            ['validation_errors' => $errors],
        );
    }

    /**
     * Get the tool name.
     */
    public function getToolName(): string
    {
        return $this->toolName;
    }

    /**
     * Get the arguments passed to the tool.
     *
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
