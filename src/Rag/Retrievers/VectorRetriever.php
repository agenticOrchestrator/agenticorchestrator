<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Rag\Retrievers;

use AgenticOrchestrator\Embeddings\Contracts\EmbeddingProviderInterface;
use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Embeddings\VectorSearchResult;
use AgenticOrchestrator\Rag\Contracts\RetrieverInterface;

/**
 * VectorRetriever - Retrieves documents using vector similarity search.
 *
 * Uses embedding-based semantic search to find relevant documents.
 */
class VectorRetriever implements RetrieverInterface
{
    /**
     * The similarity threshold.
     */
    protected float $threshold = 0.7;

    /**
     * Create a new vector retriever.
     */
    public function __construct(
        protected EmbeddingProviderInterface $embeddings,
        protected VectorStoreInterface $store,
    ) {}

    /**
     * Retrieve relevant documents for a query.
     *
     * @param  string  $query  The search query
     * @param  int  $limit  Maximum number of results
     * @param  array<string, mixed>  $filter  Optional metadata filters
     * @return array<VectorSearchResult>
     */
    public function retrieve(string $query, int $limit = 5, array $filter = []): array
    {
        // Generate embedding for the query
        $embedding = $this->embeddings->embed($query);

        // Search the vector store
        $results = $this->store->search(
            embedding: $embedding,
            limit: $limit,
            filter: $filter,
        );

        // Filter by threshold
        return array_values(array_filter(
            $results,
            fn (VectorSearchResult $r) => $r->score >= $this->threshold
        ));
    }

    /**
     * Set the similarity threshold for filtering results.
     */
    public function setThreshold(float $threshold): static
    {
        $this->threshold = max(0.0, min(1.0, $threshold));

        return $this;
    }

    /**
     * Get the current similarity threshold.
     */
    public function getThreshold(): float
    {
        return $this->threshold;
    }
}
