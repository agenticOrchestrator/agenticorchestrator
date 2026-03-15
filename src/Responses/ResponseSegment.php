<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Responses;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Represents a single segment of a hybrid response.
 *
 * Each segment contains content from a specific source (RAG or LLM)
 * with associated metadata and confidence scoring.
 *
 * @implements Arrayable<string, mixed>
 */
class ResponseSegment implements Arrayable, JsonSerializable
{
    /**
     * Source type constants.
     */
    public const SOURCE_RAG = 'rag';

    public const SOURCE_LLM = 'llm';

    public const SOURCE_CACHED = 'cached';

    public const SOURCE_TOOL = 'tool';

    /**
     * Create a new response segment.
     *
     * @param  string  $content  The content of this segment
     * @param  string  $source  The source type (rag, llm, cached, tool)
     * @param  float|null  $confidence  Confidence/relevance score (0.0-1.0)
     * @param  array<string, mixed>  $metadata  Additional metadata
     * @param  int|null  $order  Display order (for sorting segments)
     */
    public function __construct(
        public readonly string $content,
        public readonly string $source,
        public readonly ?float $confidence = null,
        public readonly array $metadata = [],
        public readonly ?int $order = null,
    ) {}

    /**
     * Create a segment from RAG retrieval result.
     *
     * @param  string  $content  Retrieved content
     * @param  float  $score  Relevance score
     * @param  array<string, mixed>  $metadata  Source metadata
     */
    public static function fromRag(
        string $content,
        float $score,
        array $metadata = [],
    ): static {
        return new static(
            content: $content,
            source: self::SOURCE_RAG,
            confidence: $score,
            metadata: array_merge($metadata, ['retrieval_type' => 'vector']),
        );
    }

    /**
     * Create a segment from LLM response.
     *
     * @param  string  $content  Generated content
     * @param  array<string, mixed>  $metadata  LLM metadata (model, tokens, etc.)
     */
    public static function fromLlm(
        string $content,
        array $metadata = [],
    ): static {
        return new static(
            content: $content,
            source: self::SOURCE_LLM,
            confidence: null, // LLMs don't provide confidence scores
            metadata: $metadata,
        );
    }

    /**
     * Create a segment from cached response.
     *
     * @param  string  $content  Cached content
     * @param  array<string, mixed>  $metadata  Cache metadata
     */
    public static function fromCache(
        string $content,
        array $metadata = [],
    ): static {
        return new static(
            content: $content,
            source: self::SOURCE_CACHED,
            confidence: 1.0, // Cached responses are exact matches
            metadata: $metadata,
        );
    }

    /**
     * Create a segment from tool execution.
     *
     * @param  string  $content  Tool output
     * @param  string  $toolName  Name of the executed tool
     * @param  array<string, mixed>  $metadata  Tool execution metadata
     */
    public static function fromTool(
        string $content,
        string $toolName,
        array $metadata = [],
    ): static {
        return new static(
            content: $content,
            source: self::SOURCE_TOOL,
            confidence: null,
            metadata: array_merge($metadata, ['tool_name' => $toolName]),
        );
    }

    /**
     * Check if this segment is from RAG.
     */
    public function isFromRag(): bool
    {
        return $this->source === self::SOURCE_RAG;
    }

    /**
     * Check if this segment is from LLM.
     */
    public function isFromLlm(): bool
    {
        return $this->source === self::SOURCE_LLM;
    }

    /**
     * Check if this segment is cached.
     */
    public function isFromCache(): bool
    {
        return $this->source === self::SOURCE_CACHED;
    }

    /**
     * Check if this segment is from a tool.
     */
    public function isFromTool(): bool
    {
        return $this->source === self::SOURCE_TOOL;
    }

    /**
     * Check if this segment has a confidence score.
     */
    public function hasConfidence(): bool
    {
        return $this->confidence !== null;
    }

    /**
     * Check if confidence meets a threshold.
     */
    public function meetsThreshold(float $threshold): bool
    {
        return $this->confidence !== null && $this->confidence >= $threshold;
    }

    /**
     * Get a specific metadata value.
     *
     * @param  string  $key  Metadata key
     * @param  mixed  $default  Default value if key doesn't exist
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get the content length.
     */
    public function length(): int
    {
        return strlen($this->content);
    }

    /**
     * Truncate content to a maximum length.
     *
     * @param  int  $maxLength  Maximum length
     * @param  string  $suffix  Suffix to append if truncated
     */
    public function truncate(int $maxLength, string $suffix = '...'): static
    {
        if ($this->length() <= $maxLength) {
            return $this;
        }

        return new static(
            content: substr($this->content, 0, $maxLength - strlen($suffix)).$suffix,
            source: $this->source,
            confidence: $this->confidence,
            metadata: array_merge($this->metadata, ['truncated' => true, 'original_length' => $this->length()]),
            order: $this->order,
        );
    }

    /**
     * Create a copy with updated order.
     */
    public function withOrder(int $order): static
    {
        return new static(
            content: $this->content,
            source: $this->source,
            confidence: $this->confidence,
            metadata: $this->metadata,
            order: $order,
        );
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
            'source' => $this->source,
            'confidence' => $this->confidence,
            'metadata' => $this->metadata,
            'order' => $this->order,
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
     * Convert to string (returns content).
     */
    public function __toString(): string
    {
        return $this->content;
    }
}
