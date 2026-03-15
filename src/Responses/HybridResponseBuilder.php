<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Responses;

use AgenticOrchestrator\Agents\AgentResponse;
use AgenticOrchestrator\Rag\RagPipelineResult;
use Illuminate\Support\Collection;

/**
 * Builder for constructing HybridResponse instances.
 *
 * Provides a fluent interface for building complex hybrid responses
 * from multiple sources with full control over configuration.
 */
class HybridResponseBuilder
{
    /**
     * @var Collection<int, ResponseSegment>
     */
    protected Collection $segments;

    protected HybridStrategy $strategy = HybridStrategy::RAG_AUGMENTED;

    /**
     * @var array<string, mixed>
     */
    protected array $usage = [];

    /**
     * @var array<string, float>
     */
    protected array $latency = [];

    /**
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    protected int $segmentOrder = 0;

    public function __construct(
        protected readonly string $query,
    ) {
        $this->segments = collect();
    }

    /**
     * Add an AgentResponse (LLM) to the hybrid response.
     */
    public function withAgentResponse(AgentResponse $response): static
    {
        $this->segments->push(
            ResponseSegment::fromLlm(
                content: $response->content,
                metadata: [
                    'model' => $response->metadata['model'] ?? null,
                    'finish_reason' => $response->finishReason,
                    'tool_calls' => $response->toolCalls,
                ],
            )->withOrder($this->segmentOrder++)
        );

        // Merge usage
        $this->usage = array_merge($this->usage, $response->usage);

        // Add latency
        $this->latency['llm_ms'] = ($this->latency['llm_ms'] ?? 0) + ($response->latency * 1000);

        // Merge metadata
        $this->metadata = array_merge($this->metadata, $response->metadata);

        return $this;
    }

    /**
     * Add RAG pipeline results to the hybrid response.
     */
    public function withRagResult(RagPipelineResult $result): static
    {
        foreach ($result->getResults() as $item) {
            $this->segments->push(
                ResponseSegment::fromRag(
                    content: $item['content'] ?? '',
                    score: $item['score'] ?? 0.0,
                    metadata: [
                        'source' => $item['source'] ?? null,
                        'chunk_index' => $item['chunk_index'] ?? null,
                        'document_id' => $item['document_id'] ?? null,
                    ],
                )->withOrder($this->segmentOrder++)
            );
        }

        // Add latency
        $this->latency['rag_ms'] = ($this->latency['rag_ms'] ?? 0) + $result->durationMs;

        // Merge metadata
        $this->metadata['rag'] = $result->metadata;

        return $this;
    }

    /**
     * Add a custom segment.
     */
    public function withSegment(ResponseSegment $segment): static
    {
        $this->segments->push($segment->withOrder($this->segmentOrder++));

        return $this;
    }

    /**
     * Add a RAG segment manually.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withRagSegment(
        string $content,
        float $score,
        array $metadata = [],
    ): static {
        $this->segments->push(
            ResponseSegment::fromRag($content, $score, $metadata)
                ->withOrder($this->segmentOrder++)
        );

        return $this;
    }

    /**
     * Add an LLM segment manually.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withLlmSegment(
        string $content,
        array $metadata = [],
    ): static {
        $this->segments->push(
            ResponseSegment::fromLlm($content, $metadata)
                ->withOrder($this->segmentOrder++)
        );

        return $this;
    }

    /**
     * Add a cached segment.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withCachedSegment(
        string $content,
        array $metadata = [],
    ): static {
        $this->segments->push(
            ResponseSegment::fromCache($content, $metadata)
                ->withOrder($this->segmentOrder++)
        );

        return $this;
    }

    /**
     * Add a tool output segment.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withToolSegment(
        string $content,
        string $toolName,
        array $metadata = [],
    ): static {
        $this->segments->push(
            ResponseSegment::fromTool($content, $toolName, $metadata)
                ->withOrder($this->segmentOrder++)
        );

        return $this;
    }

    /**
     * Set the hybrid strategy.
     */
    public function withStrategy(HybridStrategy $strategy): static
    {
        $this->strategy = $strategy;

        return $this;
    }

    /**
     * Set token usage statistics.
     *
     * @param  array<string, mixed>  $usage
     */
    public function withUsage(array $usage): static
    {
        $this->usage = array_merge($this->usage, $usage);

        return $this;
    }

    /**
     * Set latency information.
     *
     * @param  array<string, float>  $latency
     */
    public function withLatency(array $latency): static
    {
        $this->latency = array_merge($this->latency, $latency);

        return $this;
    }

    /**
     * Add additional metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * Set a specific metadata value.
     */
    public function withMeta(string $key, mixed $value): static
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Automatically detect and set the strategy based on segments.
     */
    public function autoDetectStrategy(): static
    {
        $hasRag = $this->segments->contains(fn (ResponseSegment $s) => $s->isFromRag());
        $hasLlm = $this->segments->contains(fn (ResponseSegment $s) => $s->isFromLlm());

        $this->strategy = match (true) {
            $hasRag && $hasLlm => HybridStrategy::RAG_AUGMENTED,
            $hasRag => HybridStrategy::RAG_ONLY,
            $hasLlm => HybridStrategy::LLM_ONLY,
            default => HybridStrategy::LLM_ONLY,
        };

        return $this;
    }

    /**
     * Build the hybrid response.
     */
    public function build(): HybridResponse
    {
        // Calculate total latency if not set
        if (! isset($this->latency['total_ms'])) {
            $this->latency['total_ms'] = array_sum($this->latency);
        }

        return new HybridResponse(
            segments: $this->segments,
            query: $this->query,
            strategy: $this->strategy,
            usage: $this->usage,
            latency: $this->latency,
            metadata: $this->metadata,
        );
    }
}
