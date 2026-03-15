<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Rag;

use AgenticOrchestrator\Embeddings\VectorSearchResult;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * RagPipelineResult - Encapsulates RAG pipeline execution results.
 *
 * Contains search results, formatted context, and metadata about
 * the pipeline execution (ingest or query operations).
 *
 * @implements Arrayable<string, mixed>
 */
class RagPipelineResult implements Arrayable, JsonSerializable
{
    /**
     * Create a new pipeline result.
     *
     * @param  array<VectorSearchResult>  $results  Search results
     * @param  string|null  $query  The original query (for query operations)
     * @param  int  $documentsProcessed  Number of documents processed (for ingest)
     * @param  int  $chunksCreated  Number of chunks created (for ingest)
     * @param  float  $durationMs  Execution duration in milliseconds
     * @param  string  $operation  The operation type (ingest, query)
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function __construct(
        public readonly array $results = [],
        public readonly ?string $query = null,
        public readonly int $documentsProcessed = 0,
        public readonly int $chunksCreated = 0,
        public readonly float $durationMs = 0.0,
        public readonly string $operation = 'query',
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a result for an ingest operation.
     *
     * @param  int  $documentsProcessed  Documents processed
     * @param  int  $chunksCreated  Chunks created
     * @param  float  $durationMs  Duration in milliseconds
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public static function forIngest(
        int $documentsProcessed,
        int $chunksCreated,
        float $durationMs,
        array $metadata = []
    ): static {
        return new static(
            results: [],
            query: null,
            documentsProcessed: $documentsProcessed,
            chunksCreated: $chunksCreated,
            durationMs: $durationMs,
            operation: 'ingest',
            metadata: $metadata,
        );
    }

    /**
     * Create a result for a query operation.
     *
     * @param  array<VectorSearchResult>  $results  Search results
     * @param  string  $query  The original query
     * @param  float  $durationMs  Duration in milliseconds
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public static function forQuery(
        array $results,
        string $query,
        float $durationMs,
        array $metadata = []
    ): static {
        return new static(
            results: $results,
            query: $query,
            documentsProcessed: 0,
            chunksCreated: 0,
            durationMs: $durationMs,
            operation: 'query',
            metadata: $metadata,
        );
    }

    /**
     * Check if the result has context.
     */
    public function hasContext(): bool
    {
        return ! empty($this->results);
    }

    /**
     * Get the number of results.
     */
    public function count(): int
    {
        return count($this->results);
    }

    /**
     * Check if the result is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->results);
    }

    /**
     * Get all results.
     *
     * @return array<VectorSearchResult>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Get formatted context string for LLM injection.
     *
     * @param  string  $separator  Separator between chunks
     * @param  bool  $includeMetadata  Include metadata in context
     */
    public function getContext(string $separator = "\n\n---\n\n", bool $includeMetadata = false): string
    {
        if (empty($this->results)) {
            return '';
        }

        $chunks = [];

        foreach ($this->results as $i => $result) {
            $content = $result->getContent();

            if ($includeMetadata) {
                $metadata = $result->getMetadata();
                $source = $metadata['source'] ?? $metadata['file_path'] ?? null;
                $header = $source ? "[Source: {$source}]" : '[Chunk '.($i + 1).']';
                $content = "{$header}\n{$content}";
            }

            $chunks[] = $content;
        }

        return implode($separator, $chunks);
    }

    /**
     * Get context formatted with relevance scores.
     */
    public function getContextWithScores(): string
    {
        if (empty($this->results)) {
            return '';
        }

        $chunks = [];

        foreach ($this->results as $i => $result) {
            $score = round($result->score * 100, 1);
            $chunks[] = "[Relevance: {$score}%]\n{$result->getContent()}";
        }

        return implode("\n\n---\n\n", $chunks);
    }

    /**
     * Get the most relevant result.
     */
    public function getBestResult(): ?VectorSearchResult
    {
        if (empty($this->results)) {
            return null;
        }

        return $this->results[0];
    }

    /**
     * Get the best match content.
     */
    public function getBestMatch(): ?string
    {
        $best = $this->getBestResult();

        return $best?->getContent();
    }

    /**
     * Get results above a score threshold.
     *
     * @param  float  $threshold  Minimum score threshold
     * @return array<VectorSearchResult>
     */
    public function getResultsAboveThreshold(float $threshold): array
    {
        return array_filter(
            $this->results,
            fn (VectorSearchResult $r) => $r->score >= $threshold
        );
    }

    /**
     * Get the average score of all results.
     */
    public function getAverageScore(): float
    {
        if (empty($this->results)) {
            return 0.0;
        }

        $total = array_reduce(
            $this->results,
            fn (float $sum, VectorSearchResult $r) => $sum + $r->score,
            0.0
        );

        return $total / count($this->results);
    }

    /**
     * Get unique source files from results.
     *
     * @return array<string>
     */
    public function getSources(): array
    {
        $sources = [];

        foreach ($this->results as $result) {
            $source = $result->getMeta('source') ?? $result->getMeta('file_path');
            if ($source !== null && ! in_array($source, $sources, true)) {
                $sources[] = $source;
            }
        }

        return $sources;
    }

    /**
     * Check if this was an ingest operation.
     */
    public function isIngest(): bool
    {
        return $this->operation === 'ingest';
    }

    /**
     * Check if this was a query operation.
     */
    public function isQuery(): bool
    {
        return $this->operation === 'query';
    }

    /**
     * Get execution duration in seconds.
     */
    public function getDurationSeconds(): float
    {
        return $this->durationMs / 1000;
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
            'operation' => $this->operation,
            'query' => $this->query,
            'result_count' => $this->count(),
            'documents_processed' => $this->documentsProcessed,
            'chunks_created' => $this->chunksCreated,
            'duration_ms' => $this->durationMs,
            'has_context' => $this->hasContext(),
            'average_score' => $this->getAverageScore(),
            'sources' => $this->getSources(),
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
