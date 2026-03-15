<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Embeddings;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Vector Search Result - A single search result with similarity score.
 *
 * @implements Arrayable<string, mixed>
 */
class VectorSearchResult implements Arrayable, JsonSerializable
{
    /**
     * Create a new search result.
     *
     * @param  VectorDocument  $document  The matched document
     * @param  float  $score  Similarity score (higher = more similar)
     * @param  float|null  $distance  Distance metric (lower = closer)
     */
    public function __construct(
        public readonly VectorDocument $document,
        public readonly float $score,
        public readonly ?float $distance = null,
    ) {}

    /**
     * Create from array data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            document: VectorDocument::fromArray($data['document'] ?? $data),
            score: $data['score'] ?? 0.0,
            distance: $data['distance'] ?? null,
        );
    }

    /**
     * Get the document ID.
     */
    public function getId(): string
    {
        return $this->document->id;
    }

    /**
     * Get the document content.
     */
    public function getContent(): string
    {
        return $this->document->content;
    }

    /**
     * Get document metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->document->metadata;
    }

    /**
     * Get a specific metadata value.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->document->getMeta($key, $default);
    }

    /**
     * Check if score is above threshold.
     */
    public function isAboveThreshold(float $threshold): bool
    {
        return $this->score >= $threshold;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'document' => $this->document->toArray(),
            'score' => $this->score,
            'distance' => $this->distance,
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
