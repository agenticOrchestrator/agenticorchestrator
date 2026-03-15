<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Embeddings;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Vector Document - Represents a document with embedding.
 *
 * @implements Arrayable<string, mixed>
 */
class VectorDocument implements Arrayable, JsonSerializable
{
    /**
     * Create a new vector document.
     *
     * @param  string  $id  Unique document identifier
     * @param  string  $content  The original content
     * @param  array<float>  $embedding  The embedding vector
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $content,
        public readonly array $embedding = [],
        public readonly array $metadata = [],
    ) {}

    /**
     * Create from array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            id: $data['id'],
            content: $data['content'],
            embedding: $data['embedding'] ?? [],
            metadata: $data['metadata'] ?? [],
        );
    }

    /**
     * Get metadata value.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if has metadata key.
     */
    public function hasMeta(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    /**
     * Create copy with updated embedding.
     *
     * @param  array<float>  $embedding
     */
    public function withEmbedding(array $embedding): static
    {
        return new static(
            id: $this->id,
            content: $this->content,
            embedding: $embedding,
            metadata: $this->metadata,
        );
    }

    /**
     * Create copy with additional metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        return new static(
            id: $this->id,
            content: $this->content,
            embedding: $this->embedding,
            metadata: array_merge($this->metadata, $metadata),
        );
    }

    /**
     * Get embedding dimension.
     */
    public function getDimension(): int
    {
        return count($this->embedding);
    }

    /**
     * Check if has embedding.
     */
    public function hasEmbedding(): bool
    {
        return ! empty($this->embedding);
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'embedding' => $this->embedding,
            'metadata' => $this->metadata,
        ];
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
