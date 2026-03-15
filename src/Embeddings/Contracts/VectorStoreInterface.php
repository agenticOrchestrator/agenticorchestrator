<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Embeddings\Contracts;

use AgenticOrchestrator\Embeddings\VectorDocument;
use AgenticOrchestrator\Embeddings\VectorSearchResult;

/**
 * Interface for vector stores.
 *
 * Vector stores persist embeddings and enable similarity search
 * for semantic retrieval operations.
 */
interface VectorStoreInterface
{
    /**
     * Store a document with its embedding.
     *
     * @param  string  $id  Unique document identifier
     * @param  array<float>  $embedding  The embedding vector
     * @param  string  $content  The original content
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function upsert(
        string $id,
        array $embedding,
        string $content,
        array $metadata = [],
    ): void;

    /**
     * Store multiple documents.
     *
     * @param  array<VectorDocument>  $documents
     */
    public function upsertBatch(array $documents): void;

    /**
     * Search for similar documents.
     *
     * @param  array<float>  $embedding  Query embedding
     * @param  int  $limit  Maximum results to return
     * @param  array<string, mixed>  $filter  Metadata filters
     * @return array<VectorSearchResult>
     */
    public function search(
        array $embedding,
        int $limit = 10,
        array $filter = [],
    ): array;

    /**
     * Delete a document by ID.
     */
    public function delete(string $id): bool;

    /**
     * Delete multiple documents by IDs.
     *
     * @param  array<string>  $ids
     */
    public function deleteBatch(array $ids): int;

    /**
     * Delete documents matching a filter.
     *
     * @param  array<string, mixed>  $filter
     */
    public function deleteByFilter(array $filter): int;

    /**
     * Get a document by ID.
     */
    public function get(string $id): ?VectorDocument;

    /**
     * Check if a document exists.
     */
    public function exists(string $id): bool;

    /**
     * Get the total document count.
     */
    public function count(): int;

    /**
     * Clear all documents (use with caution).
     */
    public function clear(): void;
}
