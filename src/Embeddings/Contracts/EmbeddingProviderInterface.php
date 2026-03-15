<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Embeddings\Contracts;

/**
 * Interface for embedding providers.
 *
 * Embedding providers convert text into vector representations
 * for semantic search and similarity matching.
 */
interface EmbeddingProviderInterface
{
    /**
     * Generate embeddings for a single text.
     *
     * @param  string  $text  The text to embed
     * @return array<float> The embedding vector
     */
    public function embed(string $text): array;

    /**
     * Generate embeddings for multiple texts.
     *
     * @param  array<string>  $texts  The texts to embed
     * @return array<int, array<float>> Array of embedding vectors
     */
    public function embedBatch(array $texts): array;

    /**
     * Get the dimension of the embeddings.
     */
    public function getDimension(): int;

    /**
     * Get the model name/identifier.
     */
    public function getModel(): string;

    /**
     * Get the maximum input length (tokens or characters).
     */
    public function getMaxInputLength(): int;
}
