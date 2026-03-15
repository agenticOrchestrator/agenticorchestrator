<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Responses;

use AgenticOrchestrator\Agents\AgentResponse;
use AgenticOrchestrator\Rag\RagPipelineResult;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;

/**
 * A unified response combining LLM and RAG results.
 *
 * HybridResponse provides a homogeneous interface for consuming
 * responses from multiple sources (LLM, RAG, tools) with clear
 * source attribution and confidence scoring.
 *
 * @implements Arrayable<string, mixed>
 */
class HybridResponse implements Arrayable, JsonSerializable
{
    /**
     * Response segments from various sources.
     *
     * @var Collection<int, ResponseSegment>
     */
    protected Collection $segments;

    /**
     * Create a new hybrid response.
     *
     * @param  array<ResponseSegment>|Collection<int, ResponseSegment>  $segments  Response segments
     * @param  string  $query  Original user query
     * @param  HybridStrategy  $strategy  Strategy used to generate this response
     * @param  array<string, mixed>  $usage  Token usage statistics
     * @param  array<string, float>  $latency  Latency breakdown by component
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function __construct(
        array|Collection $segments,
        public readonly string $query,
        public readonly HybridStrategy $strategy,
        public readonly array $usage = [],
        public readonly array $latency = [],
        public readonly array $metadata = [],
    ) {
        $this->segments = $segments instanceof Collection
            ? $segments
            : collect($segments);
    }

    /**
     * Create a hybrid response from an AgentResponse (LLM only).
     */
    public static function fromAgentResponse(
        AgentResponse $response,
        string $query,
    ): static {
        $segment = ResponseSegment::fromLlm(
            content: $response->content,
            metadata: [
                'model' => $response->metadata['model'] ?? null,
                'finish_reason' => $response->finishReason,
                'tool_calls' => $response->toolCalls,
            ],
        );

        return new static(
            segments: [$segment],
            query: $query,
            strategy: HybridStrategy::LLM_ONLY,
            usage: $response->usage,
            latency: ['llm_ms' => $response->latency * 1000, 'total_ms' => $response->latency * 1000],
            metadata: $response->metadata,
        );
    }

    /**
     * Create a hybrid response from RAG results only.
     */
    public static function fromRagResult(
        RagPipelineResult $result,
        string $query,
    ): static {
        $segments = collect($result->getResults())->map(function ($item) {
            return ResponseSegment::fromRag(
                content: self::extractContent($item),
                score: self::extractScore($item),
                metadata: self::extractMetadata($item),
            );
        });

        return new static(
            segments: $segments,
            query: $query,
            strategy: HybridStrategy::RAG_ONLY,
            usage: [],
            latency: ['rag_ms' => $result->durationMs, 'total_ms' => $result->durationMs],
            metadata: $result->metadata,
        );
    }

    /**
     * Create a hybrid response combining LLM and RAG.
     *
     * @param  AgentResponse  $llmResponse  The LLM response
     * @param  RagPipelineResult  $ragResult  The RAG retrieval result
     * @param  string  $query  Original query
     * @param  HybridStrategy  $strategy  Strategy used
     */
    public static function fromCombined(
        AgentResponse $llmResponse,
        RagPipelineResult $ragResult,
        string $query,
        HybridStrategy $strategy = HybridStrategy::RAG_AUGMENTED,
    ): static {
        $segments = collect();

        // Add RAG segments first (context)
        foreach ($ragResult->getResults() as $index => $item) {
            $segments->push(
                ResponseSegment::fromRag(
                    content: self::extractContent($item),
                    score: self::extractScore($item),
                    metadata: self::extractMetadata($item),
                )->withOrder($index)
            );
        }

        // Add LLM segment (synthesized answer)
        $segments->push(
            ResponseSegment::fromLlm(
                content: $llmResponse->content,
                metadata: [
                    'model' => $llmResponse->metadata['model'] ?? null,
                    'finish_reason' => $llmResponse->finishReason,
                    'tool_calls' => $llmResponse->toolCalls,
                ],
            )->withOrder($segments->count())
        );

        $ragLatencyMs = $ragResult->durationMs;
        $llmLatencyMs = $llmResponse->latency * 1000;

        return new static(
            segments: $segments,
            query: $query,
            strategy: $strategy,
            usage: $llmResponse->usage,
            latency: [
                'rag_ms' => $ragLatencyMs,
                'llm_ms' => $llmLatencyMs,
                'total_ms' => $ragLatencyMs + $llmLatencyMs,
            ],
            metadata: array_merge(
                $llmResponse->metadata,
                ['rag_metadata' => $ragResult->metadata]
            ),
        );
    }

    /**
     * Create a builder for constructing hybrid responses.
     */
    public static function builder(string $query): HybridResponseBuilder
    {
        return new HybridResponseBuilder($query);
    }

    /**
     * Get all segments.
     *
     * @return Collection<int, ResponseSegment>
     */
    public function getSegments(): Collection
    {
        return $this->segments;
    }

    /**
     * Get segments filtered by source type.
     *
     * @return Collection<int, ResponseSegment>
     */
    public function getSegmentsBySource(string $source): Collection
    {
        return $this->segments->filter(fn (ResponseSegment $s) => $s->source === $source);
    }

    /**
     * Get only RAG segments.
     *
     * @return Collection<int, ResponseSegment>
     */
    public function getRagSegments(): Collection
    {
        return $this->getSegmentsBySource(ResponseSegment::SOURCE_RAG);
    }

    /**
     * Get only LLM segments.
     *
     * @return Collection<int, ResponseSegment>
     */
    public function getLlmSegments(): Collection
    {
        return $this->getSegmentsBySource(ResponseSegment::SOURCE_LLM);
    }

    /**
     * Get the primary/best segment (highest confidence or first LLM).
     */
    public function getPrimarySegment(): ?ResponseSegment
    {
        // For LLM-only or RAG-augmented, prefer LLM segment
        if ($this->strategy->usesLlm()) {
            $llm = $this->getLlmSegments()->first();
            if ($llm !== null) {
                return $llm;
            }
        }

        // For RAG-only or as fallback, return highest confidence
        return $this->segments
            ->sortByDesc(fn (ResponseSegment $s) => $s->confidence ?? 0)
            ->first();
    }

    /**
     * Get the primary content (from primary segment).
     */
    public function getContent(): string
    {
        $segment = $this->getPrimarySegment();

        return $segment !== null ? $segment->content : '';
    }

    /**
     * Get combined content from all segments.
     *
     * @param  string  $separator  Separator between segments
     */
    public function getCombinedContent(string $separator = "\n\n"): string
    {
        return $this->segments
            ->sortBy(fn (ResponseSegment $s) => $s->order ?? PHP_INT_MAX)
            ->map(fn (ResponseSegment $s) => $s->content)
            ->implode($separator);
    }

    /**
     * Get content with source attribution.
     *
     * @return array<int, array{content: string, source: string, confidence: float|null}>
     */
    public function getAttributedContent(): array
    {
        return $this->segments->map(fn (ResponseSegment $s) => [
            'content' => $s->content,
            'source' => $s->source,
            'confidence' => $s->confidence,
        ])->values()->all();
    }

    /**
     * Check if response has RAG context.
     */
    public function hasRagContext(): bool
    {
        return $this->getRagSegments()->isNotEmpty();
    }

    /**
     * Check if response has LLM content.
     */
    public function hasLlmContent(): bool
    {
        return $this->getLlmSegments()->isNotEmpty();
    }

    /**
     * Check if this is a hybrid (multi-source) response.
     */
    public function isHybrid(): bool
    {
        return $this->hasRagContext() && $this->hasLlmContent();
    }

    /**
     * Get the total number of segments.
     */
    public function segmentCount(): int
    {
        return $this->segments->count();
    }

    /**
     * Get segments meeting a confidence threshold.
     *
     * @return Collection<int, ResponseSegment>
     */
    public function getHighConfidenceSegments(float $threshold = 0.7): Collection
    {
        return $this->segments->filter(fn (ResponseSegment $s) => $s->meetsThreshold($threshold));
    }

    /**
     * Get the average confidence score across RAG segments.
     */
    public function getAverageRagConfidence(): ?float
    {
        $ragSegments = $this->getRagSegments()->filter(fn (ResponseSegment $s) => $s->hasConfidence());

        if ($ragSegments->isEmpty()) {
            return null;
        }

        return $ragSegments->avg(fn (ResponseSegment $s) => $s->confidence);
    }

    /**
     * Get all unique sources from RAG segments.
     *
     * @return array<string>
     */
    public function getSources(): array
    {
        return $this->getRagSegments()
            ->map(fn (ResponseSegment $s) => $s->getMeta('source'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get total token usage.
     */
    public function getTotalTokens(): int
    {
        return ($this->usage['prompt_tokens'] ?? 0) + ($this->usage['completion_tokens'] ?? 0);
    }

    /**
     * Get total latency in milliseconds.
     */
    public function getTotalLatencyMs(): float
    {
        return $this->latency['total_ms'] ?? 0.0;
    }

    /**
     * Get latency breakdown.
     *
     * @return array<string, float>
     */
    public function getLatencyBreakdown(): array
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
     * Transform segments using a callback.
     *
     * @param  callable(ResponseSegment): ResponseSegment  $callback
     */
    public function mapSegments(callable $callback): static
    {
        return new static(
            segments: $this->segments->map($callback),
            query: $this->query,
            strategy: $this->strategy,
            usage: $this->usage,
            latency: $this->latency,
            metadata: $this->metadata,
        );
    }

    /**
     * Filter segments using a callback.
     *
     * @param  callable(ResponseSegment): bool  $callback
     */
    public function filterSegments(callable $callback): static
    {
        return new static(
            segments: $this->segments->filter($callback)->values(),
            query: $this->query,
            strategy: $this->strategy,
            usage: $this->usage,
            latency: $this->latency,
            metadata: $this->metadata,
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
            'segments' => $this->segments->map(fn (ResponseSegment $s) => $s->toArray())->all(),
            'query' => $this->query,
            'strategy' => $this->strategy->value,
            'usage' => $this->usage,
            'latency' => $this->latency,
            'metadata' => $this->metadata,
            'summary' => [
                'segment_count' => $this->segmentCount(),
                'has_rag' => $this->hasRagContext(),
                'has_llm' => $this->hasLlmContent(),
                'is_hybrid' => $this->isHybrid(),
                'sources' => $this->getSources(),
                'avg_rag_confidence' => $this->getAverageRagConfidence(),
            ],
        ];
    }

    /**
     * Get a simplified array for API responses.
     *
     * @return array<string, mixed>
     */
    public function toApiResponse(): array
    {
        return [
            'content' => $this->getContent(),
            'segments' => $this->getAttributedContent(),
            'strategy' => $this->strategy->value,
            'sources' => $this->getSources(),
            'latency_ms' => $this->getTotalLatencyMs(),
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
     * Extract content from a RAG result item.
     *
     * @param  mixed  $item  VectorSearchResult object or array
     */
    protected static function extractContent(mixed $item): string
    {
        if (is_array($item)) {
            return $item['content'] ?? '';
        }

        return $item->getContent();
    }

    /**
     * Extract score from a RAG result item.
     *
     * @param  mixed  $item  VectorSearchResult object or array
     */
    protected static function extractScore(mixed $item): float
    {
        if (is_array($item)) {
            return $item['score'] ?? 0.0;
        }

        return $item->score;
    }

    /**
     * Extract metadata from a RAG result item.
     *
     * @param  mixed  $item  VectorSearchResult object or array
     * @return array<string, mixed>
     */
    protected static function extractMetadata(mixed $item): array
    {
        if (is_array($item)) {
            return [
                'source' => $item['source'] ?? null,
                'chunk_index' => $item['chunk_index'] ?? null,
                'document_id' => $item['document_id'] ?? null,
            ];
        }

        return [
            'source' => $item->getMeta('source'),
            'chunk_index' => $item->getMeta('chunk_index'),
            'document_id' => $item->getId(),
        ];
    }

    /**
     * Convert to string (returns primary content).
     */
    public function __toString(): string
    {
        return $this->getContent();
    }
}
