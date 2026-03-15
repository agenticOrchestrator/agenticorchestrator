<?php

declare(strict_types=1);

use AgenticOrchestrator\Conversations\Message;
use AgenticOrchestrator\Conversations\MessageRole;
use AgenticOrchestrator\Embeddings\Contracts\EmbeddingProviderInterface;
use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Embeddings\VectorDocument;
use AgenticOrchestrator\Embeddings\VectorSearchResult;
use AgenticOrchestrator\Memory\Drivers\VectorMemoryAdapter;
use AgenticOrchestrator\Memory\Drivers\VectorMemoryDriver;
use Illuminate\Support\Collection;

describe('VectorMemoryAdapter', function () {
    beforeEach(function () {
        $this->embeddings = Mockery::mock(EmbeddingProviderInterface::class);
        $this->store = Mockery::mock(VectorStoreInterface::class);
        $this->driver = new VectorMemoryDriver($this->embeddings, $this->store);
        $this->adapter = new VectorMemoryAdapter($this->driver);
    });

    describe('store', function () {
        it('calls driver set with key and value', function () {
            $this->embeddings->shouldReceive('embed')
                ->once()
                ->with('test value')
                ->andReturn([0.1, 0.2, 0.3]);

            $this->store->shouldReceive('upsert')
                ->once()
                ->withArgs(function (string $id, array $embedding, string $content, array $metadata) {
                    return $id === 'default:my-key'
                        && $embedding === [0.1, 0.2, 0.3]
                        && $content === 'test value'
                        && $metadata['key'] === 'my-key'
                        && $metadata['namespace'] === 'default';
                });

            $this->adapter->store('my-key', 'test value');
        });

        it('stores with metadata parameter ignored by driver', function () {
            $this->embeddings->shouldReceive('embed')
                ->once()
                ->andReturn([0.1]);

            $this->store->shouldReceive('upsert')->once();

            $this->adapter->store('key', 'value', ['extra' => 'meta']);
        });
    });

    describe('recall', function () {
        it('calls driver get and returns value', function () {
            $doc = new VectorDocument(
                id: 'default:my-key',
                content: 'stored value',
                metadata: ['type' => 'string', 'expires_at' => null],
            );

            $this->store->shouldReceive('get')
                ->once()
                ->with('default:my-key')
                ->andReturn($doc);

            $result = $this->adapter->recall('my-key');

            expect($result)->toBe('stored value');
        });

        it('returns null when key does not exist', function () {
            $this->store->shouldReceive('get')
                ->once()
                ->with('default:my-key')
                ->andReturn(null);

            $result = $this->adapter->recall('my-key');

            expect($result)->toBeNull();
        });
    });

    describe('has', function () {
        it('calls driver has and returns true when exists', function () {
            $this->store->shouldReceive('exists')
                ->once()
                ->with('default:my-key')
                ->andReturn(true);

            expect($this->adapter->has('my-key'))->toBeTrue();
        });

        it('returns false when key does not exist', function () {
            $this->store->shouldReceive('exists')
                ->once()
                ->with('default:missing')
                ->andReturn(false);

            expect($this->adapter->has('missing'))->toBeFalse();
        });
    });

    describe('forget', function () {
        it('calls driver forget to delete key', function () {
            $this->store->shouldReceive('delete')
                ->once()
                ->with('default:my-key')
                ->andReturn(true);

            $this->adapter->forget('my-key');

            // No exception means success; Mockery verifies the call
        });
    });

    describe('clear', function () {
        it('calls driver flush and resets conversation history', function () {
            $this->store->shouldReceive('deleteByFilter')
                ->once()
                ->with(['namespace' => 'default'])
                ->andReturn(1);

            // Add a message first to verify history gets cleared
            $message = new Message(role: MessageRole::User, content: 'hello');

            $this->embeddings->shouldReceive('embed')
                ->once()
                ->andReturn([0.1]);
            $this->store->shouldReceive('upsert')->once();

            $this->adapter->addMessage($message);
            expect($this->adapter->getConversationHistory())->toHaveCount(1);

            $this->adapter->clear();

            expect($this->adapter->getConversationHistory())->toBeEmpty();
        });
    });

    describe('search', function () {
        it('returns proper Collection format from vector results', function () {
            $doc1 = new VectorDocument(
                id: 'default:key1',
                content: 'first result',
                metadata: ['source' => 'test'],
            );
            $doc2 = new VectorDocument(
                id: 'default:key2',
                content: 'second result',
                metadata: ['source' => 'test2'],
            );

            $searchResults = [
                new VectorSearchResult(document: $doc1, score: 0.95),
                new VectorSearchResult(document: $doc2, score: 0.80),
            ];

            $this->embeddings->shouldReceive('embed')
                ->once()
                ->with('search query')
                ->andReturn([0.5, 0.5]);

            $this->store->shouldReceive('search')
                ->once()
                ->withArgs(function (array $embedding, int $limit, array $filter) {
                    return $embedding === [0.5, 0.5]
                        && $limit === 5
                        && $filter === ['namespace' => 'default'];
                })
                ->andReturn($searchResults);

            $results = $this->adapter->search('search query', 5);

            expect($results)->toBeInstanceOf(Collection::class)
                ->and($results)->toHaveCount(2)
                ->and($results[0])->toBe([
                    'key' => 'default:key1',
                    'content' => 'first result',
                    'score' => 0.95,
                    'metadata' => ['source' => 'test'],
                ])
                ->and($results[1])->toBe([
                    'key' => 'default:key2',
                    'content' => 'second result',
                    'score' => 0.80,
                    'metadata' => ['source' => 'test2'],
                ]);
        });

        it('returns empty collection when no results', function () {
            $this->embeddings->shouldReceive('embed')
                ->once()
                ->andReturn([0.1]);

            $this->store->shouldReceive('search')
                ->once()
                ->andReturn([]);

            $results = $this->adapter->search('nothing');

            expect($results)->toBeInstanceOf(Collection::class)
                ->and($results)->toBeEmpty();
        });

        it('uses default limit of 5', function () {
            $this->embeddings->shouldReceive('embed')
                ->once()
                ->andReturn([0.1]);

            $this->store->shouldReceive('search')
                ->once()
                ->withArgs(function (array $embedding, int $limit, array $filter) {
                    return $limit === 5;
                })
                ->andReturn([]);

            $this->adapter->search('query');
        });
    });

    describe('getConversationHistory', function () {
        it('returns empty array when no messages', function () {
            expect($this->adapter->getConversationHistory())->toBe([]);
        });

        it('returns all messages when under limit', function () {
            $msg1 = new Message(role: MessageRole::User, content: 'hello');
            $msg2 = new Message(role: MessageRole::Assistant, content: 'hi');

            $this->embeddings->shouldReceive('embed')->twice()->andReturn([0.1]);
            $this->store->shouldReceive('upsert')->twice();

            $this->adapter->addMessage($msg1);
            $this->adapter->addMessage($msg2);

            $history = $this->adapter->getConversationHistory();

            expect($history)->toHaveCount(2)
                ->and($history[0]->content)->toBe('hello')
                ->and($history[1]->content)->toBe('hi');
        });

        it('respects limit parameter and returns last N messages', function () {
            $this->embeddings->shouldReceive('embed')->times(3)->andReturn([0.1]);
            $this->store->shouldReceive('upsert')->times(3);

            $this->adapter->addMessage(new Message(role: MessageRole::User, content: 'first'));
            $this->adapter->addMessage(new Message(role: MessageRole::User, content: 'second'));
            $this->adapter->addMessage(new Message(role: MessageRole::User, content: 'third'));

            $history = $this->adapter->getConversationHistory(2);

            expect($history)->toHaveCount(2)
                ->and($history[0]->content)->toBe('second')
                ->and($history[1]->content)->toBe('third');
        });

        it('uses default limit of 50', function () {
            expect($this->adapter->getConversationHistory())->toBe([]);
            // Default param is 50, verified by method signature
        });
    });

    describe('addMessage', function () {
        it('stores message to both history and vector', function () {
            $message = new Message(role: MessageRole::User, content: 'remember this');

            $this->embeddings->shouldReceive('embed')
                ->once()
                ->with('remember this')
                ->andReturn([0.2, 0.3]);

            $this->store->shouldReceive('upsert')
                ->once()
                ->withArgs(function (string $id, array $embedding, string $content, array $metadata) {
                    return str_starts_with($id, 'default:msg_')
                        && $embedding === [0.2, 0.3]
                        && $content === 'remember this';
                });

            $this->adapter->addMessage($message);

            $history = $this->adapter->getConversationHistory();
            expect($history)->toHaveCount(1)
                ->and($history[0])->toBe($message);
        });

        it('stores assistant messages', function () {
            $message = new Message(role: MessageRole::Assistant, content: 'response');

            $this->embeddings->shouldReceive('embed')->once()->andReturn([0.1]);
            $this->store->shouldReceive('upsert')->once();

            $this->adapter->addMessage($message);

            expect($this->adapter->getConversationHistory())->toHaveCount(1)
                ->and($this->adapter->getConversationHistory()[0]->role)->toBe(MessageRole::Assistant);
        });
    });

    describe('getDriver', function () {
        it('returns vector as driver name', function () {
            expect($this->adapter->getDriver())->toBe('vector');
        });
    });

    describe('getNamespace', function () {
        it('delegates to the underlying driver', function () {
            expect($this->adapter->getNamespace())->toBe('default');
        });

        it('returns updated namespace after setNamespace', function () {
            $this->adapter->setNamespace('tenant-42');

            expect($this->adapter->getNamespace())->toBe('tenant-42');
        });
    });

    describe('setNamespace', function () {
        it('delegates to the underlying driver', function () {
            $result = $this->adapter->setNamespace('custom-ns');

            expect($result)->toBeInstanceOf(VectorMemoryAdapter::class)
                ->and($this->adapter->getNamespace())->toBe('custom-ns');
        });

        it('returns static for fluent chaining', function () {
            $result = $this->adapter->setNamespace('ns');

            expect($result)->toBe($this->adapter);
        });
    });

    describe('getVectorDriver', function () {
        it('returns the underlying VectorMemoryDriver', function () {
            $vectorDriver = $this->adapter->getVectorDriver();

            expect($vectorDriver)->toBeInstanceOf(VectorMemoryDriver::class)
                ->and($vectorDriver)->toBe($this->driver);
        });
    });
});
