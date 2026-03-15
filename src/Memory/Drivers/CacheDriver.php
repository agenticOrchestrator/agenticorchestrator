<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Memory\Drivers;

use AgenticOrchestrator\Contracts\MemoryInterface;
use AgenticOrchestrator\Conversations\Message;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Cache-based memory driver.
 *
 * Uses Laravel's cache system for persistence.
 * Supports TTL-based expiration.
 */
class CacheDriver implements MemoryInterface
{
    /**
     * Current namespace.
     */
    protected string $namespace = 'default';

    /**
     * @param  string  $store  Cache store name
     * @param  int  $ttl  Time-to-live in seconds
     * @param  string  $prefix  Key prefix
     */
    public function __construct(
        protected string $store = 'default',
        protected int $ttl = 3600,
        protected string $prefix = 'agent_memory:',
    ) {}

    /**
     * Store a value in memory.
     */
    public function store(string $key, mixed $value, array $metadata = []): void
    {
        $data = [
            'value' => $value,
            'metadata' => $metadata,
            'timestamp' => time(),
        ];

        $this->cache()->put(
            $this->buildKey($key),
            $data,
            $this->ttl
        );

        // Track keys for clear() operation
        $this->trackKey($key);
    }

    /**
     * Recall a value from memory.
     */
    public function recall(string $key): mixed
    {
        $data = $this->cache()->get($this->buildKey($key));

        return $data['value'] ?? null;
    }

    /**
     * Check if a key exists in memory.
     */
    public function has(string $key): bool
    {
        return $this->cache()->has($this->buildKey($key));
    }

    /**
     * Search memory (basic for cache driver).
     */
    public function search(string $query, int $limit = 5): Collection
    {
        // Cache driver doesn't support semantic search
        // Falls back to checking tracked keys
        $results = collect();
        $keys = $this->getTrackedKeys();
        $queryLower = strtolower($query);

        foreach ($keys as $key) {
            $data = $this->cache()->get($this->buildKey($key));
            if ($data === null) {
                continue;
            }

            $content = is_string($data['value'])
                ? $data['value']
                : json_encode($data['value']);

            if (str_contains(strtolower($content), $queryLower)) {
                $results->push([
                    'key' => $key,
                    'content' => $data['value'],
                    'score' => 1.0,
                    'metadata' => $data['metadata'] ?? [],
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
        $this->cache()->forget($this->buildKey($key));
        $this->untrackKey($key);
    }

    /**
     * Clear all memory for this namespace.
     */
    public function clear(): void
    {
        $keys = $this->getTrackedKeys();

        foreach ($keys as $key) {
            $this->cache()->forget($this->buildKey($key));
        }

        // Clear history
        $this->cache()->forget($this->buildKey('_history'));

        // Clear tracked keys
        $this->cache()->forget($this->buildKey('_tracked_keys'));
    }

    /**
     * Get conversation history.
     *
     * @return array<int, Message>
     */
    public function getConversationHistory(int $limit = 50): array
    {
        $history = $this->cache()->get($this->buildKey('_history'), []);

        // Convert arrays back to Message objects
        $messages = array_map(
            fn ($data) => Message::fromArray($data),
            $history
        );

        return array_slice($messages, -$limit);
    }

    /**
     * Add a message to conversation history.
     */
    public function addMessage(Message $message): void
    {
        $history = $this->cache()->get($this->buildKey('_history'), []);
        $history[] = $message->toArray();

        // Keep last 100 messages max
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }

        $this->cache()->put(
            $this->buildKey('_history'),
            $history,
            $this->ttl
        );
    }

    /**
     * Get the driver name.
     */
    public function getDriver(): string
    {
        return 'cache';
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
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * Get the cache instance.
     */
    protected function cache(): Repository
    {
        return Cache::store($this->store === 'default' ? null : $this->store);
    }

    /**
     * Build a prefixed key.
     */
    protected function buildKey(string $key): string
    {
        return "{$this->prefix}{$this->namespace}:{$key}";
    }

    /**
     * Track a key for clear() operation.
     */
    protected function trackKey(string $key): void
    {
        $tracked = $this->getTrackedKeys();

        if (! in_array($key, $tracked, true)) {
            $tracked[] = $key;
            $this->cache()->put(
                $this->buildKey('_tracked_keys'),
                $tracked,
                $this->ttl * 2 // Keep longer than data
            );
        }
    }

    /**
     * Untrack a key.
     */
    protected function untrackKey(string $key): void
    {
        $tracked = $this->getTrackedKeys();
        $tracked = array_filter($tracked, fn ($k) => $k !== $key);

        $this->cache()->put(
            $this->buildKey('_tracked_keys'),
            array_values($tracked),
            $this->ttl * 2
        );
    }

    /**
     * Get all tracked keys.
     *
     * @return array<int, string>
     */
    protected function getTrackedKeys(): array
    {
        return $this->cache()->get($this->buildKey('_tracked_keys'), []);
    }
}
