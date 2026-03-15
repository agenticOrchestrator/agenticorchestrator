<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Rag\Contracts;

use AgenticOrchestrator\Embeddings\VectorSearchResult;

/**
 * Interface for document retrievers.
 *
 * Retrievers handle the search and retrieval of relevant documents
 * based on a query, using various strategies like vector similarity,
 * keyword search, or hybrid approaches.
 */
interface RetrieverInterface
{
    /**
     * Retrieve relevant documents for a query.
     *
     * @param  string  $query  The search query
     * @param  int  $limit  Maximum number of results
     * @param  array<string, mixed>  $filter  Optional metadata filters
     * @return array<VectorSearchResult>
     */
    public function retrieve(string $query, int $limit = 5, array $filter = []): array;

    /**
     * Set the similarity threshold for filtering results.
     */
    public function setThreshold(float $threshold): static;

    /**
     * Get the current similarity threshold.
     */
    public function getThreshold(): float;
}
