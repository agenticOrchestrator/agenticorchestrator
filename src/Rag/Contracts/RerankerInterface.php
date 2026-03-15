<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Rag\Contracts;

use AgenticOrchestrator\Embeddings\VectorSearchResult;

/**
 * Interface for result rerankers.
 *
 * Rerankers post-process search results to improve relevance,
 * filter by score thresholds, or apply additional ranking criteria.
 */
interface RerankerInterface
{
    /**
     * Rerank and filter search results.
     *
     * @param  array<VectorSearchResult>  $results  The results to rerank
     * @param  string  $query  The original query for context
     * @return array<VectorSearchResult> Reranked results
     */
    public function rerank(array $results, string $query): array;
}
