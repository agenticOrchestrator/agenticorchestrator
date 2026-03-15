<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Memory\Drivers;

use AgenticOrchestrator\Contracts\MemoryInterface;
use AgenticOrchestrator\Conversations\Message;
use AgenticOrchestrator\Embeddings\Contracts\EmbeddingProviderInterface;
use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Embeddings\VectorSearchResult;
use AgenticOrchestrator\Rag\RagPipeline;
use Illuminate\Support\Collection;

/**
 * RAG Memory Driver - Document-aware semantic memory.
 *
 * Combines the RAG pipeline for document retrieval with
 * vector storage for key-value memory operations. Ideal for
 * agents that need to recall information from ingested documents.
 */
class RagMemoryDriver implements MemoryInterface
{
    /**
     * Namespace for scoping memories.
     */
    protected string $namespace = 'default';

    /**
     * In-memory conversation history.
     *
     * @var array<int, Message>
     */
    protected array $conversationHistory = [];

    public function __construct(
        protected RagPipeline $pipeline,
        protected EmbeddingProviderInterface $embeddings,
        protected VectorStoreInterface $store,
    ) {}

    /**
     * Store a value in memory with vector embedding.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function store(string $key, mixed $value, array $metadata = []): void
    {
        $content = is_string($value) ? $value : (string) json_encode($value);
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
    }

    /**
     * Recall a value from memory.
     */
    public function recall(string $key): mixed
    {
        $doc = $this->store->get($this->prefixKey($key));

        if ($doc === null) {
            return null;
        }

        $decoded = json_decode($doc->content, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $doc->content;
    }

    /**
     * Check if a key exists in memory.
     */
    public function has(string $key): bool
    {
        return $this->store->exists($this->prefixKey($key));
    }

    /**
     * Search memory using the RAG pipeline for semantic retrieval.
     *
     * @return Collection<int, array{key: string, content: mixed, score: float, metadata: array<string, mixed>}>
     */
    public function search(string $query, int $limit = 5): Collection
    {
        $result = $this->pipeline
            ->namespace($this->namespace)
            ->limit($limit)
            ->query($query);

        /** @var Collection<int, array{key: string, content: mixed, score: float, metadata: array<string, mixed>}> */
        return collect($result->getResults())->values()->map(fn (VectorSearchResult $r) => [
            'key' => $r->getId(),
            'content' => $r->document->content,
            'score' => $r->score,
            'metadata' => $r->document->metadata,
        ]);
    }

    /**
     * Forget a specific key.
     */
    public function forget(string $key): void
    {
        $this->store->delete($this->prefixKey($key));
    }

    /**
     * Clear all memory in the current namespace.
     */
    public function clear(): void
    {
        $this->store->deleteByFilter(['namespace' => $this->namespace]);
        $this->conversationHistory = [];
    }

    /**
     * Get conversation history.
     *
     * @return array<int, Message>
     */
    public function getConversationHistory(int $limit = 50): array
    {
        return array_slice($this->conversationHistory, -$limit);
    }

    /**
     * Add a message to conversation history.
     */
    public function addMessage(Message $message): void
    {
        $this->conversationHistory[] = $message;

        $this->store(
            'msg_'.uniqid(),
            $message->content,
            ['type' => 'message', 'role' => $message->role->value]
        );
    }

    /**
     * Get the driver name.
     */
    public function getDriver(): string
    {
        return 'rag';
    }

    /**
     * Get the namespace.
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * Set the namespace.
     */
    public function setNamespace(string $namespace): static
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * Access the underlying RAG pipeline.
     */
    public function getPipeline(): RagPipeline
    {
        return $this->pipeline;
    }

    /**
     * Prefix a key with the namespace.
     */
    protected function prefixKey(string $key): string
    {
        return "{$this->namespace}:{$key}";
    }
}
