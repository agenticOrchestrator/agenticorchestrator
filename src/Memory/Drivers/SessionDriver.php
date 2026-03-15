<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Memory\Drivers;

use AgenticOrchestrator\Contracts\MemoryInterface;
use AgenticOrchestrator\Conversations\Message;
use Illuminate\Support\Collection;

/**
 * In-request session memory driver.
 *
 * Data only persists for the duration of the request.
 * Useful for single-turn conversations or testing.
 */
class SessionDriver implements MemoryInterface
{
    /**
     * In-memory storage.
     *
     * @var array<string, mixed>
     */
    protected array $storage = [];

    /**
     * Conversation history.
     *
     * @var array<int, Message>
     */
    protected array $history = [];

    /**
     * Store a value in memory.
     */
    public function store(string $key, mixed $value, array $metadata = []): void
    {
        $this->storage[$key] = [
            'value' => $value,
            'metadata' => $metadata,
            'timestamp' => time(),
        ];
    }

    /**
     * Recall a value from memory.
     */
    public function recall(string $key): mixed
    {
        return $this->storage[$key]['value'] ?? null;
    }

    /**
     * Check if a key exists in memory.
     */
    public function has(string $key): bool
    {
        return isset($this->storage[$key]);
    }

    /**
     * Search memory (basic keyword search for session driver).
     */
    public function search(string $query, int $limit = 5): Collection
    {
        $results = collect();
        $queryLower = strtolower($query);

        foreach ($this->storage as $key => $data) {
            $content = is_string($data['value'])
                ? $data['value']
                : json_encode($data['value']);

            if (str_contains(strtolower($content), $queryLower)) {
                $results->push([
                    'key' => $key,
                    'content' => $data['value'],
                    'score' => 1.0, // No semantic scoring for session driver
                    'metadata' => $data['metadata'],
                ]);
            }
        }

        return $results->take($limit);
    }

    /**
     * Forget a specific key.
     */
    public function forget(string $key): void
    {
        unset($this->storage[$key]);
    }

    /**
     * Clear all memory.
     */
    public function clear(): void
    {
        $this->storage = [];
        $this->history = [];
    }

    /**
     * Get conversation history.
     *
     * @return array<int, Message>
     */
    public function getConversationHistory(int $limit = 50): array
    {
        return array_slice($this->history, -$limit);
    }

    /**
     * Add a message to conversation history.
     */
    public function addMessage(Message $message): void
    {
        $this->history[] = $message;
    }

    /**
     * Get the driver name.
     */
    public function getDriver(): string
    {
        return 'session';
    }

    /**
     * Get the namespace (not applicable for session driver).
     */
    public function getNamespace(): string
    {
        return 'session';
    }
}
