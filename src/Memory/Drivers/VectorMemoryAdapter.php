<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Memory\Drivers;

use AgenticOrchestrator\Contracts\MemoryInterface;
use AgenticOrchestrator\Conversations\Message;
use Illuminate\Support\Collection;

/**
 * Adapts VectorMemoryDriver to the MemoryInterface contract.
 *
 * Provides semantic search capabilities through vector embeddings
 * while fulfilling the standard memory interface for interchangeable usage.
 */
class VectorMemoryAdapter implements MemoryInterface
{
    /**
     * In-memory conversation history (vector stores are not ideal for ordered history).
     *
     * @var array<int, Message>
     */
    protected array $conversationHistory = [];

    public function __construct(
        protected VectorMemoryDriver $driver,
    ) {}

    /**
     * Store a value in memory with vector embedding.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function store(string $key, mixed $value, array $metadata = []): void
    {
        $this->driver->set($key, $value);
    }

    /**
     * Recall a value from memory.
     */
    public function recall(string $key): mixed
    {
        return $this->driver->get($key);
    }

    /**
     * Check if a key exists in memory.
     */
    public function has(string $key): bool
    {
        return $this->driver->has($key);
    }

    /**
     * Search memory semantically using vector similarity.
     *
     * @return Collection<int, array{key: string, content: mixed, score: float, metadata: array<string, mixed>}>
     */
    public function search(string $query, int $limit = 5): Collection
    {
        $results = $this->driver->search($query, $limit, 0.0);

        return collect($results)->map(fn ($result) => [
            'key' => $result->getId(),
            'content' => $result->document->content,
            'score' => $result->score,
            'metadata' => $result->document->metadata,
        ]);
    }

    /**
     * Forget a specific key.
     */
    public function forget(string $key): void
    {
        $this->driver->forget($key);
    }

    /**
     * Clear all memory in the current namespace.
     */
    public function clear(): void
    {
        $this->driver->flush();
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

        // Also store as vector for semantic recall
        $this->driver->set(
            'msg_'.uniqid(),
            $message->content,
        );
    }

    /**
     * Get the driver name.
     */
    public function getDriver(): string
    {
        return 'vector';
    }

    /**
     * Get the namespace.
     */
    public function getNamespace(): string
    {
        return $this->driver->getNamespace();
    }

    /**
     * Set the namespace.
     */
    public function setNamespace(string $namespace): static
    {
        $this->driver->namespace($namespace);

        return $this;
    }

    /**
     * Access the underlying vector driver for advanced operations.
     */
    public function getVectorDriver(): VectorMemoryDriver
    {
        return $this->driver;
    }
}
