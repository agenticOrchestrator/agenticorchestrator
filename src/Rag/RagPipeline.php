<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Rag;

use AgenticOrchestrator\Embeddings\Contracts\EmbeddingProviderInterface;
use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Embeddings\VectorDocument;
use AgenticOrchestrator\Rag\Chunking\FixedSizeChunker;
use AgenticOrchestrator\Rag\Chunking\RecursiveCharacterChunker;
use AgenticOrchestrator\Rag\Contracts\ChunkingStrategyInterface;
use AgenticOrchestrator\Rag\Contracts\DocumentLoaderInterface;
use AgenticOrchestrator\Rag\Contracts\RerankerInterface;
use AgenticOrchestrator\Rag\Contracts\RetrieverInterface;
use AgenticOrchestrator\Rag\Loaders\DirectoryLoader;
use AgenticOrchestrator\Rag\Loaders\JsonLoader;
use AgenticOrchestrator\Rag\Loaders\MarkdownLoader;
use AgenticOrchestrator\Rag\Loaders\TextLoader;
use AgenticOrchestrator\Rag\Retrievers\VectorRetriever;

/**
 * RagPipeline - Fluent builder for RAG operations.
 *
 * Provides a fluent API for building and executing RAG pipelines,
 * including document ingestion, chunking, embedding, and retrieval.
 *
 * @example
 * ```php
 * $pipeline = RagPipeline::make()
 *     ->namespace('knowledge_base')
 *     ->embeddings($embeddings)
 *     ->store($vectorStore)
 *     ->chunkSize(1000)
 *     ->chunkOverlap(200);
 *
 * // Ingest documents
 * $result = $pipeline->from('/path/to/docs')->ingest();
 *
 * // Query for context
 * $result = $pipeline->limit(5)->threshold(0.7)->query('user question');
 * echo $result->getContext();
 * ```
 */
class RagPipeline
{
    /**
     * The embedding provider.
     */
    protected ?EmbeddingProviderInterface $embeddings = null;

    /**
     * The vector store.
     */
    protected ?VectorStoreInterface $store = null;

    /**
     * The document loader.
     */
    protected ?DocumentLoaderInterface $loader = null;

    /**
     * The chunking strategy.
     */
    protected ?ChunkingStrategyInterface $chunker = null;

    /**
     * The retriever.
     */
    protected ?RetrieverInterface $retriever = null;

    /**
     * The reranker.
     */
    protected ?RerankerInterface $reranker = null;

    /**
     * The configuration.
     */
    protected RagConfig $config;

    /**
     * Documents pending for ingestion.
     *
     * @var array<Document>
     */
    protected array $pendingDocuments = [];

    /**
     * The source path or content for loading.
     */
    protected ?string $source = null;

    /**
     * Create a new RAG pipeline.
     */
    public function __construct(?RagConfig $config = null)
    {
        $this->config = $config ?? $this->loadDefaultConfig();
    }

    /**
     * Create a new pipeline instance.
     */
    public static function make(?RagConfig $config = null): static
    {
        return new static($config);
    }

    /**
     * Load default configuration from Laravel config.
     */
    protected function loadDefaultConfig(): RagConfig
    {
        if (function_exists('config')) {
            $ragConfig = config('agent-orchestrator.rag', []);

            return RagConfig::fromConfig($ragConfig);
        }

        return new RagConfig;
    }

    /**
     * Set the namespace for document scoping.
     */
    public function namespace(string $namespace): static
    {
        $this->config = $this->config->withNamespace($namespace);

        return $this;
    }

    /**
     * Set the tenant ID for multi-tenancy.
     */
    public function forTenant(string|int $tenantId): static
    {
        $this->config = $this->config->withTenantId((string) $tenantId);

        return $this;
    }

    /**
     * Set the embedding provider.
     */
    public function embeddings(EmbeddingProviderInterface $embeddings): static
    {
        $this->embeddings = $embeddings;

        return $this;
    }

    /**
     * Set the vector store.
     */
    public function store(VectorStoreInterface $store): static
    {
        $this->store = $store;

        return $this;
    }

    /**
     * Set the chunk size.
     */
    public function chunkSize(int $size): static
    {
        $this->config = $this->config->withChunkSize($size);

        return $this;
    }

    /**
     * Set the chunk overlap.
     */
    public function chunkOverlap(int $overlap): static
    {
        $this->config = $this->config->withChunkOverlap($overlap);

        return $this;
    }

    /**
     * Set the retrieve limit.
     */
    public function limit(int $limit): static
    {
        $this->config = $this->config->withRetrieveLimit($limit);

        return $this;
    }

    /**
     * Set the score threshold.
     */
    public function threshold(float $threshold): static
    {
        $this->config = $this->config->withScoreThreshold($threshold);

        return $this;
    }

    /**
     * Set a custom document loader.
     */
    public function loader(DocumentLoaderInterface $loader): static
    {
        $this->loader = $loader;

        return $this;
    }

    /**
     * Set a custom chunking strategy.
     */
    public function chunker(ChunkingStrategyInterface $chunker): static
    {
        $this->chunker = $chunker;

        return $this;
    }

    /**
     * Set a custom retriever.
     */
    public function retriever(RetrieverInterface $retriever): static
    {
        $this->retriever = $retriever;

        return $this;
    }

    /**
     * Set a custom reranker.
     */
    public function reranker(RerankerInterface $reranker): static
    {
        $this->reranker = $reranker;

        return $this;
    }

    /**
     * Set the source path or content for loading.
     */
    public function from(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    /**
     * Add raw text content for ingestion.
     */
    public function fromText(string $content, array $metadata = []): static
    {
        $this->pendingDocuments[] = Document::fromText($content, $metadata);

        return $this;
    }

    /**
     * Add a document for ingestion.
     */
    public function addDocument(Document $document): static
    {
        $this->pendingDocuments[] = $document;

        return $this;
    }

    /**
     * Add multiple documents for ingestion.
     *
     * @param  array<Document>  $documents
     */
    public function addDocuments(array $documents): static
    {
        foreach ($documents as $document) {
            $this->pendingDocuments[] = $document;
        }

        return $this;
    }

    /**
     * Ingest documents into the vector store.
     */
    public function ingest(): RagPipelineResult
    {
        $startTime = microtime(true);

        $this->validateIngestion();

        // Load documents from source if specified
        if ($this->source !== null) {
            $loader = $this->resolveLoader($this->source);
            $loadedDocs = $loader->load($this->source);
            $this->pendingDocuments = array_merge($this->pendingDocuments, $loadedDocs);
        }

        if (empty($this->pendingDocuments)) {
            return RagPipelineResult::forIngest(
                documentsProcessed: 0,
                chunksCreated: 0,
                durationMs: (microtime(true) - $startTime) * 1000,
                metadata: ['message' => 'No documents to ingest'],
            );
        }

        // Chunk documents
        $chunker = $this->resolveChunker();
        $chunks = $chunker->chunkAll($this->pendingDocuments);

        // Generate embeddings and store
        $vectorDocuments = $this->embedAndPrepare($chunks);
        $this->store->upsertBatch($vectorDocuments);

        $documentsCount = count($this->pendingDocuments);
        $chunksCount = count($chunks);

        // Reset state
        $this->pendingDocuments = [];
        $this->source = null;

        return RagPipelineResult::forIngest(
            documentsProcessed: $documentsCount,
            chunksCreated: $chunksCount,
            durationMs: (microtime(true) - $startTime) * 1000,
            metadata: [
                'namespace' => $this->config->getEffectiveNamespace(),
                'chunk_size' => $this->config->chunkSize,
                'chunk_overlap' => $this->config->chunkOverlap,
            ],
        );
    }

    /**
     * Query the pipeline for relevant context.
     */
    public function query(string $query): RagPipelineResult
    {
        $startTime = microtime(true);

        $this->validateQuery();

        $retriever = $this->resolveRetriever();
        $results = $retriever->retrieve(
            query: $query,
            limit: $this->config->retrieveLimit,
            filter: ['namespace' => $this->config->getEffectiveNamespace()],
        );

        // Apply reranking if configured
        if ($this->reranker !== null) {
            $results = $this->reranker->rerank($results, $query);
        }

        return RagPipelineResult::forQuery(
            results: $results,
            query: $query,
            durationMs: (microtime(true) - $startTime) * 1000,
            metadata: [
                'namespace' => $this->config->getEffectiveNamespace(),
                'limit' => $this->config->retrieveLimit,
                'threshold' => $this->config->scoreThreshold,
            ],
        );
    }

    /**
     * Search for similar content (alias for query).
     */
    public function search(string $query): RagPipelineResult
    {
        return $this->query($query);
    }

    /**
     * Delete documents by filter.
     *
     * @param  array<string, mixed>  $filter
     */
    public function delete(array $filter = []): int
    {
        $this->validateStore();

        $effectiveFilter = array_merge(
            ['namespace' => $this->config->getEffectiveNamespace()],
            $filter
        );

        return $this->store->deleteByFilter($effectiveFilter);
    }

    /**
     * Clear all documents in the current namespace.
     */
    public function clear(): int
    {
        return $this->delete();
    }

    /**
     * Get the current configuration.
     */
    public function getConfig(): RagConfig
    {
        return $this->config;
    }

    /**
     * Get the effective namespace.
     */
    public function getNamespace(): string
    {
        return $this->config->getEffectiveNamespace();
    }

    /**
     * Validate ingestion requirements.
     */
    protected function validateIngestion(): void
    {
        if ($this->embeddings === null) {
            throw new \InvalidArgumentException('Embedding provider is required for ingestion');
        }

        if ($this->store === null) {
            throw new \InvalidArgumentException('Vector store is required for ingestion');
        }
    }

    /**
     * Validate query requirements.
     */
    protected function validateQuery(): void
    {
        if ($this->embeddings === null) {
            throw new \InvalidArgumentException('Embedding provider is required for querying');
        }

        if ($this->store === null) {
            throw new \InvalidArgumentException('Vector store is required for querying');
        }
    }

    /**
     * Validate store is configured.
     */
    protected function validateStore(): void
    {
        if ($this->store === null) {
            throw new \InvalidArgumentException('Vector store is required');
        }
    }

    /**
     * Resolve the appropriate document loader.
     */
    protected function resolveLoader(string $source): DocumentLoaderInterface
    {
        if ($this->loader !== null) {
            return $this->loader;
        }

        // Auto-detect based on source
        if (is_dir($source)) {
            return new DirectoryLoader;
        }

        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));

        return match ($extension) {
            'json', 'jsonl' => new JsonLoader,
            'md', 'markdown' => new MarkdownLoader,
            default => new TextLoader,
        };
    }

    /**
     * Resolve the chunking strategy.
     */
    protected function resolveChunker(): ChunkingStrategyInterface
    {
        if ($this->chunker !== null) {
            return $this->chunker
                ->setChunkSize($this->config->chunkSize)
                ->setOverlap($this->config->chunkOverlap);
        }

        $chunker = match ($this->config->chunker) {
            'fixed' => new FixedSizeChunker,
            default => new RecursiveCharacterChunker,
        };

        return $chunker
            ->setChunkSize($this->config->chunkSize)
            ->setOverlap($this->config->chunkOverlap);
    }

    /**
     * Resolve the retriever.
     */
    protected function resolveRetriever(): RetrieverInterface
    {
        if ($this->retriever !== null) {
            return $this->retriever->setThreshold($this->config->scoreThreshold);
        }

        $retriever = new VectorRetriever($this->embeddings, $this->store);

        return $retriever->setThreshold($this->config->scoreThreshold);
    }

    /**
     * Embed documents and prepare for storage.
     *
     * @param  array<Document>  $documents
     * @return array<VectorDocument>
     */
    protected function embedAndPrepare(array $documents): array
    {
        $vectorDocuments = [];
        $batchSize = 100;
        $namespace = $this->config->getEffectiveNamespace();

        // Process in batches for efficiency
        foreach (array_chunk($documents, $batchSize) as $batch) {
            $contents = array_map(fn (Document $doc) => $doc->content, $batch);
            $embeddings = $this->embeddings->embedBatch($contents);

            foreach ($batch as $i => $document) {
                $vectorDocuments[] = new VectorDocument(
                    id: "{$namespace}:{$document->id}",
                    content: $document->content,
                    embedding: $embeddings[$i],
                    metadata: array_merge($document->metadata, [
                        'namespace' => $namespace,
                        'source' => $document->source,
                        'ingested_at' => date('c'),
                    ]),
                );
            }
        }

        return $vectorDocuments;
    }
}
