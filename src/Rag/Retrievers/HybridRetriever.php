<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Rag\Retrievers;

use AgenticOrchestrator\Embeddings\Contracts\EmbeddingProviderInterface;
use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Embeddings\VectorSearchResult;
use AgenticOrchestrator\Rag\Contracts\RetrieverInterface;

/**
 * HybridRetriever - Combines vector and keyword search.
 *
 * Uses both embedding-based semantic search and keyword matching
 * to improve retrieval quality, especially for specific terms.
 */
class HybridRetriever implements RetrieverInterface
{
    /**
     * The similarity threshold.
     */
    protected float $threshold = 0.7;

    /**
     * Weight for vector search results (0-1).
     */
    protected float $vectorWeight = 0.7;

    /**
     * Weight for keyword search results (0-1).
     */
    protected float $keywordWeight = 0.3;

    /**
     * Create a new hybrid retriever.
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
        // Get vector search results
        $embedding = $this->embeddings->embed($query);
        $vectorResults = $this->store->search(
            embedding: $embedding,
            limit: $limit * 2, // Get more to allow for reranking
            filter: $filter,
        );

        // Extract keywords from query
        $keywords = $this->extractKeywords($query);

        // Score and combine results
        $scoredResults = [];

        foreach ($vectorResults as $result) {
            $vectorScore = $result->score * $this->vectorWeight;
            $keywordScore = $this->calculateKeywordScore($result->getContent(), $keywords) * $this->keywordWeight;
            $combinedScore = $vectorScore + $keywordScore;

            // Create new result with combined score
            $scoredResults[] = new VectorSearchResult(
                document: $result->document,
                score: $combinedScore,
                distance: $result->distance,
            );
        }

        // Sort by combined score
        usort($scoredResults, fn ($a, $b) => $b->score <=> $a->score);

        // Filter by threshold and limit
        $filteredResults = array_filter(
            $scoredResults,
            fn (VectorSearchResult $r) => $r->score >= $this->threshold
        );

        return array_slice(array_values($filteredResults), 0, $limit);
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

    /**
     * Set the weight for vector search results.
     */
    public function setVectorWeight(float $weight): static
    {
        $this->vectorWeight = max(0.0, min(1.0, $weight));
        $this->keywordWeight = 1.0 - $this->vectorWeight;

        return $this;
    }

    /**
     * Set the weight for keyword search results.
     */
    public function setKeywordWeight(float $weight): static
    {
        $this->keywordWeight = max(0.0, min(1.0, $weight));
        $this->vectorWeight = 1.0 - $this->keywordWeight;

        return $this;
    }

    /**
     * Extract keywords from query.
     *
     * @return array<string>
     */
    protected function extractKeywords(string $query): array
    {
        // Remove common stop words and extract significant terms
        $stopWords = [
            'a', 'an', 'the', 'and', 'or', 'but', 'is', 'are', 'was', 'were',
            'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did',
            'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can',
            'to', 'of', 'in', 'for', 'on', 'with', 'at', 'by', 'from', 'as',
            'into', 'through', 'during', 'before', 'after', 'above', 'below',
            'between', 'under', 'again', 'further', 'then', 'once', 'here',
            'there', 'when', 'where', 'why', 'how', 'all', 'each', 'few',
            'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not',
            'only', 'own', 'same', 'so', 'than', 'too', 'very', 'just',
            'what', 'which', 'who', 'whom', 'this', 'that', 'these', 'those',
            'am', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him',
            'her', 'us', 'them', 'my', 'your', 'his', 'its', 'our', 'their',
        ];

        // Tokenize and filter
        $words = preg_split('/\W+/', strtolower($query), -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false) {
            return [];
        }

        return array_values(array_filter(
            $words,
            fn ($word) => strlen($word) > 2 && ! in_array($word, $stopWords, true)
        ));
    }

    /**
     * Calculate keyword match score for content.
     *
     * @param  array<string>  $keywords
     */
    protected function calculateKeywordScore(string $content, array $keywords): float
    {
        if (empty($keywords)) {
            return 0.0;
        }

        $contentLower = strtolower($content);
        $matches = 0;
        $totalWeight = 0;

        foreach ($keywords as $keyword) {
            // Count occurrences
            $count = substr_count($contentLower, $keyword);

            if ($count > 0) {
                // Weight by keyword length (longer = more specific)
                $weight = min(strlen($keyword) / 10, 1.0);
                $matches += min($count, 3) * $weight; // Cap at 3 occurrences
                $totalWeight += $weight;
            } else {
                $totalWeight += strlen($keyword) / 10;
            }
        }

        if ($totalWeight === 0) {
            return 0.0;
        }

        // Normalize to 0-1 range
        return min($matches / $totalWeight, 1.0);
    }
}
