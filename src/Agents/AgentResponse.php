<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Agents;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * Represents the response from an agent.
 *
 * Contains the generated content, tool calls made, usage statistics,
 * and additional metadata about the response.
 */
class AgentResponse implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * @param  string  $content  The generated text content
     * @param  array<int, array{id: string, name: string, arguments: array<string, mixed>, result: mixed}>  $toolCalls  Tool calls made during generation
     * @param  array{prompt_tokens: int, completion_tokens: int, total_tokens: int}  $usage  Token usage statistics
     * @param  array<string, mixed>  $metadata  Additional response metadata
     * @param  float|null  $latency  Response latency in milliseconds
     * @param  string|null  $finishReason  Why generation stopped (stop, tool_calls, length, etc.)
     */
    public function __construct(
        public readonly string $content,
        public readonly array $toolCalls = [],
        public readonly array $usage = [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
        ],
        public readonly array $metadata = [],
        public readonly ?float $latency = null,
        public readonly ?string $finishReason = null,
    ) {}

    /**
     * Check if the response contains tool calls.
     */
    public function hasToolCalls(): bool
    {
        return count($this->toolCalls) > 0;
    }

    /**
     * Get all tool calls made during this response.
     *
     * @return array<int, array{id: string, name: string, arguments: array<string, mixed>, result: mixed}>
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    /**
     * Get the total tokens used.
     */
    public function getTotalTokens(): int
    {
        return $this->usage['total_tokens'] ?? 0;
    }

    /**
     * Get the prompt tokens used.
     */
    public function getPromptTokens(): int
    {
        return $this->usage['prompt_tokens'] ?? 0;
    }

    /**
     * Get the completion tokens used.
     */
    public function getCompletionTokens(): int
    {
        return $this->usage['completion_tokens'] ?? 0;
    }

    /**
     * Get the response latency in milliseconds.
     */
    public function getLatency(): ?float
    {
        return $this->latency;
    }

    /**
     * Get a specific metadata value.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if the response was successful (completed normally).
     */
    public function isSuccessful(): bool
    {
        return in_array($this->finishReason, ['stop', 'end_turn', null], true);
    }

    /**
     * Check if the response was truncated due to length.
     */
    public function wasTruncated(): bool
    {
        return $this->finishReason === 'length';
    }

    /**
     * Convert the response to a string (returns content).
     */
    public function __toString(): string
    {
        return $this->content;
    }

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'tool_calls' => $this->toolCalls,
            'usage' => $this->usage,
            'metadata' => $this->metadata,
            'latency' => $this->latency,
            'finish_reason' => $this->finishReason,
        ];
    }

    /**
     * Convert the object to its JSON representation.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR);
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

    /**
     * Create a response from a provider's raw response.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromProviderResponse(array $data): self
    {
        return new self(
            content: $data['content'] ?? '',
            toolCalls: $data['tool_calls'] ?? [],
            usage: $data['usage'] ?? [
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
            ],
            metadata: $data['metadata'] ?? [],
            latency: $data['latency'] ?? null,
            finishReason: $data['finish_reason'] ?? null,
        );
    }

    /**
     * Create an empty/error response.
     */
    public static function empty(string $message = ''): self
    {
        return new self(
            content: $message,
            finishReason: 'error',
        );
    }
}
