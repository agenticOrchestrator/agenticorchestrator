<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Agents\Concerns;

use AgenticOrchestrator\Contracts\MemoryInterface;
use AgenticOrchestrator\Memory\MemoryManager;
use Illuminate\Support\Collection;

/**
 * Provides memory capabilities for agents.
 */
trait HasMemory
{
    /**
     * The memory instance for this agent.
     */
    protected ?MemoryInterface $memoryInstance = null;

    /**
     * Get the memory instance for this agent.
     */
    public function getMemory(): MemoryInterface
    {
        if ($this->memoryInstance === null) {
            $this->memoryInstance = $this->createMemory();
        }

        return $this->memoryInstance;
    }

    /**
     * Create a memory instance based on configuration.
     */
    protected function createMemory(): MemoryInterface
    {
        /** @var MemoryManager $manager */
        $manager = app(MemoryManager::class);

        $config = $this->memory ?? ['driver' => 'cache'];
        $driver = $config['driver'] ?? 'cache';

        // Build namespace for team isolation
        $namespace = $this->buildMemoryNamespace();

        return $manager->driver($driver)->forNamespace($namespace);
    }

    /**
     * Build a unique namespace for this agent's memory.
     */
    protected function buildMemoryNamespace(): string
    {
        $parts = [];

        // Add team prefix if team-scoped
        if (method_exists($this, 'getTeamId') && $this->getTeamId() !== null) {
            $parts[] = 'team_'.$this->getTeamId();
        }

        // Add agent identifier
        $parts[] = 'agent_'.$this->getId();

        // Add user prefix if user-scoped
        if (method_exists($this, 'getUserId') && $this->getUserId() !== null) {
            $parts[] = 'user_'.$this->getUserId();
        }

        // Add custom namespace from config
        $customNamespace = $this->memory['namespace'] ?? null;
        if ($customNamespace !== null) {
            $parts[] = $customNamespace;
        }

        return implode(':', $parts);
    }

    /**
     * Set a custom memory instance.
     */
    public function withMemory(MemoryInterface $memory): static
    {
        $this->memoryInstance = $memory;

        return $this;
    }

    /**
     * Store a value in agent memory.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function remember(string $key, mixed $value, array $metadata = []): void
    {
        $this->getMemory()->store($key, $value, $metadata);
    }

    /**
     * Recall a value from agent memory.
     */
    public function recall(string $key): mixed
    {
        return $this->getMemory()->recall($key);
    }

    /**
     * Forget a value from agent memory.
     */
    public function forget(string $key): void
    {
        $this->getMemory()->forget($key);
    }

    /**
     * Search memory semantically.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function searchMemory(string $query, int $limit = 5): Collection
    {
        return $this->getMemory()->search($query, $limit);
    }

    /**
     * Clear all memory for this agent scope.
     */
    public function clearMemory(): void
    {
        $this->getMemory()->clear();
    }
}
