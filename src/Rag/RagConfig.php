<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Rag;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * RagConfig - Configuration value object for RAG pipelines.
 *
 * Encapsulates all configuration options for a RAG pipeline,
 * including chunking, retrieval, and namespace settings.
 *
 * @implements Arrayable<string, mixed>
 */
class RagConfig implements Arrayable, JsonSerializable
{
    /**
     * Create a new RAG configuration.
     *
     * @param  string  $namespace  Namespace for scoping documents
     * @param  int  $chunkSize  Size of each chunk in characters
     * @param  int  $chunkOverlap  Overlap between chunks in characters
     * @param  string  $chunker  Chunking strategy (fixed, recursive)
     * @param  string  $retriever  Retriever type (vector, hybrid)
     * @param  int  $retrieveLimit  Maximum documents to retrieve
     * @param  float  $scoreThreshold  Minimum similarity score
     * @param  string|null  $tenantId  Tenant identifier for multi-tenancy
     * @param  array<string, mixed>  $extra  Additional configuration options
     */
    public function __construct(
        public readonly string $namespace = 'default',
        public readonly int $chunkSize = 1000,
        public readonly int $chunkOverlap = 200,
        public readonly string $chunker = 'recursive',
        public readonly string $retriever = 'vector',
        public readonly int $retrieveLimit = 5,
        public readonly float $scoreThreshold = 0.7,
        public readonly ?string $tenantId = null,
        public readonly array $extra = [],
    ) {}

    /**
     * Create from Laravel config.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): static
    {
        return new static(
            namespace: $config['namespace'] ?? 'default',
            chunkSize: $config['chunking']['size'] ?? $config['chunk_size'] ?? 1000,
            chunkOverlap: $config['chunking']['overlap'] ?? $config['chunk_overlap'] ?? 200,
            chunker: $config['default_chunker'] ?? $config['chunker'] ?? 'recursive',
            retriever: $config['default_retriever'] ?? $config['retriever'] ?? 'vector',
            retrieveLimit: $config['retrieval']['limit'] ?? $config['retrieve_limit'] ?? 5,
            scoreThreshold: $config['retrieval']['threshold'] ?? $config['score_threshold'] ?? 0.7,
            tenantId: $config['tenant_id'] ?? null,
            extra: $config['extra'] ?? [],
        );
    }

    /**
     * Create with namespace.
     */
    public function withNamespace(string $namespace): static
    {
        return new static(
            namespace: $namespace,
            chunkSize: $this->chunkSize,
            chunkOverlap: $this->chunkOverlap,
            chunker: $this->chunker,
            retriever: $this->retriever,
            retrieveLimit: $this->retrieveLimit,
            scoreThreshold: $this->scoreThreshold,
            tenantId: $this->tenantId,
            extra: $this->extra,
        );
    }

    /**
     * Create with chunk size.
     */
    public function withChunkSize(int $size): static
    {
        return new static(
            namespace: $this->namespace,
            chunkSize: $size,
            chunkOverlap: $this->chunkOverlap,
            chunker: $this->chunker,
            retriever: $this->retriever,
            retrieveLimit: $this->retrieveLimit,
            scoreThreshold: $this->scoreThreshold,
            tenantId: $this->tenantId,
            extra: $this->extra,
        );
    }

    /**
     * Create with chunk overlap.
     */
    public function withChunkOverlap(int $overlap): static
    {
        return new static(
            namespace: $this->namespace,
            chunkSize: $this->chunkSize,
            chunkOverlap: $overlap,
            chunker: $this->chunker,
            retriever: $this->retriever,
            retrieveLimit: $this->retrieveLimit,
            scoreThreshold: $this->scoreThreshold,
            tenantId: $this->tenantId,
            extra: $this->extra,
        );
    }

    /**
     * Create with retrieve limit.
     */
    public function withRetrieveLimit(int $limit): static
    {
        return new static(
            namespace: $this->namespace,
            chunkSize: $this->chunkSize,
            chunkOverlap: $this->chunkOverlap,
            chunker: $this->chunker,
            retriever: $this->retriever,
            retrieveLimit: $limit,
            scoreThreshold: $this->scoreThreshold,
            tenantId: $this->tenantId,
            extra: $this->extra,
        );
    }

    /**
     * Create with score threshold.
     */
    public function withScoreThreshold(float $threshold): static
    {
        return new static(
            namespace: $this->namespace,
            chunkSize: $this->chunkSize,
            chunkOverlap: $this->chunkOverlap,
            chunker: $this->chunker,
            retriever: $this->retriever,
            retrieveLimit: $this->retrieveLimit,
            scoreThreshold: $threshold,
            tenantId: $this->tenantId,
            extra: $this->extra,
        );
    }

    /**
     * Create with tenant ID.
     */
    public function withTenantId(string $tenantId): static
    {
        return new static(
            namespace: $this->namespace,
            chunkSize: $this->chunkSize,
            chunkOverlap: $this->chunkOverlap,
            chunker: $this->chunker,
            retriever: $this->retriever,
            retrieveLimit: $this->retrieveLimit,
            scoreThreshold: $this->scoreThreshold,
            tenantId: $tenantId,
            extra: $this->extra,
        );
    }

    /**
     * Get the effective namespace with tenant prefix.
     */
    public function getEffectiveNamespace(): string
    {
        if ($this->tenantId === null) {
            return $this->namespace;
        }

        return "tenant_{$this->tenantId}_{$this->namespace}";
    }

    /**
     * Get extra configuration value.
     */
    public function getExtra(string $key, mixed $default = null): mixed
    {
        return $this->extra[$key] ?? $default;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'namespace' => $this->namespace,
            'chunk_size' => $this->chunkSize,
            'chunk_overlap' => $this->chunkOverlap,
            'chunker' => $this->chunker,
            'retriever' => $this->retriever,
            'retrieve_limit' => $this->retrieveLimit,
            'score_threshold' => $this->scoreThreshold,
            'tenant_id' => $this->tenantId,
            'extra' => $this->extra,
        ];
    }

    /**
     * Serialize for JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
