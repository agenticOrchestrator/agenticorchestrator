<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Rag\Chunking;

use AgenticOrchestrator\Rag\Contracts\ChunkingStrategyInterface;
use AgenticOrchestrator\Rag\Document;

/**
 * RecursiveCharacterChunker - Splits documents using recursive separators.
 *
 * Attempts to split text using a hierarchy of separators (paragraphs,
 * sentences, words) to create more semantically coherent chunks.
 */
class RecursiveCharacterChunker implements ChunkingStrategyInterface
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
     * The separators to use for splitting, in order of preference.
     *
     * @var array<string>
     */
    protected array $separators = [
        "\n\n",      // Double newline (paragraphs)
        "\n",        // Single newline
        '. ',        // Sentence ending
        '? ',        // Question ending
        '! ',        // Exclamation ending
        '; ',        // Semicolon
        ', ',        // Comma
        ' ',         // Space (words)
        '',          // Character by character (last resort)
    ];

    /**
     * Whether to keep the separator with the chunk.
     */
    protected bool $keepSeparator = true;

    /**
     * Create a new recursive character chunker.
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

        if (mb_strlen($content) <= $this->chunkSize) {
            return [$document];
        }

        $textChunks = $this->splitText($content, $this->separators);
        $documents = [];

        foreach ($textChunks as $index => $chunkData) {
            if (trim($chunkData['content']) === '') {
                continue;
            }

            $documents[] = $document->createChunk(
                content: trim($chunkData['content']),
                chunkIndex: $index,
                startOffset: $chunkData['offset'],
            );
        }

        return $documents;
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
     * Set custom separators.
     *
     * @param  array<string>  $separators
     */
    public function setSeparators(array $separators): static
    {
        $this->separators = $separators;

        return $this;
    }

    /**
     * Recursively split text using separators.
     *
     * @param  array<string>  $separators
     * @return array<array{content: string, offset: int}>
     */
    protected function splitText(string $text, array $separators): array
    {
        $separator = $separators[0] ?? '';
        $remainingSeparators = array_slice($separators, 1);

        // Split by current separator
        if ($separator === '') {
            // Last resort: character by character
            $splits = mb_str_split($text);
        } else {
            $splits = $this->splitWithSeparator($text, $separator);
        }

        // Merge small splits and track offsets
        $chunks = [];
        $currentChunk = '';
        $currentOffset = 0;
        $textOffset = 0;

        foreach ($splits as $split) {
            $splitLen = mb_strlen($split);

            // If adding this split would exceed chunk size
            if (mb_strlen($currentChunk) + $splitLen > $this->chunkSize) {
                // If current chunk is not empty, save it
                if ($currentChunk !== '') {
                    // Check if current chunk is too large and needs recursive splitting
                    if (mb_strlen($currentChunk) > $this->chunkSize && ! empty($remainingSeparators)) {
                        $subChunks = $this->splitText($currentChunk, $remainingSeparators);
                        foreach ($subChunks as $subChunk) {
                            $chunks[] = [
                                'content' => $subChunk['content'],
                                'offset' => $currentOffset + $subChunk['offset'],
                            ];
                        }
                    } else {
                        $chunks[] = ['content' => $currentChunk, 'offset' => $currentOffset];
                    }

                    // Handle overlap: take last portion of current chunk
                    $overlapText = $this->getOverlapText($currentChunk);
                    $currentChunk = $overlapText;
                    $currentOffset = $textOffset - mb_strlen($overlapText);
                }

                // If split itself is too large, recurse
                if ($splitLen > $this->chunkSize && ! empty($remainingSeparators)) {
                    $subChunks = $this->splitText($split, $remainingSeparators);
                    foreach ($subChunks as $subChunk) {
                        $chunks[] = [
                            'content' => $subChunk['content'],
                            'offset' => $textOffset + $subChunk['offset'],
                        ];
                    }
                    $textOffset += $splitLen;

                    continue;
                }
            }

            // Add to current chunk
            if ($currentChunk === '') {
                $currentOffset = $textOffset;
            }
            $currentChunk .= $split;
            $textOffset += $splitLen;
        }

        // Don't forget the last chunk
        if ($currentChunk !== '') {
            $chunks[] = ['content' => $currentChunk, 'offset' => $currentOffset];
        }

        return $chunks;
    }

    /**
     * Split text by separator, optionally keeping the separator.
     *
     * @return array<string>
     */
    protected function splitWithSeparator(string $text, string $separator): array
    {
        $parts = explode($separator, $text);
        $result = [];

        foreach ($parts as $i => $part) {
            if ($i < count($parts) - 1) {
                // Add separator back to the end of each part (except last)
                $result[] = $this->keepSeparator ? $part.$separator : $part;
            } else {
                $result[] = $part;
            }
        }

        return $result;
    }

    /**
     * Get the overlap text from the end of a chunk.
     */
    protected function getOverlapText(string $text): string
    {
        if ($this->overlap <= 0) {
            return '';
        }

        $len = mb_strlen($text);

        if ($len <= $this->overlap) {
            return $text;
        }

        return mb_substr($text, $len - $this->overlap);
    }
}
