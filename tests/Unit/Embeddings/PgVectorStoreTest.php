<?php

declare(strict_types=1);

use AgenticOrchestrator\Embeddings\Stores\PgVectorStore;
use AgenticOrchestrator\Embeddings\VectorDocument;
use AgenticOrchestrator\Embeddings\VectorSearchResult;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

describe('PgVectorStore', function () {
    beforeEach(function () {
        $this->connection = Mockery::mock(Connection::class);
        DB::shouldReceive('connection')->with(null)->andReturn($this->connection);
    });

    describe('constructor and distance metrics', function () {
        it('creates with default parameters', function () {
            $store = new PgVectorStore;

            expect($store)->toBeInstanceOf(PgVectorStore::class);
        });

        it('creates with custom table and dimension', function () {
            $store = new PgVectorStore(
                table: 'custom_vectors',
                dimension: 3072,
            );

            expect($store)->toBeInstanceOf(PgVectorStore::class);
        });

        it('uses cosine distance operator by default', function () {
            $store = new PgVectorStore;

            // Verify via createTable which includes the dimension
            $this->connection->shouldReceive('statement')
                ->twice(); // CREATE TABLE + CREATE INDEX

            $store->createTable();
        });

        it('accepts euclidean distance metric', function () {
            DB::shouldReceive('connection')->with(null)->andReturn($this->connection);
            $store = new PgVectorStore(distanceMetric: 'euclidean');

            expect($store)->toBeInstanceOf(PgVectorStore::class);
        });

        it('accepts l2 distance metric', function () {
            DB::shouldReceive('connection')->with(null)->andReturn($this->connection);
            $store = new PgVectorStore(distanceMetric: 'l2');

            expect($store)->toBeInstanceOf(PgVectorStore::class);
        });

        it('accepts inner_product distance metric', function () {
            DB::shouldReceive('connection')->with(null)->andReturn($this->connection);
            $store = new PgVectorStore(distanceMetric: 'inner_product');

            expect($store)->toBeInstanceOf(PgVectorStore::class);
        });

        it('accepts dot distance metric', function () {
            DB::shouldReceive('connection')->with(null)->andReturn($this->connection);
            $store = new PgVectorStore(distanceMetric: 'dot');

            expect($store)->toBeInstanceOf(PgVectorStore::class);
        });

        it('falls back to cosine for unknown metric', function () {
            DB::shouldReceive('connection')->with(null)->andReturn($this->connection);
            $store = new PgVectorStore(distanceMetric: 'unknown');

            expect($store)->toBeInstanceOf(PgVectorStore::class);
        });
    });

    describe('fromConfig', function () {
        it('creates from config array', function () {
            $store = PgVectorStore::fromConfig([
                'table' => 'my_vectors',
                'dimension' => 768,
                'distance_metric' => 'euclidean',
            ]);

            expect($store)->toBeInstanceOf(PgVectorStore::class);
        });

        it('uses defaults for missing config values', function () {
            $store = PgVectorStore::fromConfig([]);

            expect($store)->toBeInstanceOf(PgVectorStore::class);
        });
    });

    describe('upsert', function () {
        it('inserts a document with vector', function () {
            $this->connection->shouldReceive('statement')
                ->once()
                ->withArgs(function ($sql, $params) {
                    return str_contains($sql, 'INSERT INTO vector_documents')
                        && $params[0] === 'doc-1'
                        && $params[1] === 'test content'
                        && $params[2] === '[0.1,0.2,0.3]'
                        && $params[3] === '{"source":"test"}';
                });

            $store = new PgVectorStore;
            $store->upsert('doc-1', [0.1, 0.2, 0.3], 'test content', ['source' => 'test']);
        });
    });

    describe('upsertBatch', function () {
        it('upserts each document individually', function () {
            $this->connection->shouldReceive('statement')->times(2);

            $docs = [
                new VectorDocument('d1', 'content1', [0.1], ['k' => 'v1']),
                new VectorDocument('d2', 'content2', [0.2], ['k' => 'v2']),
            ];

            $store = new PgVectorStore;
            $store->upsertBatch($docs);
        });
    });

    describe('search', function () {
        it('searches without filters', function () {
            $row = (object) [
                'id' => 'doc-1',
                'content' => 'hello world',
                'embedding' => '[0.1,0.2]',
                'metadata' => '{"source":"test"}',
                'score' => 0.95,
            ];

            $this->connection->shouldReceive('select')
                ->once()
                ->andReturn([$row]);

            $store = new PgVectorStore;
            $results = $store->search([0.1, 0.2], 5);

            expect($results)->toHaveCount(1)
                ->and($results[0])->toBeInstanceOf(VectorSearchResult::class)
                ->and($results[0]->score)->toBe(0.95)
                ->and($results[0]->document->id)->toBe('doc-1')
                ->and($results[0]->document->content)->toBe('hello world')
                ->and($results[0]->document->embedding)->toBe([0.1, 0.2])
                ->and($results[0]->document->metadata)->toBe(['source' => 'test']);
        });

        it('searches with scalar filter', function () {
            $this->connection->shouldReceive('select')
                ->once()
                ->withArgs(function ($sql, $params) {
                    return str_contains($sql, 'metadata->>? = ?')
                        && str_contains($sql, 'WHERE');
                })
                ->andReturn([]);

            $store = new PgVectorStore;
            $store->search([0.1], 10, ['type' => 'article']);
        });

        it('searches with array filter', function () {
            $this->connection->shouldReceive('select')
                ->once()
                ->withArgs(function ($sql, $params) {
                    return str_contains($sql, 'metadata->>? IN');
                })
                ->andReturn([]);

            $store = new PgVectorStore;
            $store->search([0.1], 10, ['type' => ['article', 'blog']]);
        });
    });

    describe('delete', function () {
        it('deletes a document and returns true', function () {
            $this->connection->shouldReceive('delete')
                ->once()
                ->withArgs(function ($sql, $params) {
                    return str_contains($sql, 'DELETE FROM vector_documents WHERE id = ?')
                        && $params[0] === 'doc-1';
                })
                ->andReturn(1);

            $store = new PgVectorStore;

            expect($store->delete('doc-1'))->toBeTrue();
        });

        it('returns false when document not found', function () {
            $this->connection->shouldReceive('delete')
                ->once()
                ->andReturn(0);

            $store = new PgVectorStore;

            expect($store->delete('nonexistent'))->toBeFalse();
        });
    });

    describe('deleteBatch', function () {
        it('deletes multiple documents', function () {
            $this->connection->shouldReceive('delete')
                ->once()
                ->withArgs(function ($sql, $params) {
                    return str_contains($sql, 'IN (?,?)')
                        && $params === ['id1', 'id2'];
                })
                ->andReturn(2);

            $store = new PgVectorStore;

            expect($store->deleteBatch(['id1', 'id2']))->toBe(2);
        });

        it('returns zero for empty ids', function () {
            $store = new PgVectorStore;

            expect($store->deleteBatch([]))->toBe(0);
        });
    });

    describe('deleteByFilter', function () {
        it('deletes documents matching scalar filter', function () {
            $this->connection->shouldReceive('delete')
                ->once()
                ->withArgs(function ($sql, $params) {
                    return str_contains($sql, 'metadata->>? = ?');
                })
                ->andReturn(3);

            $store = new PgVectorStore;

            expect($store->deleteByFilter(['type' => 'old']))->toBe(3);
        });

        it('deletes documents matching array filter', function () {
            $this->connection->shouldReceive('delete')
                ->once()
                ->withArgs(function ($sql, $params) {
                    return str_contains($sql, 'metadata->>? IN');
                })
                ->andReturn(2);

            $store = new PgVectorStore;

            expect($store->deleteByFilter(['type' => ['old', 'expired']]))->toBe(2);
        });

        it('returns zero for empty filter', function () {
            $store = new PgVectorStore;

            expect($store->deleteByFilter([]))->toBe(0);
        });
    });

    describe('get', function () {
        it('returns a document by id', function () {
            $row = (object) [
                'id' => 'doc-1',
                'content' => 'found it',
                'embedding' => '[0.5,0.6]',
                'metadata' => '{"tag":"important"}',
            ];

            $this->connection->shouldReceive('selectOne')
                ->once()
                ->andReturn($row);

            $store = new PgVectorStore;
            $doc = $store->get('doc-1');

            expect($doc)->toBeInstanceOf(VectorDocument::class)
                ->and($doc->id)->toBe('doc-1')
                ->and($doc->content)->toBe('found it')
                ->and($doc->embedding)->toBe([0.5, 0.6])
                ->and($doc->metadata)->toBe(['tag' => 'important']);
        });

        it('returns null when document not found', function () {
            $this->connection->shouldReceive('selectOne')
                ->once()
                ->andReturn(null);

            $store = new PgVectorStore;

            expect($store->get('nonexistent'))->toBeNull();
        });
    });

    describe('exists', function () {
        it('returns true when document exists', function () {
            $this->connection->shouldReceive('selectOne')
                ->once()
                ->andReturn((object) ['1' => 1]);

            $store = new PgVectorStore;

            expect($store->exists('doc-1'))->toBeTrue();
        });

        it('returns false when document does not exist', function () {
            $this->connection->shouldReceive('selectOne')
                ->once()
                ->andReturn(null);

            $store = new PgVectorStore;

            expect($store->exists('missing'))->toBeFalse();
        });
    });

    describe('count', function () {
        it('returns document count', function () {
            $this->connection->shouldReceive('selectOne')
                ->once()
                ->andReturn((object) ['count' => 42]);

            $store = new PgVectorStore;

            expect($store->count())->toBe(42);
        });

        it('returns zero when count is null', function () {
            $this->connection->shouldReceive('selectOne')
                ->once()
                ->andReturn((object) ['count' => null]);

            $store = new PgVectorStore;

            expect($store->count())->toBe(0);
        });
    });

    describe('clear', function () {
        it('truncates the table', function () {
            $this->connection->shouldReceive('statement')
                ->once()
                ->withArgs(function ($sql) {
                    return str_contains($sql, 'TRUNCATE TABLE vector_documents');
                });

            $store = new PgVectorStore;
            $store->clear();
        });
    });

    describe('createTable', function () {
        it('creates table and index', function () {
            $this->connection->shouldReceive('statement')
                ->twice(); // CREATE TABLE + CREATE INDEX

            $store = new PgVectorStore;
            $store->createTable();
        });
    });

    describe('dropTable', function () {
        it('drops the table', function () {
            $this->connection->shouldReceive('statement')
                ->once()
                ->withArgs(function ($sql) {
                    return str_contains($sql, 'DROP TABLE IF EXISTS vector_documents');
                });

            $store = new PgVectorStore;
            $store->dropTable();
        });
    });
});
