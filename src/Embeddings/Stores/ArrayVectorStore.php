<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Embeddings\Stores;

use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Embeddings\VectorDocument;
use AgenticOrchestrator\Embeddings\VectorSearchResult;

/**
 * Array Vector Store - In-memory vector store for testing and development.
 *
 * Not suitable for production with large datasets.
 */
class ArrayVectorStore implements VectorStoreInterface
{
    /**
     * Stored documents.
     *
     * @var array<string, VectorDocument>
     */
    protected array $documents = [];

    /**
     * Distance metric to use.
     */
    protected string $distanceMetric;

    /**
     * Create a new array vector store.
     *
     * @param  string  $distanceMetric  'cosine', 'euclidean', or 'dot'
     */
    public function __construct(string $distanceMetric = 'cosine')
    {
        $this->distanceMetric = $distanceMetric;
    }

    /**
     * Store a document with its embedding.
     *
     * @param  array<float>  $embedding
     * @param  array<string, mixed>  $metadata
     */
    public function upsert(
        string $id,
        array $embedding,
        string $content,
        array $metadata = [],
    ): void {
        $this->documents[$id] = new VectorDocument(
            id: $id,
            content: $content,
            embedding: $embedding,
            metadata: $metadata,
        );
    }

    /**
     * Store multiple documents.
     *
     * @param  array<VectorDocument>  $documents
     */
    public function upsertBatch(array $documents): void
    {
        foreach ($documents as $doc) {
            $this->documents[$doc->id] = $doc;
        }
    }

    /**
     * Search for similar documents.
     *
     * @param  array<float>  $embedding
     * @param  array<string, mixed>  $filter
     * @return array<VectorSearchResult>
     */
    public function search(
        array $embedding,
        int $limit = 10,
        array $filter = [],
    ): array {
        $results = [];

        foreach ($this->documents as $doc) {
            // Apply metadata filter
            if (! $this->matchesFilter($doc, $filter)) {
                continue;
            }

            // Calculate similarity
            $score = $this->calculateSimilarity($embedding, $doc->embedding);

            $results[] = new VectorSearchResult(
                document: $doc,
                score: $score,
                distance: $this->distanceMetric === 'cosine' ? 1 - $score : null,
            );
        }

        // Sort by score descending
        usort($results, fn ($a, $b) => $b->score <=> $a->score);

        // Return top results
        return array_slice($results, 0, $limit);
    }

    /**
     * Delete a document by ID.
     */
    public function delete(string $id): bool
    {
        if (isset($this->documents[$id])) {
            unset($this->documents[$id]);

            return true;
        }

        return false;
    }

    /**
     * Delete multiple documents by IDs.
     *
     * @param  array<string>  $ids
     */
    public function deleteBatch(array $ids): int
    {
        $count = 0;

        foreach ($ids as $id) {
            if ($this->delete($id)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Delete documents matching a filter.
     *
     * @param  array<string, mixed>  $filter
     */
    public function deleteByFilter(array $filter): int
    {
        $count = 0;

        foreach ($this->documents as $id => $doc) {
            if ($this->matchesFilter($doc, $filter)) {
                unset($this->documents[$id]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get a document by ID.
     */
    public function get(string $id): ?VectorDocument
    {
        return $this->documents[$id] ?? null;
    }

    /**
     * Check if a document exists.
     */
    public function exists(string $id): bool
    {
        return isset($this->documents[$id]);
    }

    /**
     * Get the total document count.
     */
    public function count(): int
    {
        return count($this->documents);
    }

    /**
     * Clear all documents.
     */
    public function clear(): void
    {
        $this->documents = [];
    }

    /**
     * Check if document matches filter.
     *
     * @param  array<string, mixed>  $filter
     */
    protected function matchesFilter(VectorDocument $doc, array $filter): bool
    {
        foreach ($filter as $key => $value) {
            $metaValue = $doc->getMeta($key);

            if (is_array($value)) {
                // Array means "in" operation
                if (! in_array($metaValue, $value, true)) {
                    return false;
                }
            } elseif ($metaValue !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate similarity between two embeddings.
     *
     * @param  array<float>  $a
     * @param  array<float>  $b
     */
    protected function calculateSimilarity(array $a, array $b): float
    {
        return match ($this->distanceMetric) {
            'cosine' => $this->cosineSimilarity($a, $b),
            'euclidean' => $this->euclideanSimilarity($a, $b),
            'dot' => $this->dotProduct($a, $b),
            default => $this->cosineSimilarity($a, $b),
        };
    }

    /**
     * Calculate cosine similarity.
     *
     * @param  array<float>  $a
     * @param  array<float>  $b
     */
    protected function cosineSimilarity(array $a, array $b): float
    {
        $dot = $this->dotProduct($a, $b);
        $normA = sqrt($this->dotProduct($a, $a));
        $normB = sqrt($this->dotProduct($b, $b));

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dot / ($normA * $normB);
    }

    /**
     * Calculate euclidean distance as similarity.
     *
     * @param  array<float>  $a
     * @param  array<float>  $b
     */
    protected function euclideanSimilarity(array $a, array $b): float
    {
        $sum = 0.0;

        foreach ($a as $i => $val) {
            $diff = $val - ($b[$i] ?? 0);
            $sum += $diff * $diff;
        }

        $distance = sqrt($sum);

        // Convert distance to similarity (inverse)
        return 1 / (1 + $distance);
    }

    /**
     * Calculate dot product.
     *
     * @param  array<float>  $a
     * @param  array<float>  $b
     */
    protected function dotProduct(array $a, array $b): float
    {
        $sum = 0.0;

        foreach ($a as $i => $val) {
            $sum += $val * ($b[$i] ?? 0);
        }

        return $sum;
    }

    /**
     * Get all documents (for testing/debugging).
     *
     * @return array<string, VectorDocument>
     */
    public function all(): array
    {
        return $this->documents;
    }
}
