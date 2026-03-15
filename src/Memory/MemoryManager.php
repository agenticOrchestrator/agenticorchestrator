<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Memory;

use AgenticOrchestrator\Contracts\MemoryInterface;
use AgenticOrchestrator\Embeddings\Contracts\EmbeddingProviderInterface;
use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Memory\Drivers\CacheDriver;
use AgenticOrchestrator\Memory\Drivers\DatabaseDriver;
use AgenticOrchestrator\Memory\Drivers\RagMemoryDriver;
use AgenticOrchestrator\Memory\Drivers\SessionDriver;
use AgenticOrchestrator\Memory\Drivers\VectorMemoryAdapter;
use AgenticOrchestrator\Memory\Drivers\VectorMemoryDriver;
use AgenticOrchestrator\MultiTenancy\TenantManager;
use AgenticOrchestrator\Rag\RagPipeline;
use Illuminate\Contracts\Container\Container;

/**
 * Manages memory driver instances.
 *
 * Provides a factory for creating memory instances with
 * different drivers (session, cache, database, vector, rag).
 */
class MemoryManager
{
    /**
     * Resolved driver instances.
     *
     * @var array<string, MemoryInterface>
     */
    protected array $drivers = [];

    /**
     * @param  Container  $container  Laravel container
     * @param  array<string, mixed>  $config  Memory configuration
     */
    public function __construct(
        protected Container $container,
        protected array $config = [],
    ) {}

    /**
     * Get a memory driver by name.
     */
    public function driver(?string $name = null): Memory
    {
        $name = $name ?? $this->getDefaultDriver();

        if (! isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->createDriver($name);
        }

        return new Memory($this->drivers[$name]);
    }

    /**
     * Create a driver instance.
     */
    protected function createDriver(string $name): MemoryInterface
    {
        $config = $this->config['drivers'][$name] ?? [];

        return match ($name) {
            'session' => new SessionDriver,
            'cache' => new CacheDriver(
                store: $config['store'] ?? 'default',
                ttl: $config['ttl'] ?? 3600,
                prefix: $config['prefix'] ?? 'agent_memory:',
            ),
            'database' => $this->createDatabaseDriver($config),
            'vector' => $this->createVectorDriver($config),
            'rag' => $this->createRagDriver($config),
            default => throw new \InvalidArgumentException("Unsupported memory driver: {$name}"),
        };
    }

    /**
     * Create database memory driver.
     *
     * @param  array<string, mixed>  $config
     */
    protected function createDatabaseDriver(array $config): MemoryInterface
    {
        $driver = new DatabaseDriver($config);

        if ($this->container->bound(TenantManager::class)) {
            $driver->setTenantManager($this->container->make(TenantManager::class));
        }

        return $driver;
    }

    /**
     * Create vector memory driver.
     *
     * Wraps the VectorMemoryDriver in an adapter that implements MemoryInterface.
     *
     * @param  array<string, mixed>  $config
     */
    protected function createVectorDriver(array $config): MemoryInterface
    {
        $embeddings = $this->container->make(EmbeddingProviderInterface::class);
        $store = $this->container->make(VectorStoreInterface::class);

        $vectorDriver = new VectorMemoryDriver($embeddings, $store);

        return new VectorMemoryAdapter($vectorDriver);
    }

    /**
     * Create RAG memory driver.
     *
     * Uses the RAG pipeline for document-aware semantic memory.
     *
     * @param  array<string, mixed>  $config
     */
    protected function createRagDriver(array $config): MemoryInterface
    {
        $pipeline = $this->container->bound(RagPipeline::class)
            ? $this->container->make(RagPipeline::class)
            : RagPipeline::make();

        $embeddings = $this->container->make(EmbeddingProviderInterface::class);
        $store = $this->container->make(VectorStoreInterface::class);

        return new RagMemoryDriver($pipeline, $embeddings, $store);
    }

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config['default'] ?? 'cache';
    }

    /**
     * Set the default driver name.
     */
    public function setDefaultDriver(string $name): void
    {
        $this->config['default'] = $name;
    }

    /**
     * Get all supported driver names.
     *
     * @return array<int, string>
     */
    public function getSupportedDrivers(): array
    {
        return ['session', 'cache', 'database', 'vector', 'rag'];
    }
}
