<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Embeddings\Stores;

use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Embeddings\VectorDocument;
use AgenticOrchestrator\Embeddings\VectorSearchResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Chroma Vector Store - Vector storage using ChromaDB.
 *
 * @see https://docs.trychroma.com/
 */
class ChromaVectorStore implements VectorStoreInterface
{
    protected string $host;

    protected string $collection;

    protected ?string $collectionId = null;

    protected int $timeout = 30;

    protected ?string $tenant = null;

    protected ?string $database = null;

    /**
     * Create a new Chroma vector store.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->host = rtrim($config['host'] ?? 'http://localhost:8000', '/');
        $this->collection = $config['collection'] ?? 'documents';
        $this->timeout = $config['timeout'] ?? 30;
        $this->tenant = $config['tenant'] ?? 'default_tenant';
        $this->database = $config['database'] ?? 'default_database';
    }

    /**
     * Create a new instance.
     *
     * @param  array<string, mixed>  $config
     */
    public static function make(array $config = []): static
    {
        return new static($config);
    }

    /**
     * Store a document with its embedding.
     *
     * @param  array<float>  $embedding
     * @param  array<string, mixed>  $metadata
     */
    public function upsert(
        string $id,
        array $embedding,
        string $content,
        array $metadata = [],
    ): void {
        $this->ensureCollection();

        $this->request()->post("/api/v1/collections/{$this->collectionId}/upsert", [
            'ids' => [$id],
            'embeddings' => [$embedding],
            'documents' => [$content],
            'metadatas' => [$metadata],
        ]);
    }

    /**
     * Store multiple documents.
     *
     * @param  array<VectorDocument>  $documents
     */
    public function upsertBatch(array $documents): void
    {
        $this->ensureCollection();

        $ids = [];
        $embeddings = [];
        $contents = [];
        $metadatas = [];

        foreach ($documents as $doc) {
            $ids[] = $doc->id;
            $embeddings[] = $doc->embedding;
            $contents[] = $doc->content;
            $metadatas[] = $doc->metadata;
        }

        $this->request()->post("/api/v1/collections/{$this->collectionId}/upsert", [
            'ids' => $ids,
            'embeddings' => $embeddings,
            'documents' => $contents,
            'metadatas' => $metadatas,
        ]);
    }

    /**
     * Search for similar documents.
     *
     * @param  array<float>  $embedding
     * @param  array<string, mixed>  $filter
     * @return array<VectorSearchResult>
     */
    public function search(
        array $embedding,
        int $limit = 10,
        array $filter = [],
    ): array {
        $this->ensureCollection();

        $body = [
            'query_embeddings' => [$embedding],
            'n_results' => $limit,
            'include' => ['documents', 'metadatas', 'distances'],
        ];

        if (! empty($filter)) {
            $body['where'] = $filter;
        }

        $response = $this->request()->post(
            "/api/v1/collections/{$this->collectionId}/query",
            $body
        );

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json();
        $ids = $data['ids'][0] ?? [];
        $documents = $data['documents'][0] ?? [];
        $metadatas = $data['metadatas'][0] ?? [];
        $distances = $data['distances'][0] ?? [];

        $results = [];

        foreach ($ids as $i => $id) {
            // Convert distance to similarity score (1 - distance for L2)
            $score = isset($distances[$i]) ? 1.0 / (1.0 + $distances[$i]) : 0.0;

            $results[] = new VectorSearchResult(
                id: $id,
                score: $score,
                content: $documents[$i] ?? '',
                metadata: $metadatas[$i] ?? [],
            );
        }

        return $results;
    }

    /**
     * Delete a document by ID.
     */
    public function delete(string $id): bool
    {
        $this->ensureCollection();

        $response = $this->request()->post(
            "/api/v1/collections/{$this->collectionId}/delete",
            [
                'ids' => [$id],
            ]
        );

        return $response->successful();
    }

    /**
     * Delete multiple documents by IDs.
     *
     * @param  array<string>  $ids
     */
    public function deleteBatch(array $ids): int
    {
        $this->ensureCollection();

        $response = $this->request()->post(
            "/api/v1/collections/{$this->collectionId}/delete",
            [
                'ids' => $ids,
            ]
        );

        return $response->successful() ? count($ids) : 0;
    }

    /**
     * Delete documents matching a filter.
     *
     * @param  array<string, mixed>  $filter
     */
    public function deleteByFilter(array $filter): int
    {
        $this->ensureCollection();

        $response = $this->request()->post(
            "/api/v1/collections/{$this->collectionId}/delete",
            [
                'where' => $filter,
            ]
        );

        // Chroma doesn't return count
        return $response->successful() ? -1 : 0;
    }

    /**
     * Get a document by ID.
     */
    public function get(string $id): ?VectorDocument
    {
        $this->ensureCollection();

        $response = $this->request()->post(
            "/api/v1/collections/{$this->collectionId}/get",
            [
                'ids' => [$id],
                'include' => ['documents', 'metadatas', 'embeddings'],
            ]
        );

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        if (empty($data['ids'])) {
            return null;
        }

        return new VectorDocument(
            id: $data['ids'][0],
            content: $data['documents'][0] ?? '',
            embedding: $data['embeddings'][0] ?? [],
            metadata: $data['metadatas'][0] ?? [],
        );
    }

    /**
     * Check if a document exists.
     */
    public function exists(string $id): bool
    {
        return $this->get($id) !== null;
    }

    /**
     * Get the total document count.
     */
    public function count(): int
    {
        $this->ensureCollection();

        $response = $this->request()->get(
            "/api/v1/collections/{$this->collectionId}/count"
        );

        if (! $response->successful()) {
            return 0;
        }

        return $response->json() ?? 0;
    }

    /**
     * Clear all documents.
     */
    public function clear(): void
    {
        if ($this->collectionId) {
            $this->request()->delete("/api/v1/collections/{$this->collectionId}");
            $this->collectionId = null;
        }
    }

    /**
     * Ensure collection exists.
     */
    protected function ensureCollection(): void
    {
        if ($this->collectionId !== null) {
            return;
        }

        // Try to get existing collection
        $response = $this->request()->get('/api/v1/collections');

        if ($response->successful()) {
            $collections = $response->json() ?? [];

            foreach ($collections as $collection) {
                if ($collection['name'] === $this->collection) {
                    $this->collectionId = $collection['id'];

                    return;
                }
            }
        }

        // Create collection
        $response = $this->request()->post('/api/v1/collections', [
            'name' => $this->collection,
            'metadata' => [
                'hnsw:space' => 'cosine',
            ],
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $this->collectionId = $data['id'];
        }
    }

    /**
     * Create the HTTP request.
     */
    protected function request(): PendingRequest
    {
        return Http::baseUrl($this->host)
            ->timeout($this->timeout)
            ->acceptJson();
    }
}
