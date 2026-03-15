<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Embeddings\Stores;

use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Embeddings\VectorDocument;
use AgenticOrchestrator\Embeddings\VectorSearchResult;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * PgVector Store - PostgreSQL with pgvector extension.
 *
 * Requires PostgreSQL with pgvector extension installed.
 * CREATE EXTENSION IF NOT EXISTS vector;
 */
class PgVectorStore implements VectorStoreInterface
{
    /**
     * Database connection.
     */
    protected Connection $connection;

    /**
     * Table name.
     */
    protected string $table;

    /**
     * Embedding dimension.
     */
    protected int $dimension;

    /**
     * Distance operator.
     */
    protected string $distanceOperator;

    /**
     * Create a new PgVector store.
     *
     * @param  string  $table  Table name
     * @param  int  $dimension  Embedding dimension
     * @param  string  $distanceMetric  'cosine', 'euclidean', or 'inner_product'
     * @param  string|null  $connection  Database connection name
     */
    public function __construct(
        string $table = 'vector_documents',
        int $dimension = 1536,
        string $distanceMetric = 'cosine',
        ?string $connection = null,
    ) {
        $this->connection = DB::connection($connection);
        $this->table = $table;
        $this->dimension = $dimension;

        $this->distanceOperator = match ($distanceMetric) {
            'cosine' => '<=>',
            'euclidean', 'l2' => '<->',
            'inner_product', 'dot' => '<#>',
            default => '<=>',
        };
    }

    /**
     * Create from config.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): static
    {
        return new static(
            table: $config['table'] ?? 'vector_documents',
            dimension: $config['dimension'] ?? 1536,
            distanceMetric: $config['distance_metric'] ?? 'cosine',
            connection: $config['connection'] ?? null,
        );
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
        $vectorString = $this->formatVector($embedding);

        $this->connection->statement("
            INSERT INTO {$this->table} (id, content, embedding, metadata, updated_at)
            VALUES (?, ?, ?::vector, ?::jsonb, NOW())
            ON CONFLICT (id) DO UPDATE SET
                content = EXCLUDED.content,
                embedding = EXCLUDED.embedding,
                metadata = EXCLUDED.metadata,
                updated_at = NOW()
        ", [$id, $content, $vectorString, json_encode($metadata)]);
    }

    /**
     * Store multiple documents.
     *
     * @param  array<VectorDocument>  $documents
     */
    public function upsertBatch(array $documents): void
    {
        foreach ($documents as $doc) {
            $this->upsert(
                $doc->id,
                $doc->embedding,
                $doc->content,
                $doc->metadata,
            );
        }
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
        $vectorString = $this->formatVector($embedding);

        $whereClause = '';
        $params = [$vectorString];

        if (! empty($filter)) {
            $conditions = [];
            foreach ($filter as $key => $value) {
                if (is_array($value)) {
                    $placeholders = implode(',', array_fill(0, count($value), '?'));
                    $conditions[] = "metadata->>? IN ({$placeholders})";
                    $params[] = $key;
                    $params = array_merge($params, $value);
                } else {
                    $conditions[] = 'metadata->>? = ?';
                    $params[] = $key;
                    $params[] = $value;
                }
            }
            $whereClause = 'WHERE '.implode(' AND ', $conditions);
        }

        $params[] = $limit;

        $results = $this->connection->select("
            SELECT
                id,
                content,
                embedding::text,
                metadata,
                1 - (embedding {$this->distanceOperator} ?::vector) as score
            FROM {$this->table}
            {$whereClause}
            ORDER BY embedding {$this->distanceOperator} ?::vector
            LIMIT ?
        ", array_merge([$vectorString], $params));

        return array_map(function ($row) {
            return new VectorSearchResult(
                document: new VectorDocument(
                    id: $row->id,
                    content: $row->content,
                    embedding: $this->parseVector($row->embedding),
                    metadata: json_decode($row->metadata, true) ?? [],
                ),
                score: (float) $row->score,
            );
        }, $results);
    }

    /**
     * Delete a document by ID.
     */
    public function delete(string $id): bool
    {
        $deleted = $this->connection->delete(
            "DELETE FROM {$this->table} WHERE id = ?",
            [$id]
        );

        return $deleted > 0;
    }

    /**
     * Delete multiple documents by IDs.
     *
     * @param  array<string>  $ids
     */
    public function deleteBatch(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        return $this->connection->delete(
            "DELETE FROM {$this->table} WHERE id IN ({$placeholders})",
            $ids
        );
    }

    /**
     * Delete documents matching a filter.
     *
     * @param  array<string, mixed>  $filter
     */
    public function deleteByFilter(array $filter): int
    {
        if (empty($filter)) {
            return 0;
        }

        $conditions = [];
        $params = [];

        foreach ($filter as $key => $value) {
            if (is_array($value)) {
                $placeholders = implode(',', array_fill(0, count($value), '?'));
                $conditions[] = "metadata->>? IN ({$placeholders})";
                $params[] = $key;
                $params = array_merge($params, $value);
            } else {
                $conditions[] = 'metadata->>? = ?';
                $params[] = $key;
                $params[] = $value;
            }
        }

        $whereClause = implode(' AND ', $conditions);

        return $this->connection->delete(
            "DELETE FROM {$this->table} WHERE {$whereClause}",
            $params
        );
    }

    /**
     * Get a document by ID.
     */
    public function get(string $id): ?VectorDocument
    {
        $result = $this->connection->selectOne(
            "SELECT id, content, embedding::text, metadata FROM {$this->table} WHERE id = ?",
            [$id]
        );

        if ($result === null) {
            return null;
        }

        return new VectorDocument(
            id: $result->id,
            content: $result->content,
            embedding: $this->parseVector($result->embedding),
            metadata: json_decode($result->metadata, true) ?? [],
        );
    }

    /**
     * Check if a document exists.
     */
    public function exists(string $id): bool
    {
        $result = $this->connection->selectOne(
            "SELECT 1 FROM {$this->table} WHERE id = ?",
            [$id]
        );

        return $result !== null;
    }

    /**
     * Get the total document count.
     */
    public function count(): int
    {
        $result = $this->connection->selectOne(
            "SELECT COUNT(*) as count FROM {$this->table}"
        );

        return (int) ($result->count ?? 0);
    }

    /**
     * Clear all documents.
     */
    public function clear(): void
    {
        $this->connection->statement("TRUNCATE TABLE {$this->table}");
    }

    /**
     * Format embedding array as pgvector string.
     *
     * @param  array<float>  $embedding
     */
    protected function formatVector(array $embedding): string
    {
        return '['.implode(',', $embedding).']';
    }

    /**
     * Parse pgvector string to array.
     *
     * @return array<float>
     */
    protected function parseVector(string $vector): array
    {
        $trimmed = trim($vector, '[]');

        if ($trimmed === '') {
            return [];
        }

        return array_map('floatval', explode(',', $trimmed));
    }

    /**
     * Create the table if it doesn't exist.
     */
    public function createTable(): void
    {
        $this->connection->statement("
            CREATE TABLE IF NOT EXISTS {$this->table} (
                id TEXT PRIMARY KEY,
                content TEXT NOT NULL,
                embedding vector({$this->dimension}),
                metadata JSONB DEFAULT '{}',
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW()
            )
        ");

        // Create index for similarity search
        $indexName = "{$this->table}_embedding_idx";
        $this->connection->statement("
            CREATE INDEX IF NOT EXISTS {$indexName}
            ON {$this->table}
            USING ivfflat (embedding vector_cosine_ops)
            WITH (lists = 100)
        ");
    }

    /**
     * Drop the table.
     */
    public function dropTable(): void
    {
        $this->connection->statement("DROP TABLE IF EXISTS {$this->table}");
    }
}
