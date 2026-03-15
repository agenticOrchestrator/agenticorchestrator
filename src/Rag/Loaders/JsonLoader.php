<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Rag\Loaders;

use AgenticOrchestrator\Rag\Contracts\DocumentLoaderInterface;
use AgenticOrchestrator\Rag\Document;

/**
 * JsonLoader - Loads JSON and JSONL files.
 *
 * Supports both regular JSON arrays and JSONL (newline-delimited JSON).
 * Can extract content from specific fields.
 */
class JsonLoader implements DocumentLoaderInterface
{
    /**
     * Supported file extensions.
     *
     * @var array<string>
     */
    protected array $extensions = ['json', 'jsonl', 'ndjson'];

    /**
     * The field to extract content from.
     */
    protected string $contentField = 'content';

    /**
     * Fields to include in metadata.
     *
     * @var array<string>|null
     */
    protected ?array $metadataFields = null;

    /**
     * The field to use as document ID.
     */
    protected ?string $idField = 'id';

    /**
     * Create a new JSON loader.
     */
    public function __construct(
        string $contentField = 'content',
        ?string $idField = 'id',
    ) {
        $this->contentField = $contentField;
        $this->idField = $idField;
    }

    /**
     * Load documents from a JSON or JSONL file.
     *
     * @param  string  $source  The file path
     * @return array<Document>
     */
    public function load(string $source): array
    {
        if (! file_exists($source)) {
            throw new \InvalidArgumentException("File not found: {$source}");
        }

        $content = file_get_contents($source);

        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$source}");
        }

        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));

        if ($extension === 'jsonl' || $extension === 'ndjson') {
            return $this->loadJsonl($source, $content);
        }

        return $this->loadJson($source, $content);
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
     * Set the content field name.
     */
    public function contentField(string $field): static
    {
        $this->contentField = $field;

        return $this;
    }

    /**
     * Set the ID field name.
     */
    public function idField(?string $field): static
    {
        $this->idField = $field;

        return $this;
    }

    /**
     * Set metadata fields to extract.
     *
     * @param  array<string>  $fields
     */
    public function metadataFields(array $fields): static
    {
        $this->metadataFields = $fields;

        return $this;
    }

    /**
     * Load regular JSON file.
     *
     * @return array<Document>
     */
    protected function loadJson(string $source, string $content): array
    {
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON: '.json_last_error_msg());
        }

        // Handle both array of objects and single object
        if (! is_array($data)) {
            throw new \RuntimeException('JSON must be an array or object');
        }

        // If it's an associative array (object), treat as single document
        if (array_keys($data) !== range(0, count($data) - 1)) {
            return [$this->createDocumentFromData($source, $data, 0)];
        }

        // Array of objects
        $documents = [];
        foreach ($data as $index => $item) {
            if (is_array($item)) {
                $documents[] = $this->createDocumentFromData($source, $item, $index);
            }
        }

        return $documents;
    }

    /**
     * Load JSONL file.
     *
     * @return array<Document>
     */
    protected function loadJsonl(string $source, string $content): array
    {
        $documents = [];
        $lines = explode("\n", $content);

        foreach ($lines as $index => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $data = json_decode($line, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Skip invalid lines
                continue;
            }

            if (is_array($data)) {
                $documents[] = $this->createDocumentFromData($source, $data, $index);
            }
        }

        return $documents;
    }

    /**
     * Create a document from JSON data.
     *
     * @param  array<string, mixed>  $data
     */
    protected function createDocumentFromData(string $source, array $data, int $index): Document
    {
        // Extract content
        $content = $this->extractContent($data);

        // Extract ID
        $id = null;
        if ($this->idField !== null && isset($data[$this->idField])) {
            $id = (string) $data[$this->idField];
        }

        // Extract metadata
        $metadata = $this->extractMetadata($data);
        $metadata['loader'] = 'json';
        $metadata['index'] = $index;

        $document = Document::fromFile($source, $content, $metadata);

        if ($id !== null) {
            $document = $document->withId($id);
        } else {
            $document = $document->withId(Document::generateId($source.'_'.$index));
        }

        return $document;
    }

    /**
     * Extract content from data.
     *
     * @param  array<string, mixed>  $data
     */
    protected function extractContent(array $data): string
    {
        // Try the configured content field
        if (isset($data[$this->contentField])) {
            $content = $data[$this->contentField];

            return is_string($content) ? $content : json_encode($content);
        }

        // Common field names to try
        $commonFields = ['text', 'body', 'message', 'description', 'value'];

        foreach ($commonFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                return $data[$field];
            }
        }

        // Fall back to JSON encoding the entire object
        return json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Extract metadata from data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractMetadata(array $data): array
    {
        if ($this->metadataFields !== null) {
            $metadata = [];

            foreach ($this->metadataFields as $field) {
                if (isset($data[$field])) {
                    $metadata[$field] = $data[$field];
                }
            }

            return $metadata;
        }

        // Exclude content and ID fields from metadata
        $exclude = [$this->contentField];

        if ($this->idField !== null) {
            $exclude[] = $this->idField;
        }

        return array_diff_key($data, array_flip($exclude));
    }
}
