<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Rag\Loaders;

use AgenticOrchestrator\Rag\Contracts\DocumentLoaderInterface;
use AgenticOrchestrator\Rag\Document;

/**
 * TextLoader - Loads plain text files.
 *
 * Supports .txt files and any other plain text format.
 */
class TextLoader implements DocumentLoaderInterface
{
    /**
     * Supported file extensions.
     *
     * @var array<string>
     */
    protected array $extensions = ['txt', 'text', 'log'];

    /**
     * Create a new text loader.
     *
     * @param  string|null  $encoding  Text encoding (default: UTF-8)
     */
    public function __construct(
        protected ?string $encoding = 'UTF-8',
    ) {}

    /**
     * Load documents from a text file.
     *
     * @param  string  $source  The file path
     * @return array<Document>
     */
    public function load(string $source): array
    {
        if (! file_exists($source)) {
            throw new \InvalidArgumentException("File not found: {$source}");
        }

        if (! is_readable($source)) {
            throw new \InvalidArgumentException("File not readable: {$source}");
        }

        $content = file_get_contents($source);

        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$source}");
        }

        // Handle encoding if needed
        if ($this->encoding !== null && $this->encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $this->encoding);
        }

        return [
            Document::fromFile($source, $content, [
                'loader' => 'text',
                'encoding' => $this->encoding,
                'size_bytes' => filesize($source),
                'modified_at' => date('c', filemtime($source)),
            ]),
        ];
    }

    /**
     * Check if the loader supports a given source.
     */
    public function supports(string $source): bool
    {
        if (! file_exists($source) || ! is_file($source)) {
            return false;
        }

        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));

        return in_array($extension, $this->extensions, true);
    }

    /**
     * Add supported extensions.
     *
     * @param  array<string>  $extensions
     */
    public function addExtensions(array $extensions): static
    {
        $this->extensions = array_merge($this->extensions, $extensions);

        return $this;
    }
}
