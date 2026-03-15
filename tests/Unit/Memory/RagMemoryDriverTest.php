<?php

declare(strict_types=1);

use AgenticOrchestrator\Conversations\Message;
use AgenticOrchestrator\Conversations\MessageRole;
use AgenticOrchestrator\Embeddings\Contracts\EmbeddingProviderInterface;
use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Embeddings\VectorDocument;
use AgenticOrchestrator\Embeddings\VectorSearchResult;
use AgenticOrchestrator\Memory\Drivers\RagMemoryDriver;
use AgenticOrchestrator\Rag\RagPipeline;
use AgenticOrchestrator\Rag\RagPipelineResult;

describe('RagMemoryDriver', function () {
    beforeEach(function () {
        $this->pipeline = Mockery::mock(RagPipeline::class);
        $this->embeddings = Mockery::mock(EmbeddingProviderInterface::class);
        $this->store = Mockery::mock(VectorStoreInterface::class);

        $this->driver = new RagMemoryDriver(
            $this->pipeline,
            $this->embeddings,
            $this->store,
        );
    });

    describe('store', function () {
        it('embeds and upserts string content', function () {
            $this->embeddings->shouldReceive('embed')
                ->with('hello world')
                ->andReturn([0.1, 0.2, 0.3]);

            $this->store->shouldReceive('upsert')
                ->once()
                ->withArgs(function ($id, $embedding, $content, $metadata) {
                    return $id === 'default:greeting'
                        && $embedding === [0.1, 0.2, 0.3]
                        && $content === 'hello world'
                        && $metadata['key'] === 'greeting'
                        && $metadata['namespace'] === 'default'
                        && $metadata['type'] === 'memory';
                });

            $this->driver->store('greeting', 'hello world');
        });

        it('json encodes non-string values', function () {
            $this->embeddings->shouldReceive('embed')
                ->with('{"foo":"bar"}')
                ->andReturn([0.1]);

            $this->store->shouldReceive('upsert')->once();

            $this->driver->store('data', ['foo' => 'bar']);
        });
    });

    describe('recall', function () {
        it('returns decoded content from store', function () {
            $doc = new VectorDocument(
                id: 'default:key1',
                content: '{"name":"test"}',
                embedding: [0.1],
                metadata: [],
            );

            $this->store->shouldReceive('get')
                ->with('default:key1')
                ->andReturn($doc);

            $result = $this->driver->recall('key1');

            expect($result)->toBe(['name' => 'test']);
        });

        it('returns string content when not json', function () {
            $doc = new VectorDocument(
                id: 'default:key2',
                content: 'plain text',
                embedding: [0.1],
                metadata: [],
            );

            $this->store->shouldReceive('get')
                ->with('default:key2')
                ->andReturn($doc);

            expect($this->driver->recall('key2'))->toBe('plain text');
        });

        it('returns null when not found', function () {
            $this->store->shouldReceive('get')
                ->with('default:missing')
                ->andReturn(null);

            expect($this->driver->recall('missing'))->toBeNull();
        });
    });

    describe('has', function () {
        it('checks store existence with prefixed key', function () {
            $this->store->shouldReceive('exists')
                ->with('default:mykey')
                ->andReturn(true);

            expect($this->driver->has('mykey'))->toBeTrue();
        });

        it('returns false when not found', function () {
            $this->store->shouldReceive('exists')
                ->with('default:nope')
                ->andReturn(false);

            expect($this->driver->has('nope'))->toBeFalse();
        });
    });

    describe('forget', function () {
        it('deletes from store with prefixed key', function () {
            $this->store->shouldReceive('delete')
                ->with('default:old')
                ->once()
                ->andReturn(true);

            $this->driver->forget('old');
        });
    });

    describe('clear', function () {
        it('deletes by namespace filter and resets history', function () {
            $this->store->shouldReceive('deleteByFilter')
                ->with(['namespace' => 'default'])
                ->once()
                ->andReturn(5);

            $this->driver->clear();

            expect($this->driver->getConversationHistory())->toBe([]);
        });
    });

    describe('search', function () {
        it('queries pipeline and maps results', function () {
            $doc = new VectorDocument(
                id: 'doc1',
                content: 'test content',
                embedding: [0.1],
                metadata: ['source' => 'test.md'],
            );
            $searchResult = new VectorSearchResult(document: $doc, score: 0.95);

            $pipelineResult = Mockery::mock(RagPipelineResult::class);
            $pipelineResult->shouldReceive('getResults')
                ->andReturn([$searchResult]);

            $this->pipeline->shouldReceive('namespace')
                ->with('default')
                ->andReturnSelf();
            $this->pipeline->shouldReceive('limit')
                ->with(5)
                ->andReturnSelf();
            $this->pipeline->shouldReceive('query')
                ->with('search query')
                ->andReturn($pipelineResult);

            $results = $this->driver->search('search query', 5);

            expect($results)->toHaveCount(1);
            expect($results->first()['key'])->toBe('doc1');
            expect($results->first()['content'])->toBe('test content');
            expect($results->first()['score'])->toBe(0.95);
            expect($results->first()['metadata'])->toBe(['source' => 'test.md']);
        });
    });

    describe('conversation history', function () {
        it('stores and retrieves messages', function () {
            $this->embeddings->shouldReceive('embed')->andReturn([0.1]);
            $this->store->shouldReceive('upsert');

            $this->driver->addMessage(Message::user('hello'));
            $this->driver->addMessage(Message::assistant('hi there'));

            $history = $this->driver->getConversationHistory();

            expect($history)->toHaveCount(2);
            expect($history[0]->role)->toBe(MessageRole::User);
            expect($history[1]->role)->toBe(MessageRole::Assistant);
        });

        it('respects limit parameter', function () {
            $this->embeddings->shouldReceive('embed')->andReturn([0.1]);
            $this->store->shouldReceive('upsert');

            for ($i = 0; $i < 5; $i++) {
                $this->driver->addMessage(Message::user("msg {$i}"));
            }

            expect($this->driver->getConversationHistory(3))->toHaveCount(3);
        });
    });

    describe('driver info', function () {
        it('returns rag as driver name', function () {
            expect($this->driver->getDriver())->toBe('rag');
        });

        it('returns default namespace', function () {
            expect($this->driver->getNamespace())->toBe('default');
        });

        it('allows setting namespace', function () {
            $result = $this->driver->setNamespace('custom');

            expect($result)->toBeInstanceOf(RagMemoryDriver::class);
            expect($this->driver->getNamespace())->toBe('custom');
        });

        it('returns pipeline instance', function () {
            expect($this->driver->getPipeline())->toBe($this->pipeline);
        });
    });

    describe('namespace scoping', function () {
        it('uses namespace as key prefix', function () {
            $this->driver->setNamespace('team_42');

            $this->store->shouldReceive('exists')
                ->with('team_42:mykey')
                ->andReturn(true);

            expect($this->driver->has('mykey'))->toBeTrue();
        });
    });
});
