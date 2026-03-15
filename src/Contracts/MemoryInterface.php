<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Contracts;

use AgenticOrchestrator\Conversations\Message;
use Illuminate\Support\Collection;

/**
 * Interface for agent memory systems.
 *
 * Memory provides persistence and retrieval capabilities
 * for agents, including conversation history and semantic
 * search functionality.
 */
interface MemoryInterface
{
    /**
     * Store a value in memory.
     *
     * @param  string  $key  Unique identifier for the value
     * @param  mixed  $value  The value to store (will be serialized)
     * @param  array<string, mixed>  $metadata  Additional metadata for search/filtering
     */
    public function store(string $key, mixed $value, array $metadata = []): void;

    /**
     * Recall a value from memory by key.
     *
     * @param  string  $key  The key to look up
     * @return mixed The stored value, or null if not found
     */
    public function recall(string $key): mixed;

    /**
     * Check if a key exists in memory.
     *
     * @param  string  $key  The key to check
     */
    public function has(string $key): bool;

    /**
     * Search memory semantically.
     *
     * For vector/RAG drivers, this performs similarity search.
     * For other drivers, falls back to keyword search.
     *
     * @param  string  $query  The search query
     * @param  int  $limit  Maximum results to return
     * @return Collection<int, array{
     *     key: string,
     *     content: mixed,
     *     score: float,
     *     metadata: array<string, mixed>
     * }>
     */
    public function search(string $query, int $limit = 5): Collection;

    /**
     * Forget a specific key.
     *
     * @param  string  $key  The key to remove
     */
    public function forget(string $key): void;

    /**
     * Clear all memory for this scope.
     *
     * Removes all stored values for the current
     * agent/team/user scope.
     */
    public function clear(): void;

    /**
     * Get recent conversation history.
     *
     * Returns messages in chronological order,
     * limited to the most recent entries.
     *
     * @param  int  $limit  Maximum messages to return
     * @return array<int, Message>
     */
    public function getConversationHistory(int $limit = 50): array;

    /**
     * Add a message to conversation history.
     *
     * @param  Message  $message  The message to add
     */
    public function addMessage(Message $message): void;

    /**
     * Get the memory driver name.
     *
     * E.g., 'session', 'cache', 'database', 'vector', 'rag'
     */
    public function getDriver(): string;

    /**
     * Get the namespace for this memory scope.
     *
     * Typically includes team_id and agent_id for isolation.
     */
    public function getNamespace(): string;
}
