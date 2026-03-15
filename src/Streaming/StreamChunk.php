<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Streaming;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Stream Chunk - A single chunk in a streaming response.
 *
 * @implements Arrayable<string, mixed>
 */
class StreamChunk implements Arrayable, JsonSerializable
{
    /**
     * Chunk type constants.
     */
    public const TYPE_CONTENT = 'content';

    public const TYPE_TOOL_CALL = 'tool_call';

    public const TYPE_TOOL_RESULT = 'tool_result';

    public const TYPE_METADATA = 'metadata';

    public const TYPE_ERROR = 'error';

    public const TYPE_DONE = 'done';

    /**
     * Create a new stream chunk.
     *
     * @param  string  $type  The chunk type
     * @param  string  $content  The chunk content
     * @param  array<string, mixed>  $metadata  Additional metadata
     * @param  int  $index  Chunk index in the stream
     * @param  bool  $isLast  Whether this is the last chunk
     */
    public function __construct(
        public readonly string $type,
        public readonly string $content,
        public readonly array $metadata = [],
        public readonly int $index = 0,
        public readonly bool $isLast = false,
    ) {}

    /**
     * Create a content chunk.
     */
    public static function content(string $content, int $index = 0): static
    {
        return new static(
            type: self::TYPE_CONTENT,
            content: $content,
            index: $index,
        );
    }

    /**
     * Create a tool call chunk.
     *
     * @param  array<string, mixed>  $toolCall
     */
    public static function toolCall(array $toolCall, int $index = 0): static
    {
        return new static(
            type: self::TYPE_TOOL_CALL,
            content: '',
            metadata: ['tool_call' => $toolCall],
            index: $index,
        );
    }

    /**
     * Create a tool result chunk.
     */
    public static function toolResult(string $toolName, mixed $result, int $index = 0): static
    {
        return new static(
            type: self::TYPE_TOOL_RESULT,
            content: is_string($result) ? $result : json_encode($result),
            metadata: ['tool_name' => $toolName, 'result' => $result],
            index: $index,
        );
    }

    /**
     * Create an error chunk.
     */
    public static function error(string $error, int $index = 0): static
    {
        return new static(
            type: self::TYPE_ERROR,
            content: $error,
            index: $index,
        );
    }

    /**
     * Create a done/completion chunk.
     *
     * @param  array<string, mixed>  $finalMetadata
     */
    public static function done(array $finalMetadata = [], int $index = 0): static
    {
        return new static(
            type: self::TYPE_DONE,
            content: '',
            metadata: $finalMetadata,
            index: $index,
            isLast: true,
        );
    }

    /**
     * Check if this is a content chunk.
     */
    public function isContent(): bool
    {
        return $this->type === self::TYPE_CONTENT;
    }

    /**
     * Check if this is a tool call chunk.
     */
    public function isToolCall(): bool
    {
        return $this->type === self::TYPE_TOOL_CALL;
    }

    /**
     * Check if this is an error chunk.
     */
    public function isError(): bool
    {
        return $this->type === self::TYPE_ERROR;
    }

    /**
     * Check if this is the final chunk.
     */
    public function isDone(): bool
    {
        return $this->type === self::TYPE_DONE || $this->isLast;
    }

    /**
     * Get metadata value.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'content' => $this->content,
            'metadata' => $this->metadata,
            'index' => $this->index,
            'is_last' => $this->isLast,
        ];
    }

    /**
     * Convert to SSE format.
     */
    public function toSSE(): string
    {
        $data = json_encode($this->toArray());

        return "data: {$data}\n\n";
    }

    /**
     * Serialize for JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
