<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Rag\Chunking;

use AgenticOrchestrator\Rag\Contracts\ChunkingStrategyInterface;
use AgenticOrchestrator\Rag\Document;

/**
 * FixedSizeChunker - Splits documents into fixed-size chunks.
 *
 * Simple chunking strategy that splits text into chunks of a fixed
 * character count with configurable overlap between chunks.
 */
class FixedSizeChunker implements ChunkingStrategyInterface
{
    /**
     * The chunk size in characters.
     */
    protected int $chunkSize = 1000;

    /**
     * The overlap between chunks in characters.
     */
    protected int $overlap = 200;

    /**
     * Whether to trim whitespace from chunks.
     */
    protected bool $trimChunks = true;

    /**
     * Create a new fixed size chunker.
     */
    public function __construct(
        int $chunkSize = 1000,
        int $overlap = 200,
    ) {
        $this->chunkSize = $chunkSize;
        $this->overlap = $overlap;
    }

    /**
     * Split a document into chunks.
     *
     * @return array<Document>
     */
    public function chunk(Document $document): array
    {
        $content = $document->content;
        $length = mb_strlen($content);

        if ($length <= $this->chunkSize) {
            return [$document];
        }

        $chunks = [];
        $position = 0;
        $chunkIndex = 0;

        while ($position < $length) {
            // Calculate chunk end
            $end = min($position + $this->chunkSize, $length);

            // Extract chunk
            $chunkContent = mb_substr($content, $position, $end - $position);

            if ($this->trimChunks) {
                $chunkContent = trim($chunkContent);
            }

            // Only add non-empty chunks
            if ($chunkContent !== '') {
                $chunks[] = $document->createChunk(
                    content: $chunkContent,
                    chunkIndex: $chunkIndex,
                    startOffset: $position,
                );
                $chunkIndex++;
            }

            // Move position, ensuring we always advance
            $step = $this->chunkSize - $this->overlap;
            if ($step <= 0) {
                $step = $this->chunkSize;
            }
            $position += $step;
        }

        return $chunks;
    }

    /**
     * Split multiple documents into chunks.
     *
     * @param  array<Document>  $documents
     * @return array<Document>
     */
    public function chunkAll(array $documents): array
    {
        $chunks = [];

        foreach ($documents as $document) {
            $docChunks = $this->chunk($document);
            $chunks = array_merge($chunks, $docChunks);
        }

        return $chunks;
    }

    /**
     * Set the chunk size.
     */
    public function setChunkSize(int $size): static
    {
        $this->chunkSize = max(1, $size);

        return $this;
    }

    /**
     * Set the chunk overlap.
     */
    public function setOverlap(int $overlap): static
    {
        $this->overlap = max(0, $overlap);

        return $this;
    }

    /**
     * Set whether to trim chunks.
     */
    public function trimChunks(bool $trim = true): static
    {
        $this->trimChunks = $trim;

        return $this;
    }
}
