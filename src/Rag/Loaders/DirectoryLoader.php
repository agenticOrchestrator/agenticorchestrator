<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Rag\Loaders;

use AgenticOrchestrator\Rag\Contracts\DocumentLoaderInterface;
use AgenticOrchestrator\Rag\Document;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * DirectoryLoader - Loads documents from a directory recursively.
 *
 * Auto-detects file types and uses appropriate loaders.
 * Supports filtering by file extension and exclusion patterns.
 */
class DirectoryLoader implements DocumentLoaderInterface
{
    /**
     * File extension to loader mapping.
     *
     * @var array<string, DocumentLoaderInterface>
     */
    protected array $loaders = [];

    /**
     * File extensions to include (null = all supported).
     *
     * @var array<string>|null
     */
    protected ?array $includeExtensions = null;

    /**
     * Patterns to exclude.
     *
     * @var array<string>
     */
    protected array $excludePatterns = [
        '**/node_modules/**',
        '**/vendor/**',
        '**/.git/**',
        '**/.*',
    ];

    /**
     * Whether to recurse into subdirectories.
     */
    protected bool $recursive = true;

    /**
     * Create a new directory loader.
     */
    public function __construct()
    {
        // Register default loaders
        $this->loaders = [
            'txt' => new TextLoader,
            'text' => new TextLoader,
            'log' => new TextLoader,
            'md' => new MarkdownLoader,
            'markdown' => new MarkdownLoader,
            'json' => new JsonLoader,
            'jsonl' => new JsonLoader,
        ];
    }

    /**
     * Load documents from a directory.
     *
     * @param  string  $source  The directory path
     * @return array<Document>
     */
    public function load(string $source): array
    {
        if (! is_dir($source)) {
            throw new \InvalidArgumentException("Directory not found: {$source}");
        }

        $documents = [];

        $iterator = $this->recursive
            ? new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            )
            : new \DirectoryIterator($source);

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $filePath = $file->getPathname();

            // Check exclusion patterns
            if ($this->isExcluded($filePath)) {
                continue;
            }

            // Check extension filter
            $extension = strtolower($file->getExtension());

            if ($this->includeExtensions !== null && ! in_array($extension, $this->includeExtensions, true)) {
                continue;
            }

            // Get appropriate loader
            $loader = $this->loaders[$extension] ?? null;

            if ($loader === null) {
                continue;
            }

            try {
                $loadedDocs = $loader->load($filePath);
                $documents = array_merge($documents, $loadedDocs);
            } catch (\Throwable) {
                // Skip files that fail to load
                continue;
            }
        }

        return $documents;
    }

    /**
     * Check if the loader supports a given source.
     */
    public function supports(string $source): bool
    {
        return is_dir($source);
    }

    /**
     * Register a loader for specific extensions.
     *
     * @param  array<string>  $extensions
     */
    public function registerLoader(array $extensions, DocumentLoaderInterface $loader): static
    {
        foreach ($extensions as $ext) {
            $this->loaders[strtolower($ext)] = $loader;
        }

        return $this;
    }

    /**
     * Set extensions to include.
     *
     * @param  array<string>  $extensions
     */
    public function includeExtensions(array $extensions): static
    {
        $this->includeExtensions = array_map('strtolower', $extensions);

        return $this;
    }

    /**
     * Add exclusion patterns.
     *
     * @param  array<string>  $patterns
     */
    public function exclude(array $patterns): static
    {
        $this->excludePatterns = array_merge($this->excludePatterns, $patterns);

        return $this;
    }

    /**
     * Set whether to recurse into subdirectories.
     */
    public function recursive(bool $recursive = true): static
    {
        $this->recursive = $recursive;

        return $this;
    }

    /**
     * Check if a file path matches any exclusion pattern.
     */
    protected function isExcluded(string $path): bool
    {
        $normalizedPath = str_replace('\\', '/', $path);

        foreach ($this->excludePatterns as $pattern) {
            if ($this->matchesGlob($normalizedPath, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Simple glob pattern matching.
     */
    protected function matchesGlob(string $path, string $pattern): bool
    {
        // Convert glob to regex
        $regex = str_replace(
            ['**/', '*', '?', '.'],
            ['.*', '[^/]*', '.', '\\.'],
            $pattern
        );

        return (bool) preg_match('#'.$regex.'#', $path);
    }
}
