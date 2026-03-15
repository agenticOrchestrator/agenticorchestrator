<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Embeddings\Stores;

use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Embeddings\VectorDocument;
use AgenticOrchestrator\Embeddings\VectorSearchResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Qdrant Vector Store - Vector storage using Qdrant.
 *
 * @see https://qdrant.tech/documentation/
 */
class QdrantVectorStore implements VectorStoreInterface
{
    protected string $host;

    protected ?string $apiKey = null;

    protected string $collection;

    protected int $timeout = 30;

    /**
     * Create a new Qdrant vector store.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->host = rtrim($config['host'] ?? 'http://localhost:6333', '/');
        $this->apiKey = $config['api_key'] ?? null;
        $this->collection = $config['collection'] ?? 'documents';
        $this->timeout = $config['timeout'] ?? 30;
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
        $payload = array_merge($metadata, ['content' => $content]);

        $this->request()->put("/collections/{$this->collection}/points", [
            'points' => [
                [
                    'id' => $this->hashId($id),
                    'vector' => $embedding,
                    'payload' => array_merge($payload, ['_id' => $id]),
                ],
            ],
        ]);
    }

    /**
     * Store multiple documents.
     *
     * @param  array<VectorDocument>  $documents
     */
    public function upsertBatch(array $documents): void
    {
        $points = [];

        foreach ($documents as $doc) {
            $payload = array_merge($doc->metadata, [
                'content' => $doc->content,
                '_id' => $doc->id,
            ]);

            $points[] = [
                'id' => $this->hashId($doc->id),
                'vector' => $doc->embedding,
                'payload' => $payload,
            ];
        }

        $this->request()->put("/collections/{$this->collection}/points", [
            'points' => $points,
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
        $body = [
            'vector' => $embedding,
            'limit' => $limit,
            'with_payload' => true,
        ];

        if (! empty($filter)) {
            $body['filter'] = $this->buildFilter($filter);
        }

        $response = $this->request()->post(
            "/collections/{$this->collection}/points/search",
            $body
        );

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json();
        $results = $data['result'] ?? [];

        return array_map(function ($item) {
            $payload = $item['payload'] ?? [];
            $content = $payload['content'] ?? '';
            $id = $payload['_id'] ?? (string) $item['id'];
            unset($payload['content'], $payload['_id']);

            return new VectorSearchResult(
                id: $id,
                score: (float) ($item['score'] ?? 0.0),
                content: $content,
                metadata: $payload,
            );
        }, $results);
    }

    /**
     * Delete a document by ID.
     */
    public function delete(string $id): bool
    {
        $response = $this->request()->post(
            "/collections/{$this->collection}/points/delete",
            [
                'points' => [$this->hashId($id)],
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
        $hashedIds = array_map(fn ($id) => $this->hashId($id), $ids);

        $response = $this->request()->post(
            "/collections/{$this->collection}/points/delete",
            [
                'points' => $hashedIds,
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
        $response = $this->request()->post(
            "/collections/{$this->collection}/points/delete",
            [
                'filter' => $this->buildFilter($filter),
            ]
        );

        // Qdrant doesn't return count directly
        return $response->successful() ? -1 : 0;
    }

    /**
     * Get a document by ID.
     */
    public function get(string $id): ?VectorDocument
    {
        $response = $this->request()->get(
            "/collections/{$this->collection}/points/{$this->hashId($id)}"
        );

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $result = $data['result'] ?? null;

        if (! $result) {
            return null;
        }

        $payload = $result['payload'] ?? [];
        $content = $payload['content'] ?? '';
        $originalId = $payload['_id'] ?? $id;
        unset($payload['content'], $payload['_id']);

        return new VectorDocument(
            id: $originalId,
            content: $content,
            embedding: $result['vector'] ?? [],
            metadata: $payload,
        );
    }

    /**
     * Check if a document exists.
     */
    public function exists(string $id): bool
    {
        $response = $this->request()->get(
            "/collections/{$this->collection}/points/{$this->hashId($id)}"
        );

        return $response->successful();
    }

    /**
     * Get the total document count.
     */
    public function count(): int
    {
        $response = $this->request()->get("/collections/{$this->collection}");

        if (! $response->successful()) {
            return 0;
        }

        $data = $response->json();

        return $data['result']['points_count'] ?? 0;
    }

    /**
     * Clear all documents.
     */
    public function clear(): void
    {
        $this->request()->delete("/collections/{$this->collection}");
    }

    /**
     * Create the collection if it doesn't exist.
     */
    public function createCollection(int $vectorSize): void
    {
        $this->request()->put("/collections/{$this->collection}", [
            'vectors' => [
                'size' => $vectorSize,
                'distance' => 'Cosine',
            ],
        ]);
    }

    /**
     * Build a filter for Qdrant.
     *
     * @param  array<string, mixed>  $filter
     * @return array<string, mixed>
     */
    protected function buildFilter(array $filter): array
    {
        $must = [];

        foreach ($filter as $key => $value) {
            $must[] = [
                'key' => $key,
                'match' => ['value' => $value],
            ];
        }

        return ['must' => $must];
    }

    /**
     * Hash a string ID to an integer for Qdrant.
     */
    protected function hashId(string $id): int
    {
        // Use CRC32 for fast, consistent hashing
        return crc32($id) & 0x7FFFFFFF;
    }

    /**
     * Create the HTTP request.
     */
    protected function request(): PendingRequest
    {
        $request = Http::baseUrl($this->host)
            ->timeout($this->timeout)
            ->acceptJson();

        if ($this->apiKey) {
            $request->withHeaders([
                'api-key' => $this->apiKey,
            ]);
        }

        return $request;
    }
}
