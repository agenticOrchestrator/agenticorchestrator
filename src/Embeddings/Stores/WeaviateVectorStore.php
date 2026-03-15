<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Embeddings\Stores;

use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Embeddings\VectorDocument;
use AgenticOrchestrator\Embeddings\VectorSearchResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Weaviate Vector Store - Vector storage using Weaviate.
 *
 * @see https://weaviate.io/developers/weaviate
 */
class WeaviateVectorStore implements VectorStoreInterface
{
    protected string $host;

    protected ?string $apiKey = null;

    protected string $className;

    protected int $timeout = 30;

    /**
     * Create a new Weaviate vector store.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->host = rtrim($config['host'] ?? 'http://localhost:8080', '/');
        $this->apiKey = $config['api_key'] ?? null;
        $this->className = $config['class_name'] ?? 'Document';
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
        $properties = array_merge($metadata, ['content' => $content]);

        $response = $this->request()->post("/v1/objects/{$this->className}", [
            'id' => $id,
            'class' => $this->className,
            'vector' => $embedding,
            'properties' => $properties,
        ]);

        if ($response->status() === 422) {
            // Object exists, update it
            $this->request()->put("/v1/objects/{$this->className}/{$id}", [
                'class' => $this->className,
                'vector' => $embedding,
                'properties' => $properties,
            ]);
        }
    }

    /**
     * Store multiple documents.
     *
     * @param  array<VectorDocument>  $documents
     */
    public function upsertBatch(array $documents): void
    {
        $objects = [];

        foreach ($documents as $doc) {
            $properties = array_merge($doc->metadata, ['content' => $doc->content]);
            $objects[] = [
                'id' => $doc->id,
                'class' => $this->className,
                'vector' => $doc->embedding,
                'properties' => $properties,
            ];
        }

        $this->request()->post('/v1/batch/objects', [
            'objects' => $objects,
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
        $graphql = $this->buildSearchQuery($embedding, $limit, $filter);

        $response = $this->request()->post('/v1/graphql', [
            'query' => $graphql,
        ]);

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json();
        $results = $data['data']['Get'][$this->className] ?? [];

        return array_map(function ($item) {
            $score = $item['_additional']['certainty'] ?? 0.0;
            $metadata = $item;
            $content = $metadata['content'] ?? '';
            unset($metadata['content'], $metadata['_additional']);

            return new VectorSearchResult(
                id: $item['_additional']['id'] ?? '',
                score: (float) $score,
                content: $content,
                metadata: $metadata,
            );
        }, $results);
    }

    /**
     * Delete a document by ID.
     */
    public function delete(string $id): bool
    {
        $response = $this->request()->delete("/v1/objects/{$this->className}/{$id}");

        return $response->successful();
    }

    /**
     * Delete multiple documents by IDs.
     *
     * @param  array<string>  $ids
     */
    public function deleteBatch(array $ids): int
    {
        $deleted = 0;

        foreach ($ids as $id) {
            if ($this->delete($id)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Delete documents matching a filter.
     *
     * @param  array<string, mixed>  $filter
     */
    public function deleteByFilter(array $filter): int
    {
        $where = $this->buildWhereFilter($filter);

        $response = $this->request()->delete('/v1/batch/objects', [
            'match' => [
                'class' => $this->className,
                'where' => $where,
            ],
        ]);

        if (! $response->successful()) {
            return 0;
        }

        $data = $response->json();

        return $data['results']['successful'] ?? 0;
    }

    /**
     * Get a document by ID.
     */
    public function get(string $id): ?VectorDocument
    {
        $response = $this->request()->get("/v1/objects/{$this->className}/{$id}", [
            'include' => 'vector',
        ]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $properties = $data['properties'] ?? [];
        $content = $properties['content'] ?? '';
        unset($properties['content']);

        return new VectorDocument(
            id: $data['id'],
            content: $content,
            embedding: $data['vector'] ?? [],
            metadata: $properties,
        );
    }

    /**
     * Check if a document exists.
     */
    public function exists(string $id): bool
    {
        $response = $this->request()->head("/v1/objects/{$this->className}/{$id}");

        return $response->successful();
    }

    /**
     * Get the total document count.
     */
    public function count(): int
    {
        $query = <<<GRAPHQL
        {
            Aggregate {
                {$this->className} {
                    meta {
                        count
                    }
                }
            }
        }
        GRAPHQL;

        $response = $this->request()->post('/v1/graphql', [
            'query' => $query,
        ]);

        if (! $response->successful()) {
            return 0;
        }

        $data = $response->json();

        return $data['data']['Aggregate'][$this->className][0]['meta']['count'] ?? 0;
    }

    /**
     * Clear all documents.
     */
    public function clear(): void
    {
        $this->request()->delete("/v1/schema/{$this->className}");
    }

    /**
     * Build the GraphQL search query.
     *
     * @param  array<float>  $embedding
     * @param  array<string, mixed>  $filter
     */
    protected function buildSearchQuery(array $embedding, int $limit, array $filter): string
    {
        $vectorStr = '['.implode(',', $embedding).']';
        $where = ! empty($filter) ? ', where: '.json_encode($this->buildWhereFilter($filter)) : '';

        return <<<GRAPHQL
        {
            Get {
                {$this->className}(
                    nearVector: {
                        vector: {$vectorStr}
                    }
                    limit: {$limit}
                    {$where}
                ) {
                    content
                    _additional {
                        id
                        certainty
                    }
                }
            }
        }
        GRAPHQL;
    }

    /**
     * Build a where filter for Weaviate.
     *
     * @param  array<string, mixed>  $filter
     * @return array<string, mixed>
     */
    protected function buildWhereFilter(array $filter): array
    {
        $conditions = [];

        foreach ($filter as $key => $value) {
            $conditions[] = [
                'path' => [$key],
                'operator' => 'Equal',
                'valueString' => (string) $value,
            ];
        }

        if (count($conditions) === 1) {
            return $conditions[0];
        }

        return [
            'operator' => 'And',
            'operands' => $conditions,
        ];
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
                'X-Weaviate-Api-Key' => $this->apiKey,
            ]);
        }

        return $request;
    }
}
