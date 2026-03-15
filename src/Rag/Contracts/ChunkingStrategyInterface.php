<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Rag\Contracts;

use AgenticOrchestrator\Rag\Document;

/**
 * Interface for text chunking strategies.
 *
 * Chunking strategies break down documents into smaller pieces
 * suitable for embedding and retrieval.
 */
interface ChunkingStrategyInterface
{
    /**
     * Split a document into chunks.
     *
     * @param  Document  $document  The document to chunk
     * @return array<Document> Array of chunked documents
     */
    public function chunk(Document $document): array;

    /**
     * Split multiple documents into chunks.
     *
     * @param  array<Document>  $documents
     * @return array<Document>
     */
    public function chunkAll(array $documents): array;

    /**
     * Set the chunk size.
     */
    public function setChunkSize(int $size): static;

    /**
     * Set the chunk overlap.
     */
    public function setOverlap(int $overlap): static;
}
