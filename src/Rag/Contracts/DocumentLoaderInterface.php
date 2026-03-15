<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Rag\Contracts;

use AgenticOrchestrator\Rag\Document;

/**
 * Interface for document loaders.
 *
 * Document loaders are responsible for reading content from various sources
 * (files, URLs, databases) and converting them into Document objects.
 */
interface DocumentLoaderInterface
{
    /**
     * Load documents from a source.
     *
     * @param  string  $source  The source path or identifier
     * @return array<Document>
     */
    public function load(string $source): array;

    /**
     * Check if the loader supports a given source.
     *
     * @param  string  $source  The source path or identifier
     */
    public function supports(string $source): bool;
}
