<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Rag;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Document - Represents a document in the RAG pipeline.
 *
 * Documents are the primary unit of content in the RAG pipeline,
 * containing text content and associated metadata.
 *
 * @implements Arrayable<string, mixed>
 */
class Document implements Arrayable, JsonSerializable
{
    /**
     * Create a new document.
     *
     * @param  string  $id  Unique document identifier
     * @param  string  $content  The document content
     * @param  array<string, mixed>  $metadata  Additional metadata
     * @param  string|null  $source  Source file path or URL
     */
    public function __construct(
        public readonly string $id,
        public readonly string $content,
        public readonly array $metadata = [],
        public readonly ?string $source = null,
    ) {}

    /**
     * Create a document from text content.
     *
     * @param  string  $content  The text content
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public static function fromText(string $content, array $metadata = []): static
    {
        return new static(
            id: static::generateId($content),
            content: $content,
            metadata: $metadata,
        );
    }

    /**
     * Create a document from a file path.
     *
     * @param  string  $path  The file path
     * @param  string  $content  The file content
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public static function fromFile(string $path, string $content, array $metadata = []): static
    {
        return new static(
            id: static::generateId($path),
            content: $content,
            metadata: array_merge([
                'source_type' => 'file',
                'file_name' => basename($path),
                'file_path' => $path,
            ], $metadata),
            source: $path,
        );
    }

    /**
     * Create from array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            id: $data['id'] ?? static::generateId($data['content'] ?? ''),
            content: $data['content'] ?? '',
            metadata: $data['metadata'] ?? [],
            source: $data['source'] ?? null,
        );
    }

    /**
     * Generate a unique ID for a document.
     */
    public static function generateId(string $content): string
    {
        return 'doc_'.substr(md5($content.microtime()), 0, 16);
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
     * Get the content length in characters.
     */
    public function getLength(): int
    {
        return mb_strlen($this->content);
    }

    /**
     * Check if the document content is empty.
     */
    public function isEmpty(): bool
    {
        return trim($this->content) === '';
    }

    /**
     * Create a copy with updated content.
     */
    public function withContent(string $content): static
    {
        return new static(
            id: $this->id,
            content: $content,
            metadata: $this->metadata,
            source: $this->source,
        );
    }

    /**
     * Create a copy with additional metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        return new static(
            id: $this->id,
            content: $this->content,
            metadata: array_merge($this->metadata, $metadata),
            source: $this->source,
        );
    }

    /**
     * Create a copy with a new ID.
     */
    public function withId(string $id): static
    {
        return new static(
            id: $id,
            content: $this->content,
            metadata: $this->metadata,
            source: $this->source,
        );
    }

    /**
     * Create a chunk from this document.
     *
     * @param  string  $content  The chunk content
     * @param  int  $chunkIndex  The chunk index
     * @param  int  $startOffset  The start offset in the original content
     */
    public function createChunk(string $content, int $chunkIndex, int $startOffset): static
    {
        return new static(
            id: "{$this->id}_chunk_{$chunkIndex}",
            content: $content,
            metadata: array_merge($this->metadata, [
                'parent_id' => $this->id,
                'chunk_index' => $chunkIndex,
                'start_offset' => $startOffset,
                'is_chunk' => true,
            ]),
            source: $this->source,
        );
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
            'metadata' => $this->metadata,
            'source' => $this->source,
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
