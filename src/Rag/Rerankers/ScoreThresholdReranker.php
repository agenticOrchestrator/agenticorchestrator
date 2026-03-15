<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Rag\Rerankers;

use AgenticOrchestrator\Embeddings\VectorSearchResult;
use AgenticOrchestrator\Rag\Contracts\RerankerInterface;

/**
 * ScoreThresholdReranker - Filters results by similarity score threshold.
 *
 * Simple reranker that filters out results below a minimum score threshold
 * and optionally limits the number of results.
 */
class ScoreThresholdReranker implements RerankerInterface
{
    /**
     * The minimum score threshold.
     */
    protected float $threshold = 0.7;

    /**
     * Maximum number of results to return (null = no limit).
     */
    protected ?int $maxResults = null;

    /**
     * Minimum score gap between results to include.
     */
    protected ?float $minScoreGap = null;

    /**
     * Create a new score threshold reranker.
     */
    public function __construct(
        float $threshold = 0.7,
        ?int $maxResults = null,
    ) {
        $this->threshold = $threshold;
        $this->maxResults = $maxResults;
    }

    /**
     * Rerank and filter search results.
     *
     * @param  array<VectorSearchResult>  $results
     * @return array<VectorSearchResult>
     */
    public function rerank(array $results, string $query): array
    {
        // Filter by threshold
        $filtered = array_filter(
            $results,
            fn (VectorSearchResult $r) => $r->score >= $this->threshold
        );

        // Sort by score (descending)
        usort($filtered, fn ($a, $b) => $b->score <=> $a->score);

        // Apply score gap filtering if configured
        if ($this->minScoreGap !== null && count($filtered) > 1) {
            $filtered = $this->filterByScoreGap($filtered);
        }

        // Apply max results limit
        if ($this->maxResults !== null) {
            $filtered = array_slice($filtered, 0, $this->maxResults);
        }

        return array_values($filtered);
    }

    /**
     * Set the score threshold.
     */
    public function setThreshold(float $threshold): static
    {
        $this->threshold = max(0.0, min(1.0, $threshold));

        return $this;
    }

    /**
     * Set the maximum number of results.
     */
    public function setMaxResults(?int $maxResults): static
    {
        $this->maxResults = $maxResults;

        return $this;
    }

    /**
     * Set the minimum score gap between results.
     *
     * This can help identify when there's a clear quality drop-off
     * in the results, allowing you to cut off at that point.
     */
    public function setMinScoreGap(?float $minScoreGap): static
    {
        $this->minScoreGap = $minScoreGap;

        return $this;
    }

    /**
     * Filter results by score gap.
     *
     * Stops including results when the gap between consecutive
     * scores exceeds the minimum score gap threshold.
     *
     * @param  array<VectorSearchResult>  $results
     * @return array<VectorSearchResult>
     */
    protected function filterByScoreGap(array $results): array
    {
        if (empty($results)) {
            return [];
        }

        $filtered = [$results[0]];
        $previousScore = $results[0]->score;

        for ($i = 1; $i < count($results); $i++) {
            $currentScore = $results[$i]->score;
            $gap = $previousScore - $currentScore;

            // If gap exceeds threshold, stop including results
            if ($gap > $this->minScoreGap) {
                break;
            }

            $filtered[] = $results[$i];
            $previousScore = $currentScore;
        }

        return $filtered;
    }
}
