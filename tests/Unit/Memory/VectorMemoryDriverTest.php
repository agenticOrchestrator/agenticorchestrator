<?php

declare(strict_types=1);

use AgenticOrchestrator\Embeddings\Contracts\EmbeddingProviderInterface;
use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Embeddings\VectorDocument;
use AgenticOrchestrator\Embeddings\VectorSearchResult;
use AgenticOrchestrator\Memory\Drivers\VectorMemoryDriver;

beforeEach(function () {
    $this->embeddings = Mockery::mock(EmbeddingProviderInterface::class);
    $this->store = Mockery::mock(VectorStoreInterface::class);
    $this->driver = new VectorMemoryDriver($this->embeddings, $this->store);
});

afterEach(function () {
    Mockery::close();
});

describe('set() method', function () {
    test('sets string value without TTL', function () {
        $key = 'test_key';
        $value = 'test value';
        $embedding = [0.1, 0.2, 0.3];

        $this->embeddings->shouldReceive('embed')
            ->once()
            ->with($value)
            ->andReturn($embedding);

        $this->store->shouldReceive('upsert')
            ->once()
            ->withArgs(function ($id, $emb, $content, $metadata) use ($key, $value, $embedding) {
                return $id === "default:{$key}"
                    && $emb === $embedding
                    && $content === $value
                    && $metadata['key'] === $key
                    && $metadata['namespace'] === 'default'
                    && $metadata['type'] === 'string'
                    && isset($metadata['created_at'])
                    && $metadata['expires_at'] === null;
            });

        $this->driver->set($key, $value);
    });

    test('sets string value with TTL', function () {
        $key = 'test_key';
        $value = 'test value';
        $ttl = 3600;
        $embedding = [0.1, 0.2, 0.3];

        $this->embeddings->shouldReceive('embed')
            ->once()
            ->with($value)
            ->andReturn($embedding);

        $this->store->shouldReceive('upsert')
            ->once()
            ->withArgs(function ($id, $emb, $content, $metadata) use ($key, $value, $embedding) {
                return $id === "default:{$key}"
                    && $emb === $embedding
                    && $content === $value
                    && $metadata['key'] === $key
                    && $metadata['namespace'] === 'default'
                    && $metadata['type'] === 'string'
                    && isset($metadata['created_at'])
                    && $metadata['expires_at'] !== null;
            });

        $this->driver->set($key, $value, $ttl);
    });

    test('sets array value', function () {
        $key = 'test_key';
        $value = ['foo' => 'bar', 'baz' => 123];
        $serialized = json_encode($value);
        $embedding = [0.1, 0.2, 0.3];

        $this->embeddings->shouldReceive('embed')
            ->once()
            ->with($serialized)
            ->andReturn($embedding);

        $this->store->shouldReceive('upsert')
            ->once()
            ->withArgs(function ($id, $emb, $content, $metadata) use ($key, $serialized, $embedding) {
                return $id === "default:{$key}"
                    && $emb === $embedding
                    && $content === $serialized
                    && $metadata['key'] === $key
                    && $metadata['type'] === 'array';
            });

        $this->driver->set($key, $value);
    });

    test('sets integer value', function () {
        $key = 'test_key';
        $value = 42;
        $serialized = json_encode($value);
        $embedding = [0.1, 0.2, 0.3];

        $this->embeddings->shouldReceive('embed')
            ->once()
            ->with($serialized)
            ->andReturn($embedding);

        $this->store->shouldReceive('upsert')
            ->once()
            ->withArgs(function ($id, $emb, $content, $metadata) {
                return $metadata['type'] === 'integer';
            });

        $this->driver->set($key, $value);
    });

    test('sets boolean value', function () {
        $key = 'test_key';
        $value = true;
        $serialized = json_encode($value);
        $embedding = [0.1, 0.2, 0.3];

        $this->embeddings->shouldReceive('embed')
            ->once()
            ->with($serialized)
            ->andReturn($embedding);

        $this->store->shouldReceive('upsert')
            ->once()
            ->withArgs(function ($id, $emb, $content, $metadata) {
                return $metadata['type'] === 'boolean';
            });

        $this->driver->set($key, $value);
    });

    test('sets double value', function () {
        $key = 'test_key';
        $value = 3.14;
        $serialized = json_encode($value);
        $embedding = [0.1, 0.2, 0.3];

        $this->embeddings->shouldReceive('embed')
            ->once()
            ->with($serialized)
            ->andReturn($embedding);

        $this->store->shouldReceive('upsert')
            ->once()
            ->withArgs(function ($id, $emb, $content, $metadata) {
                return $metadata['type'] === 'double';
            });

        $this->driver->set($key, $value);
    });
});

describe('get() method', function () {
    test('returns value when key exists', function () {
        $key = 'test_key';
        $value = 'test value';
        $doc = new VectorDocument(
            id: "default:{$key}",
            content: $value,
            embedding: [0.1, 0.2, 0.3],
            metadata: [
                'key' => $key,
                'namespace' => 'default',
                'type' => 'string',
                'created_at' => now()->toISOString(),
                'expires_at' => null,
            ]
        );

        $this->store->shouldReceive('get')
            ->once()
            ->with("default:{$key}")
            ->andReturn($doc);

        $result = $this->driver->get($key);

        expect($result)->toBe($value);
    });

    test('returns default value when key does not exist', function () {
        $key = 'nonexistent_key';
        $default = 'default value';

        $this->store->shouldReceive('get')
            ->once()
            ->with("default:{$key}")
            ->andReturn(null);

        $result = $this->driver->get($key, $default);

        expect($result)->toBe($default);
    });

    test('returns default value when key is expired', function () {
        $key = 'expired_key';
        $default = 'default value';
        $doc = new VectorDocument(
            id: "default:{$key}",
            content: 'old value',
            embedding: [0.1, 0.2, 0.3],
            metadata: [
                'key' => $key,
                'namespace' => 'default',
                'type' => 'string',
                'created_at' => now()->subHours(2)->toISOString(),
                'expires_at' => now()->subHour()->toISOString(),
            ]
        );

        $this->store->shouldReceive('get')
            ->once()
            ->with("default:{$key}")
            ->andReturn($doc);

        $this->store->shouldReceive('delete')
            ->once()
            ->with("default:{$key}")
            ->andReturn(true);

        $result = $this->driver->get($key, $default);

        expect($result)->toBe($default);
    });

    test('deserializes integer value', function () {
        $key = 'test_key';
        $value = 42;
        $doc = new VectorDocument(
            id: "default:{$key}",
            content: '42',
            embedding: [0.1, 0.2, 0.3],
            metadata: [
                'key' => $key,
                'namespace' => 'default',
                'type' => 'integer',
                'created_at' => now()->toISOString(),
                'expires_at' => null,
            ]
        );

        $this->store->shouldReceive('get')
            ->once()
            ->with("default:{$key}")
            ->andReturn($doc);

        $result = $this->driver->get($key);

        expect($result)->toBe($value);
    });

    test('deserializes double value', function () {
        $key = 'test_key';
        $value = 3.14;
        $doc = new VectorDocument(
            id: "default:{$key}",
            content: '3.14',
            embedding: [0.1, 0.2, 0.3],
            metadata: [
                'key' => $key,
                'namespace' => 'default',
                'type' => 'double',
                'created_at' => now()->toISOString(),
                'expires_at' => null,
            ]
        );

        $this->store->shouldReceive('get')
            ->once()
            ->with("default:{$key}")
            ->andReturn($doc);

        $result = $this->driver->get($key);

        expect($result)->toBe($value);
    });

    test('deserializes boolean true value', function () {
        $key = 'test_key';
        $doc = new VectorDocument(
            id: "default:{$key}",
            content: 'true',
            embedding: [0.1, 0.2, 0.3],
            metadata: [
                'key' => $key,
                'namespace' => 'default',
                'type' => 'boolean',
                'created_at' => now()->toISOString(),
                'expires_at' => null,
            ]
        );

        $this->store->shouldReceive('get')
            ->once()
            ->with("default:{$key}")
            ->andReturn($doc);

        $result = $this->driver->get($key);

        expect($result)->toBeTrue();
    });

    test('deserializes boolean false value', function () {
        $key = 'test_key';
        $doc = new VectorDocument(
            id: "default:{$key}",
            content: 'false',
            embedding: [0.1, 0.2, 0.3],
            metadata: [
                'key' => $key,
                'namespace' => 'default',
                'type' => 'boolean',
                'created_at' => now()->toISOString(),
                'expires_at' => null,
            ]
        );

        $this->store->shouldReceive('get')
            ->once()
            ->with("default:{$key}")
            ->andReturn($doc);

        $result = $this->driver->get($key);

        expect($result)->toBeFalse();
    });

    test('deserializes boolean value from 1', function () {
        $key = 'test_key';
        $doc = new VectorDocument(
            id: "default:{$key}",
            content: '1',
            embedding: [0.1, 0.2, 0.3],
            metadata: [
                'key' => $key,
                'namespace' => 'default',
                'type' => 'boolean',
                'created_at' => now()->toISOString(),
                'expires_at' => null,
            ]
        );

        $this->store->shouldReceive('get')
            ->once()
            ->with("default:{$key}")
            ->andReturn($doc);

        $result = $this->driver->get($key);

        expect($result)->toBeTrue();
    });

    test('deserializes array value', function () {
        $key = 'test_key';
        $value = ['foo' => 'bar', 'baz' => 123];
        $doc = new VectorDocument(
            id: "default:{$key}",
            content: json_encode($value),
            embedding: [0.1, 0.2, 0.3],
            metadata: [
                'key' => $key,
                'namespace' => 'default',
                'type' => 'array',
                'created_at' => now()->toISOString(),
                'expires_at' => null,
            ]
        );

        $this->store->shouldReceive('get')
            ->once()
            ->with("default:{$key}")
            ->andReturn($doc);

        $result = $this->driver->get($key);

        expect($result)->toBe($value);
    });

    test('deserializes object value', function () {
        $key = 'test_key';
        $value = ['foo' => 'bar', 'baz' => 123];
        $doc = new VectorDocument(
            id: "default:{$key}",
            content: json_encode($value),
            embedding: [0.1, 0.2, 0.3],
            metadata: [
                'key' => $key,
                'namespace' => 'default',
                'type' => 'object',
                'created_at' => now()->toISOString(),
                'expires_at' => null,
            ]
        );

        $this->store->shouldReceive('get')
            ->once()
            ->with("default:{$key}")
            ->andReturn($doc);

        $result = $this->driver->get($key);

        expect($result)->toBe($value);
    });

    test('deserializes unknown type as string', function () {
        $key = 'test_key';
        $value = 'some content';
        $doc = new VectorDocument(
            id: "default:{$key}",
            content: $value,
            embedding: [0.1, 0.2, 0.3],
            metadata: [
                'key' => $key,
                'namespace' => 'default',
                'type' => 'unknown',
                'created_at' => now()->toISOString(),
                'expires_at' => null,
            ]
        );

        $this->store->shouldReceive('get')
            ->once()
            ->with("default:{$key}")
            ->andReturn($doc);

        $result = $this->driver->get($key);

        expect($result)->toBe($value);
    });
});

describe('has() method', function () {
    test('returns true when key exists', function () {
        $key = 'test_key';

        $this->store->shouldReceive('exists')
            ->once()
            ->with("default:{$key}")
            ->andReturn(true);

        $result = $this->driver->has($key);

        expect($result)->toBeTrue();
    });

    test('returns false when key does not exist', function () {
        $key = 'nonexistent_key';

        $this->store->shouldReceive('exists')
            ->once()
            ->with("default:{$key}")
            ->andReturn(false);

        $result = $this->driver->has($key);

        expect($result)->toBeFalse();
    });
});

describe('forget() method', function () {
    test('deletes key and returns true', function () {
        $key = 'test_key';

        $this->store->shouldReceive('delete')
            ->once()
            ->with("default:{$key}")
            ->andReturn(true);

        $result = $this->driver->forget($key);

        expect($result)->toBeTrue();
    });

    test('returns false when deletion fails', function () {
        $key = 'test_key';

        $this->store->shouldReceive('delete')
            ->once()
            ->with("default:{$key}")
            ->andReturn(false);

        $result = $this->driver->forget($key);

        expect($result)->toBeFalse();
    });
});

describe('flush() method', function () {
    test('deletes all keys in namespace', function () {
        $this->store->shouldReceive('deleteByFilter')
            ->once()
            ->with(['namespace' => 'default']);

        $this->driver->flush();
    });

    test('deletes all keys in custom namespace', function () {
        $this->store->shouldReceive('deleteByFilter')
            ->once()
            ->with(['namespace' => 'custom']);

        $this->driver->namespace('custom')->flush();
    });
});

describe('keys() method', function () {
    test('returns empty array', function () {
        $result = $this->driver->keys();

        expect($result)->toBe([]);
    });
});

describe('search() method', function () {
    test('searches and filters by threshold', function () {
        $query = 'test query';
        $limit = 5;
        $threshold = 0.7;
        $embedding = [0.1, 0.2, 0.3];

        $this->embeddings->shouldReceive('embed')
            ->once()
            ->with($query)
            ->andReturn($embedding);

        $results = [
            new VectorSearchResult(new VectorDocument('id1', 'content1', $embedding, ['key' => 'key1']), 0.9),
            new VectorSearchResult(new VectorDocument('id2', 'content2', $embedding, ['key' => 'key2']), 0.8),
            new VectorSearchResult(new VectorDocument('id3', 'content3', $embedding, ['key' => 'key3']), 0.6),
            new VectorSearchResult(new VectorDocument('id4', 'content4', $embedding, ['key' => 'key4']), 0.5),
        ];

        $this->store->shouldReceive('search')
            ->once()
            ->withArgs(function ($emb, $lim, $filter) use ($embedding, $limit) {
                return $emb === $embedding
                    && $lim === $limit
                    && $filter === ['namespace' => 'default'];
            })
            ->andReturn($results);

        $filtered = $this->driver->search($query, $limit, $threshold);

        expect($filtered)->toHaveCount(2);
        expect($filtered[0]->score)->toBe(0.9);
        expect($filtered[1]->score)->toBe(0.8);
    });

    test('searches with custom limit', function () {
        $query = 'test query';
        $limit = 10;
        $threshold = 0.5;
        $embedding = [0.1, 0.2, 0.3];

        $this->embeddings->shouldReceive('embed')
            ->once()
            ->with($query)
            ->andReturn($embedding);

        $this->store->shouldReceive('search')
            ->once()
            ->withArgs(function ($emb, $lim, $filter) use ($limit) {
                return $lim === $limit;
            })
            ->andReturn([]);

        $this->driver->search($query, $limit, $threshold);
    });

    test('searches in custom namespace', function () {
        $query = 'test query';
        $embedding = [0.1, 0.2, 0.3];

        $this->embeddings->shouldReceive('embed')
            ->once()
            ->with($query)
            ->andReturn($embedding);

        $this->store->shouldReceive('search')
            ->once()
            ->withArgs(function ($emb, $lim, $filter) {
                return $filter === ['namespace' => 'custom'];
            })
            ->andReturn([]);

        $this->driver->namespace('custom')->search($query);
    });
});

describe('remember() method', function () {
    test('creates memory and returns key', function () {
        $content = 'important memory';
        $metadata = ['category' => 'important'];
        $embedding = [0.1, 0.2, 0.3];

        $this->embeddings->shouldReceive('embed')
            ->once()
            ->with($content)
            ->andReturn($embedding);

        $this->store->shouldReceive('upsert')
            ->once()
            ->withArgs(function ($id, $emb, $cont, $meta) use ($content, $embedding) {
                return str_starts_with($id, 'default:mem_')
                    && $emb === $embedding
                    && $cont === $content
                    && isset($meta['key'])
                    && str_starts_with($meta['key'], 'mem_')
                    && $meta['namespace'] === 'default'
                    && $meta['type'] === 'memory'
                    && $meta['category'] === 'important'
                    && isset($meta['created_at']);
            });

        $key = $this->driver->remember($content, $metadata);

        expect($key)->toStartWith('mem_');
        expect(strlen($key))->toBe(20); // 'mem_' + 16 random chars
    });

    test('creates memory without metadata', function () {
        $content = 'important memory';
        $embedding = [0.1, 0.2, 0.3];

        $this->embeddings->shouldReceive('embed')
            ->once()
            ->with($content)
            ->andReturn($embedding);

        $this->store->shouldReceive('upsert')
            ->once()
            ->withArgs(function ($id, $emb, $cont, $meta) {
                return $meta['type'] === 'memory';
            });

        $key = $this->driver->remember($content);

        expect($key)->toStartWith('mem_');
    });
});

describe('setMany() method', function () {
    test('upserts multiple values in batch', function () {
        $memories = [
            'key1' => 'value1',
            'key2' => ['foo' => 'bar'],
            'key3' => 42,
        ];

        $this->embeddings->shouldReceive('embed')
            ->times(3)
            ->andReturn([0.1, 0.2, 0.3]);

        $this->store->shouldReceive('upsertBatch')
            ->once()
            ->withArgs(function ($documents) {
                expect($documents)->toHaveCount(3);
                expect($documents[0])->toBeInstanceOf(VectorDocument::class);
                expect($documents[0]->id)->toBe('default:key1');
                expect($documents[0]->content)->toBe('value1');
                expect($documents[1]->id)->toBe('default:key2');
                expect($documents[1]->content)->toBe(json_encode(['foo' => 'bar']));
                expect($documents[2]->id)->toBe('default:key3');
                expect($documents[2]->content)->toBe(json_encode(42));

                return true;
            });

        $this->driver->setMany($memories);
    });

    test('handles empty array', function () {
        $this->store->shouldReceive('upsertBatch')
            ->once()
            ->with([]);

        $this->driver->setMany([]);
    });
});

describe('getMany() method', function () {
    test('retrieves multiple values', function () {
        $keys = ['key1', 'key2', 'key3'];

        $doc1 = new VectorDocument(
            id: 'default:key1',
            content: 'value1',
            embedding: [0.1, 0.2, 0.3],
            metadata: ['key' => 'key1', 'namespace' => 'default', 'type' => 'string', 'created_at' => now()->toISOString(), 'expires_at' => null]
        );

        $doc2 = new VectorDocument(
            id: 'default:key2',
            content: json_encode(['foo' => 'bar']),
            embedding: [0.1, 0.2, 0.3],
            metadata: ['key' => 'key2', 'namespace' => 'default', 'type' => 'array', 'created_at' => now()->toISOString(), 'expires_at' => null]
        );

        $this->store->shouldReceive('get')
            ->once()
            ->with('default:key1')
            ->andReturn($doc1);

        $this->store->shouldReceive('get')
            ->once()
            ->with('default:key2')
            ->andReturn($doc2);

        $this->store->shouldReceive('get')
            ->once()
            ->with('default:key3')
            ->andReturn(null);

        $result = $this->driver->getMany($keys);

        expect($result)->toBe([
            'key1' => 'value1',
            'key2' => ['foo' => 'bar'],
            'key3' => null,
        ]);
    });

    test('handles empty array', function () {
        $result = $this->driver->getMany([]);

        expect($result)->toBe([]);
    });
});

describe('similar() method', function () {
    test('finds similar documents', function () {
        $key = 'test_key';
        $embedding = [0.1, 0.2, 0.3];

        $doc = new VectorDocument(
            id: 'default:test_key',
            content: 'test content',
            embedding: $embedding,
            metadata: ['key' => $key, 'namespace' => 'default', 'type' => 'string', 'created_at' => now()->toISOString(), 'expires_at' => null]
        );

        $this->store->shouldReceive('get')
            ->once()
            ->with('default:test_key')
            ->andReturn($doc);

        $results = [
            new VectorSearchResult(new VectorDocument('default:test_key', 'test content', $embedding, ['key' => 'test_key']), 1.0),
            new VectorSearchResult(new VectorDocument('default:similar1', 'similar content 1', $embedding, ['key' => 'similar1']), 0.9),
            new VectorSearchResult(new VectorDocument('default:similar2', 'similar content 2', $embedding, ['key' => 'similar2']), 0.8),
        ];

        $this->store->shouldReceive('search')
            ->once()
            ->withArgs(function ($emb, $lim, $filter) use ($embedding) {
                return $emb === $embedding
                    && $lim === 6 // limit + 1
                    && $filter === ['namespace' => 'default'];
            })
            ->andReturn($results);

        $similar = $this->driver->similar($key, 5);

        $similar = array_values($similar);
        expect($similar)->toHaveCount(2);
        expect($similar[0]->getId())->toBe('default:similar1');
        expect($similar[1]->getId())->toBe('default:similar2');
    });

    test('returns empty array when document not found', function () {
        $key = 'nonexistent_key';

        $this->store->shouldReceive('get')
            ->once()
            ->with('default:nonexistent_key')
            ->andReturn(null);

        $result = $this->driver->similar($key);

        expect($result)->toBe([]);
    });

    test('returns empty array when document has empty embedding', function () {
        $key = 'test_key';

        $doc = new VectorDocument(
            id: 'default:test_key',
            content: 'test content',
            embedding: [],
            metadata: ['key' => $key, 'namespace' => 'default', 'type' => 'string', 'created_at' => now()->toISOString(), 'expires_at' => null]
        );

        $this->store->shouldReceive('get')
            ->once()
            ->with('default:test_key')
            ->andReturn($doc);

        $result = $this->driver->similar($key);

        expect($result)->toBe([]);
    });

    test('uses custom limit', function () {
        $key = 'test_key';
        $limit = 10;
        $embedding = [0.1, 0.2, 0.3];

        $doc = new VectorDocument(
            id: 'default:test_key',
            content: 'test content',
            embedding: $embedding,
            metadata: ['key' => $key, 'namespace' => 'default', 'type' => 'string', 'created_at' => now()->toISOString(), 'expires_at' => null]
        );

        $this->store->shouldReceive('get')
            ->once()
            ->with('default:test_key')
            ->andReturn($doc);

        $this->store->shouldReceive('search')
            ->once()
            ->withArgs(function ($emb, $lim, $filter) use ($limit) {
                return $lim === $limit + 1;
            })
            ->andReturn([]);

        $this->driver->similar($key, $limit);
    });
});

describe('namespace() method', function () {
    test('sets and gets namespace', function () {
        $namespace = 'custom_namespace';

        $result = $this->driver->namespace($namespace);

        expect($result)->toBe($this->driver);
        expect($this->driver->getNamespace())->toBe($namespace);
    });

    test('namespace is used in prefixKey', function () {
        $key = 'test_key';
        $value = 'test value';
        $embedding = [0.1, 0.2, 0.3];

        $this->driver->namespace('custom');

        $this->embeddings->shouldReceive('embed')
            ->once()
            ->with($value)
            ->andReturn($embedding);

        $this->store->shouldReceive('upsert')
            ->once()
            ->withArgs(function ($id, $emb, $content, $metadata) {
                return $id === 'custom:test_key'
                    && $metadata['namespace'] === 'custom';
            });

        $this->driver->set($key, $value);
    });

    test('fluent interface allows chaining', function () {
        $key = 'test_key';

        $this->store->shouldReceive('exists')
            ->once()
            ->with('custom:test_key')
            ->andReturn(true);

        $result = $this->driver->namespace('custom')->has($key);

        expect($result)->toBeTrue();
    });
});

describe('getNamespace() method', function () {
    test('returns default namespace', function () {
        $namespace = $this->driver->getNamespace();

        expect($namespace)->toBe('default');
    });

    test('returns custom namespace after setting', function () {
        $this->driver->namespace('custom');

        $namespace = $this->driver->getNamespace();

        expect($namespace)->toBe('custom');
    });
});
