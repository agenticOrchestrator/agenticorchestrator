<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tools;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Represents the result of a tool execution.
 */
class ToolResult implements Arrayable, JsonSerializable
{
    /**
     * @param  string  $toolCallId  The ID of the tool call
     * @param  string  $name  The tool name
     * @param  array<string, mixed>  $arguments  The arguments passed to the tool
     * @param  mixed  $result  The execution result
     * @param  bool  $success  Whether execution was successful
     * @param  string|null  $error  Error message if failed
     * @param  float|null  $duration  Execution time in milliseconds
     * @param  bool  $cached  Whether result was from cache
     */
    public function __construct(
        public readonly string $toolCallId,
        public readonly string $name,
        public readonly array $arguments,
        public readonly mixed $result,
        public readonly bool $success = true,
        public readonly ?string $error = null,
        public readonly ?float $duration = null,
        public readonly bool $cached = false,
    ) {}

    /**
     * Create a successful result.
     */
    public static function success(
        string $toolCallId,
        string $name,
        array $arguments,
        mixed $result,
        ?float $duration = null,
        bool $cached = false,
    ): self {
        return new self(
            toolCallId: $toolCallId,
            name: $name,
            arguments: $arguments,
            result: $result,
            success: true,
            duration: $duration,
            cached: $cached,
        );
    }

    /**
     * Create a failed result.
     */
    public static function failure(
        string $toolCallId,
        string $name,
        array $arguments,
        string $error,
        ?float $duration = null,
    ): self {
        return new self(
            toolCallId: $toolCallId,
            name: $name,
            arguments: $arguments,
            result: null,
            success: false,
            error: $error,
            duration: $duration,
        );
    }

    /**
     * Check if execution was successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if execution failed.
     */
    public function isFailure(): bool
    {
        return ! $this->success;
    }

    /**
     * Get the result as a string for the LLM.
     */
    public function getContentForLlm(): string
    {
        if (! $this->success) {
            return "Error: {$this->error}";
        }

        if (is_string($this->result)) {
            return $this->result;
        }

        return json_encode($this->result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tool_call_id' => $this->toolCallId,
            'name' => $this->name,
            'arguments' => $this->arguments,
            'result' => $this->result,
            'success' => $this->success,
            'error' => $this->error,
            'duration' => $this->duration,
            'cached' => $this->cached,
        ];
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
