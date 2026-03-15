<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\Concerns\HasMemory;
use AgenticOrchestrator\Agents\Concerns\HasTeamScope;
use AgenticOrchestrator\Contracts\MemoryInterface;
use Illuminate\Support\Collection;

describe('HasMemory', function () {

    beforeEach(function () {
        $this->memoryAgent = new class
        {
            use HasMemory;
            use HasTeamScope;

            public bool $isSystem = false;

            protected array $memory = [
                'driver' => 'cache',
            ];

            public function getId(): string
            {
                return 'memory-test-agent';
            }

            public function getName(): string
            {
                return 'Memory Test Agent';
            }
        };
    });

    describe('withMemory', function () {
        it('sets a custom memory instance with fluent return', function () {
            $memory = Mockery::mock(MemoryInterface::class);

            $result = $this->memoryAgent->withMemory($memory);

            expect($result)->toBe($this->memoryAgent);
            expect($this->memoryAgent->getMemory())->toBe($memory);
        });
    });

    describe('getMemory', function () {
        it('returns the injected memory instance', function () {
            $memory = Mockery::mock(MemoryInterface::class);
            $this->memoryAgent->withMemory($memory);

            expect($this->memoryAgent->getMemory())->toBe($memory);
        });

        it('returns the same instance on subsequent calls', function () {
            $memory = Mockery::mock(MemoryInterface::class);
            $this->memoryAgent->withMemory($memory);

            $first = $this->memoryAgent->getMemory();
            $second = $this->memoryAgent->getMemory();

            expect($first)->toBe($second);
        });
    });

    describe('remember', function () {
        it('delegates to memory store method', function () {
            $memory = Mockery::mock(MemoryInterface::class);
            $memory->shouldReceive('store')
                ->once()
                ->with('key1', 'value1', ['tag' => 'test']);

            $this->memoryAgent->withMemory($memory);
            $this->memoryAgent->remember('key1', 'value1', ['tag' => 'test']);
        });
    });

    describe('recall', function () {
        it('delegates to memory recall method', function () {
            $memory = Mockery::mock(MemoryInterface::class);
            $memory->shouldReceive('recall')
                ->once()
                ->with('key1')
                ->andReturn('stored_value');

            $this->memoryAgent->withMemory($memory);

            expect($this->memoryAgent->recall('key1'))->toBe('stored_value');
        });
    });

    describe('forget', function () {
        it('delegates to memory forget method', function () {
            $memory = Mockery::mock(MemoryInterface::class);
            $memory->shouldReceive('forget')
                ->once()
                ->with('key1');

            $this->memoryAgent->withMemory($memory);
            $this->memoryAgent->forget('key1');
        });
    });

    describe('searchMemory', function () {
        it('delegates to memory search method', function () {
            $results = new Collection([
                ['key' => 'item1', 'content' => 'data', 'score' => 0.9, 'metadata' => []],
            ]);

            $memory = Mockery::mock(MemoryInterface::class);
            $memory->shouldReceive('search')
                ->once()
                ->with('query', 5)
                ->andReturn($results);

            $this->memoryAgent->withMemory($memory);

            expect($this->memoryAgent->searchMemory('query'))->toBe($results);
        });

        it('passes custom limit to search', function () {
            $results = new Collection;

            $memory = Mockery::mock(MemoryInterface::class);
            $memory->shouldReceive('search')
                ->once()
                ->with('query', 10)
                ->andReturn($results);

            $this->memoryAgent->withMemory($memory);

            expect($this->memoryAgent->searchMemory('query', 10))->toBe($results);
        });
    });

    describe('clearMemory', function () {
        it('delegates to memory clear method', function () {
            $memory = Mockery::mock(MemoryInterface::class);
            $memory->shouldReceive('clear')->once();

            $this->memoryAgent->withMemory($memory);
            $this->memoryAgent->clearMemory();
        });
    });
});
