<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Memory\Drivers;

use AgenticOrchestrator\Embeddings\Contracts\EmbeddingProviderInterface;
use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Embeddings\VectorDocument;
use AgenticOrchestrator\Embeddings\VectorSearchResult;
use Illuminate\Support\Str;

/**
 * Vector Memory Driver - Semantic memory using embeddings.
 *
 * Stores memories as vector embeddings for semantic search
 * and retrieval based on meaning rather than keywords.
 *
 * Note: This driver provides vector-specific operations and does not
 * implement the full MemoryInterface. Use it for semantic search
 * capabilities alongside a primary memory driver.
 */
class VectorMemoryDriver
{
    /**
     * Namespace for scoping memories.
     */
    protected string $namespace = 'default';

    /**
     * Create a new vector memory driver.
     */
    public function __construct(
        protected EmbeddingProviderInterface $embeddings,
        protected VectorStoreInterface $store,
    ) {}

    /**
     * Store a value in memory.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $content = $this->serializeValue($value);
        $embedding = $this->embeddings->embed($content);

        $this->store->upsert(
            id: $this->prefixKey($key),
            embedding: $embedding,
            content: $content,
            metadata: [
                'key' => $key,
                'namespace' => $this->namespace,
                'type' => gettype($value),
                'created_at' => now()->toISOString(),
                'expires_at' => $ttl ? now()->addSeconds($ttl)->toISOString() : null,
            ],
        );
    }

    /**
     * Get a value from memory.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $doc = $this->store->get($this->prefixKey($key));

        if ($doc === null) {
            return $default;
        }

        // Check expiration
        $expiresAt = $doc->getMeta('expires_at');
        if ($expiresAt !== null && now()->isAfter($expiresAt)) {
            $this->forget($key);

            return $default;
        }

        return $this->deserializeValue($doc->content, $doc->getMeta('type', 'string'));
    }

    /**
     * Check if a key exists in memory.
     */
    public function has(string $key): bool
    {
        return $this->store->exists($this->prefixKey($key));
    }

    /**
     * Remove a key from memory.
     */
    public function forget(string $key): bool
    {
        return $this->store->delete($this->prefixKey($key));
    }

    /**
     * Clear all memory in the current namespace.
     */
    public function flush(): void
    {
        $this->store->deleteByFilter(['namespace' => $this->namespace]);
    }

    /**
     * Get all keys in the current namespace.
     *
     * @return array<string>
     */
    public function keys(): array
    {
        // This is not efficient for large datasets
        // Consider implementing a separate key index
        return [];
    }

    /**
     * Search memories semantically.
     *
     * @param  string  $query  The search query
     * @param  int  $limit  Maximum results
     * @param  float  $threshold  Minimum similarity threshold (0-1)
     * @return array<VectorSearchResult>
     */
    public function search(string $query, int $limit = 5, float $threshold = 0.7): array
    {
        $embedding = $this->embeddings->embed($query);

        $results = $this->store->search(
            embedding: $embedding,
            limit: $limit,
            filter: ['namespace' => $this->namespace],
        );

        // Filter by threshold
        return array_filter(
            $results,
            fn (VectorSearchResult $r) => $r->score >= $threshold
        );
    }

    /**
     * Add a memory with auto-generated key.
     *
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function remember(string $content, array $metadata = []): string
    {
        $key = 'mem_'.Str::random(16);
        $embedding = $this->embeddings->embed($content);

        $this->store->upsert(
            id: $this->prefixKey($key),
            embedding: $embedding,
            content: $content,
            metadata: array_merge($metadata, [
                'key' => $key,
                'namespace' => $this->namespace,
                'type' => 'memory',
                'created_at' => now()->toISOString(),
            ]),
        );

        return $key;
    }

    /**
     * Store multiple memories in batch.
     *
     * @param  array<string, mixed>  $memories  Key => value pairs
     */
    public function setMany(array $memories): void
    {
        $documents = [];

        foreach ($memories as $key => $value) {
            $content = $this->serializeValue($value);
            $embedding = $this->embeddings->embed($content);

            $documents[] = new VectorDocument(
                id: $this->prefixKey($key),
                content: $content,
                embedding: $embedding,
                metadata: [
                    'key' => $key,
                    'namespace' => $this->namespace,
                    'type' => gettype($value),
                    'created_at' => now()->toISOString(),
                ],
            );
        }

        $this->store->upsertBatch($documents);
    }

    /**
     * Get multiple memories.
     *
     * @param  array<string>  $keys
     * @return array<string, mixed>
     */
    public function getMany(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

    /**
     * Get similar memories to a reference key.
     *
     * @param  string  $key  The reference key
     * @param  int  $limit  Maximum results
     * @return array<VectorSearchResult>
     */
    public function similar(string $key, int $limit = 5): array
    {
        $doc = $this->store->get($this->prefixKey($key));

        if ($doc === null || empty($doc->embedding)) {
            return [];
        }

        $results = $this->store->search(
            embedding: $doc->embedding,
            limit: $limit + 1, // +1 to exclude self
            filter: ['namespace' => $this->namespace],
        );

        // Remove the source document from results
        return array_filter(
            $results,
            fn (VectorSearchResult $r) => $r->getId() !== $this->prefixKey($key)
        );
    }

    /**
     * Set the namespace for memory scoping.
     */
    public function namespace(string $namespace): static
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * Get the current namespace.
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * Prefix a key with the namespace.
     */
    protected function prefixKey(string $key): string
    {
        return "{$this->namespace}:{$key}";
    }

    /**
     * Serialize a value for storage.
     */
    protected function serializeValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return json_encode($value);
    }

    /**
     * Deserialize a value from storage.
     */
    protected function deserializeValue(string $content, string $type): mixed
    {
        return match ($type) {
            'string' => $content,
            'integer' => (int) $content,
            'double' => (float) $content,
            'boolean' => $content === 'true' || $content === '1',
            'array', 'object' => json_decode($content, true),
            default => $content,
        };
    }
}
