<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Conversations;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;
use JsonSerializable;

/**
 * Represents a single message in a conversation.
 */
class Message implements Arrayable, JsonSerializable
{
    /**
     * @param  MessageRole  $role  The role of the message sender
     * @param  string  $content  The message content
     * @param  string|null  $id  Unique message identifier
     * @param  array<int, array{id: string, type: string, function: array{name: string, arguments: string}}>|null  $toolCalls  Tool calls in this message
     * @param  string|null  $toolCallId  Tool call ID this message responds to
     * @param  int|null  $tokens  Token count for this message
     * @param  array<string, mixed>  $metadata  Additional metadata
     * @param  Carbon|null  $createdAt  When the message was created
     */
    public function __construct(
        public readonly MessageRole $role,
        public readonly string $content,
        public readonly ?string $id = null,
        public readonly ?array $toolCalls = null,
        public readonly ?string $toolCallId = null,
        public readonly ?int $tokens = null,
        public readonly array $metadata = [],
        public readonly ?Carbon $createdAt = null,
    ) {}

    /**
     * Create a user message.
     */
    public static function user(string $content, array $metadata = []): self
    {
        return new self(
            role: MessageRole::User,
            content: $content,
            id: self::generateId(),
            metadata: $metadata,
            createdAt: Carbon::now(),
        );
    }

    /**
     * Create an assistant message.
     */
    public static function assistant(string $content, ?array $toolCalls = null, array $metadata = []): self
    {
        return new self(
            role: MessageRole::Assistant,
            content: $content,
            id: self::generateId(),
            toolCalls: $toolCalls,
            metadata: $metadata,
            createdAt: Carbon::now(),
        );
    }

    /**
     * Create a system message.
     */
    public static function system(string $content): self
    {
        return new self(
            role: MessageRole::System,
            content: $content,
            id: self::generateId(),
            createdAt: Carbon::now(),
        );
    }

    /**
     * Create a tool result message.
     */
    public static function tool(string $toolCallId, string $content, array $metadata = []): self
    {
        return new self(
            role: MessageRole::Tool,
            content: $content,
            id: self::generateId(),
            toolCallId: $toolCallId,
            metadata: $metadata,
            createdAt: Carbon::now(),
        );
    }

    /**
     * Check if this message has tool calls.
     */
    public function hasToolCalls(): bool
    {
        return ! empty($this->toolCalls);
    }

    /**
     * Get a metadata value.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Generate a unique message ID.
     */
    protected static function generateId(): string
    {
        return 'msg_'.bin2hex(random_bytes(12));
    }

    /**
     * Convert to array for LLM API.
     *
     * @return array{role: string, content: string, tool_calls?: array, tool_call_id?: string}
     */
    public function toApiFormat(): array
    {
        $data = [
            'role' => $this->role->value,
            'content' => $this->content,
        ];

        if ($this->toolCalls !== null) {
            $data['tool_calls'] = $this->toolCalls;
        }

        if ($this->toolCallId !== null) {
            $data['tool_call_id'] = $this->toolCallId;
        }

        return $data;
    }

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role->value,
            'content' => $this->content,
            'tool_calls' => $this->toolCalls,
            'tool_call_id' => $this->toolCallId,
            'tokens' => $this->tokens,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt?->toIso8601String(),
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

    /**
     * Create a message from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            role: MessageRole::from($data['role']),
            content: $data['content'],
            id: $data['id'] ?? null,
            toolCalls: $data['tool_calls'] ?? null,
            toolCallId: $data['tool_call_id'] ?? null,
            tokens: $data['tokens'] ?? null,
            metadata: $data['metadata'] ?? [],
            createdAt: isset($data['created_at']) ? Carbon::parse($data['created_at']) : null,
        );
    }
}
