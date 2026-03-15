<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Memory;

use AgenticOrchestrator\Contracts\MemoryInterface;
use AgenticOrchestrator\Conversations\Message;
use Illuminate\Support\Collection;

/**
 * Memory wrapper that adds namespace scoping.
 */
class Memory implements MemoryInterface
{
    /**
     * Current namespace for scoping.
     */
    protected string $namespace = 'default';

    public function __construct(
        protected MemoryInterface $driver,
    ) {}

    /**
     * Set the namespace for this memory instance.
     */
    public function forNamespace(string $namespace): self
    {
        $clone = clone $this;
        $clone->namespace = $namespace;

        return $clone;
    }

    /**
     * Store a value in memory.
     */
    public function store(string $key, mixed $value, array $metadata = []): void
    {
        $this->driver->store(
            $this->prefixKey($key),
            $value,
            array_merge($metadata, ['namespace' => $this->namespace])
        );
    }

    /**
     * Recall a value from memory.
     */
    public function recall(string $key): mixed
    {
        return $this->driver->recall($this->prefixKey($key));
    }

    /**
     * Check if a key exists in memory.
     */
    public function has(string $key): bool
    {
        return $this->driver->has($this->prefixKey($key));
    }

    /**
     * Search memory semantically.
     */
    public function search(string $query, int $limit = 5): Collection
    {
        return $this->driver->search($query, $limit);
    }

    /**
     * Forget a specific key.
     */
    public function forget(string $key): void
    {
        $this->driver->forget($this->prefixKey($key));
    }

    /**
     * Clear all memory for this scope.
     */
    public function clear(): void
    {
        $this->driver->clear();
    }

    /**
     * Get recent conversation history.
     *
     * @return array<int, Message>
     */
    public function getConversationHistory(int $limit = 50): array
    {
        return $this->driver->getConversationHistory($limit);
    }

    /**
     * Add a message to conversation history.
     */
    public function addMessage(Message $message): void
    {
        $this->driver->addMessage($message);
    }

    /**
     * Get the memory driver name.
     */
    public function getDriver(): string
    {
        return $this->driver->getDriver();
    }

    /**
     * Get the namespace.
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
}
