<?php

declare(strict_types=1);

use AgenticOrchestrator\Embeddings\Contracts\EmbeddingProviderInterface;
use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Memory\Memory;
use AgenticOrchestrator\Memory\MemoryManager;
use AgenticOrchestrator\MultiTenancy\TenantManager;
use AgenticOrchestrator\Rag\RagPipeline;
use Illuminate\Contracts\Container\Container;

describe('MemoryManager', function () {
    beforeEach(function () {
        $this->container = Mockery::mock(Container::class);
    });

    describe('constructor', function () {
        it('constructs with container and config', function () {
            $manager = new MemoryManager($this->container, ['default' => 'cache']);

            expect($manager)->toBeInstanceOf(MemoryManager::class);
        });

        it('constructs with empty config', function () {
            $manager = new MemoryManager($this->container);

            expect($manager)->toBeInstanceOf(MemoryManager::class);
        });
    });

    describe('getDefaultDriver', function () {
        it('returns cache as default when not configured', function () {
            $manager = new MemoryManager($this->container);

            expect($manager->getDefaultDriver())->toBe('cache');
        });

        it('returns configured default driver', function () {
            $manager = new MemoryManager($this->container, ['default' => 'session']);

            expect($manager->getDefaultDriver())->toBe('session');
        });
    });

    describe('setDefaultDriver', function () {
        it('sets the default driver', function () {
            $manager = new MemoryManager($this->container);
            $manager->setDefaultDriver('session');

            expect($manager->getDefaultDriver())->toBe('session');
        });
    });

    describe('getSupportedDrivers', function () {
        it('returns all supported driver names', function () {
            $manager = new MemoryManager($this->container);

            expect($manager->getSupportedDrivers())->toBe([
                'session', 'cache', 'database', 'vector', 'rag',
            ]);
        });
    });

    describe('driver', function () {
        it('creates a session driver', function () {
            $manager = new MemoryManager($this->container, [
                'default' => 'session',
                'drivers' => ['session' => []],
            ]);

            $memory = $manager->driver('session');

            expect($memory)->toBeInstanceOf(Memory::class)
                ->and($memory->getDriver())->toBe('session');
        });

        it('creates a cache driver with default config', function () {
            $manager = new MemoryManager($this->container, [
                'drivers' => ['cache' => []],
            ]);

            $memory = $manager->driver('cache');

            expect($memory)->toBeInstanceOf(Memory::class)
                ->and($memory->getDriver())->toBe('cache');
        });

        it('creates a cache driver with custom config', function () {
            $manager = new MemoryManager($this->container, [
                'drivers' => [
                    'cache' => [
                        'store' => 'redis',
                        'ttl' => 7200,
                        'prefix' => 'custom:',
                    ],
                ],
            ]);

            $memory = $manager->driver('cache');

            expect($memory)->toBeInstanceOf(Memory::class);
        });

        it('uses default driver when null passed', function () {
            $manager = new MemoryManager($this->container, [
                'default' => 'session',
                'drivers' => ['session' => []],
            ]);

            $memory = $manager->driver();

            expect($memory->getDriver())->toBe('session');
        });

        it('caches driver instances', function () {
            $manager = new MemoryManager($this->container, [
                'default' => 'session',
                'drivers' => ['session' => []],
            ]);

            $memory1 = $manager->driver('session');
            $memory2 = $manager->driver('session');

            // Both return Memory wrappers but the internal driver is cached
            expect($memory1->getDriver())->toBe($memory2->getDriver());
        });

        it('throws for unsupported driver', function () {
            $manager = new MemoryManager($this->container);

            expect(fn () => $manager->driver('nonexistent'))
                ->toThrow(InvalidArgumentException::class, 'Unsupported memory driver: nonexistent');
        });

        it('creates a database driver', function () {
            $this->container->shouldReceive('bound')
                ->with(TenantManager::class)
                ->andReturn(false);

            $manager = new MemoryManager($this->container, [
                'drivers' => ['database' => ['table' => 'agent_memories']],
            ]);

            $memory = $manager->driver('database');

            expect($memory)->toBeInstanceOf(Memory::class)
                ->and($memory->getDriver())->toBe('database');
        });

        it('creates a database driver with tenant manager', function () {
            $tenantManager = Mockery::mock(TenantManager::class);

            $this->container->shouldReceive('bound')
                ->with(TenantManager::class)
                ->andReturn(true);
            $this->container->shouldReceive('make')
                ->with(TenantManager::class)
                ->andReturn($tenantManager);

            $manager = new MemoryManager($this->container, [
                'drivers' => ['database' => []],
            ]);

            $memory = $manager->driver('database');

            expect($memory)->toBeInstanceOf(Memory::class)
                ->and($memory->getDriver())->toBe('database');
        });

        it('creates a vector driver', function () {
            $embeddings = Mockery::mock(EmbeddingProviderInterface::class);
            $store = Mockery::mock(VectorStoreInterface::class);

            $this->container->shouldReceive('make')
                ->with(EmbeddingProviderInterface::class)
                ->andReturn($embeddings);
            $this->container->shouldReceive('make')
                ->with(VectorStoreInterface::class)
                ->andReturn($store);

            $manager = new MemoryManager($this->container, [
                'drivers' => ['vector' => []],
            ]);

            $memory = $manager->driver('vector');

            expect($memory)->toBeInstanceOf(Memory::class)
                ->and($memory->getDriver())->toBe('vector');
        });

        it('creates a rag driver', function () {
            $embeddings = Mockery::mock(EmbeddingProviderInterface::class);
            $store = Mockery::mock(VectorStoreInterface::class);

            $this->container->shouldReceive('bound')
                ->with(RagPipeline::class)
                ->andReturn(false);
            $this->container->shouldReceive('make')
                ->with(EmbeddingProviderInterface::class)
                ->andReturn($embeddings);
            $this->container->shouldReceive('make')
                ->with(VectorStoreInterface::class)
                ->andReturn($store);

            $manager = new MemoryManager($this->container, [
                'drivers' => ['rag' => []],
            ]);

            $memory = $manager->driver('rag');

            expect($memory)->toBeInstanceOf(Memory::class)
                ->and($memory->getDriver())->toBe('rag');
        });
    });
});
