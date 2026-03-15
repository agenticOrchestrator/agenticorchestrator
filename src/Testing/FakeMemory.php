<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Testing;

use AgenticOrchestrator\Contracts\MemoryInterface;
use AgenticOrchestrator\Conversations\Message;
use Illuminate\Support\Collection;
use PHPUnit\Framework\AssertionFailedError;

/**
 * Fake Memory - In-memory test double for memory drivers.
 *
 * @example
 * ```php
 * $memory = FakeMemory::make();
 * $memory->store('key', 'value');
 *
 * $memory->assertHas('key');
 * $memory->assertStored('key', 'value');
 * ```
 */
class FakeMemory implements MemoryInterface
{
    /** @var array<string, array{value: mixed, metadata: array<string, mixed>}> */
    protected array $storage = [];

    /** @var array<Message> */
    protected array $messages = [];

    protected string $namespace = 'fake';

    /**
     * Create a new fake memory.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Store a value in memory.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function store(string $key, mixed $value, array $metadata = []): void
    {
        $this->storage[$key] = [
            'value' => $value,
            'metadata' => $metadata,
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
     * Check if a key exists.
     */
    public function has(string $key): bool
    {
        return isset($this->storage[$key]);
    }

    /**
     * Search memory.
     *
     * @return Collection<int, array{key: string, content: mixed, score: float, metadata: array<string, mixed>}>
     */
    public function search(string $query, int $limit = 5): Collection
    {
        $results = [];
        $queryLower = strtolower($query);

        foreach ($this->storage as $key => $data) {
            $value = $data['value'];
            $content = is_string($value) ? $value : json_encode($value);

            if (str_contains(strtolower($content), $queryLower)) {
                $results[] = [
                    'key' => $key,
                    'content' => $value,
                    'score' => 1.0,
                    'metadata' => $data['metadata'],
                ];
            }
        }

        return collect(array_slice($results, 0, $limit));
    }

    /**
     * Forget a key.
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
        $this->messages = [];
    }

    /**
     * Get conversation history.
     *
     * @return array<int, Message>
     */
    public function getConversationHistory(int $limit = 50): array
    {
        return array_slice($this->messages, -$limit);
    }

    /**
     * Add a message to conversation history.
     */
    public function addMessage(Message $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * Get the memory driver name.
     */
    public function getDriver(): string
    {
        return 'fake';
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

    // === Assertion Methods ===

    /**
     * Assert the memory has a key.
     */
    public function assertHas(string $key): void
    {
        if (! $this->has($key)) {
            throw new AssertionFailedError(
                sprintf('Expected memory to have key "%s", but it did not.', $key)
            );
        }
    }

    /**
     * Assert a key was stored with specific value.
     */
    public function assertStored(string $key, mixed $expectedValue): void
    {
        $this->assertHas($key);

        $actual = $this->recall($key);

        if ($actual !== $expectedValue) {
            throw new AssertionFailedError(
                sprintf(
                    'Expected key "%s" to have value %s, but got %s.',
                    $key,
                    json_encode($expectedValue),
                    json_encode($actual)
                )
            );
        }
    }

    /**
     * Assert the memory does not have a key.
     */
    public function assertMissing(string $key): void
    {
        if ($this->has($key)) {
            throw new AssertionFailedError(
                sprintf('Expected memory to not have key "%s", but it did.', $key)
            );
        }
    }

    /**
     * Assert memory has a specific count of items.
     */
    public function assertCount(int $expected): void
    {
        $actual = count($this->storage);

        if ($actual !== $expected) {
            throw new AssertionFailedError(
                sprintf('Expected memory to have %d item(s), but it has %d.', $expected, $actual)
            );
        }
    }

    /**
     * Assert memory is empty.
     */
    public function assertEmpty(): void
    {
        if (! empty($this->storage)) {
            throw new AssertionFailedError(
                sprintf('Expected memory to be empty, but it has %d item(s).', count($this->storage))
            );
        }
    }

    /**
     * Assert conversation history has message count.
     */
    public function assertMessageCount(int $expected): void
    {
        $actual = count($this->messages);

        if ($actual !== $expected) {
            throw new AssertionFailedError(
                sprintf('Expected %d message(s), but have %d.', $expected, $actual)
            );
        }
    }

    /**
     * Assert search returns results.
     */
    public function assertSearchFinds(string $query, int $minResults = 1): void
    {
        $results = $this->search($query);

        if ($results->count() < $minResults) {
            throw new AssertionFailedError(
                sprintf(
                    'Expected search for "%s" to find at least %d result(s), but found %d.',
                    $query,
                    $minResults,
                    $results->count()
                )
            );
        }
    }

    /**
     * Get all stored keys.
     *
     * @return array<string>
     */
    public function getKeys(): array
    {
        return array_keys($this->storage);
    }

    /**
     * Get all stored values.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        $result = [];

        foreach ($this->storage as $key => $data) {
            $result[$key] = $data['value'];
        }

        return $result;
    }

    /**
     * Get all messages.
     *
     * @return array<Message>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Seed with data.
     *
     * @param  array<string, mixed>  $data
     */
    public function seed(array $data): static
    {
        foreach ($data as $key => $value) {
            $this->store($key, $value);
        }

        return $this;
    }

    /**
     * Reset all memory.
     */
    public function reset(): static
    {
        $this->storage = [];
        $this->messages = [];

        return $this;
    }
}
