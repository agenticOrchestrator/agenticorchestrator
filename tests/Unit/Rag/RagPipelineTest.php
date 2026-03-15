<?php

declare(strict_types=1);

use AgenticOrchestrator\Embeddings\Contracts\EmbeddingProviderInterface;
use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Embeddings\VectorSearchResult;
use AgenticOrchestrator\Rag\Contracts\ChunkingStrategyInterface;
use AgenticOrchestrator\Rag\Contracts\DocumentLoaderInterface;
use AgenticOrchestrator\Rag\Contracts\RerankerInterface;
use AgenticOrchestrator\Rag\Contracts\RetrieverInterface;
use AgenticOrchestrator\Rag\Document;
use AgenticOrchestrator\Rag\RagConfig;
use AgenticOrchestrator\Rag\RagPipeline;
use AgenticOrchestrator\Rag\RagPipelineResult;

describe('RagPipeline', function () {
    describe('construction', function () {
        it('creates pipeline with default config', function () {
            $pipeline = new RagPipeline;

            expect($pipeline)->toBeInstanceOf(RagPipeline::class)
                ->and($pipeline->getConfig())->toBeInstanceOf(RagConfig::class);
        });

        it('creates pipeline with custom config', function () {
            $config = new RagConfig(namespace: 'custom', chunkSize: 500);
            $pipeline = new RagPipeline($config);

            expect($pipeline->getConfig()->namespace)->toBe('custom')
                ->and($pipeline->getConfig()->chunkSize)->toBe(500);
        });

        it('creates pipeline via static make factory', function () {
            $pipeline = RagPipeline::make();

            expect($pipeline)->toBeInstanceOf(RagPipeline::class);
        });

        it('creates pipeline via make with config', function () {
            $config = new RagConfig(namespace: 'test');
            $pipeline = RagPipeline::make($config);

            expect($pipeline->getConfig()->namespace)->toBe('test');
        });
    });

    describe('fluent configuration', function () {
        it('sets namespace', function () {
            $pipeline = RagPipeline::make()->namespace('knowledge_base');

            expect($pipeline->getNamespace())->toBe('knowledge_base');
        });

        it('sets tenant id', function () {
            $pipeline = RagPipeline::make()->forTenant('tenant-42');

            expect($pipeline->getNamespace())->toBe('tenant_tenant-42_default');
        });

        it('sets tenant id with integer', function () {
            $pipeline = RagPipeline::make()->forTenant(42);

            expect($pipeline->getNamespace())->toContain('tenant_42');
        });

        it('sets chunk size', function () {
            $pipeline = RagPipeline::make()->chunkSize(500);

            expect($pipeline->getConfig()->chunkSize)->toBe(500);
        });

        it('sets chunk overlap', function () {
            $pipeline = RagPipeline::make()->chunkOverlap(100);

            expect($pipeline->getConfig()->chunkOverlap)->toBe(100);
        });

        it('sets retrieve limit', function () {
            $pipeline = RagPipeline::make()->limit(10);

            expect($pipeline->getConfig()->retrieveLimit)->toBe(10);
        });

        it('sets score threshold', function () {
            $pipeline = RagPipeline::make()->threshold(0.8);

            expect($pipeline->getConfig()->scoreThreshold)->toBe(0.8);
        });

        it('sets embeddings provider', function () {
            $embeddings = Mockery::mock(EmbeddingProviderInterface::class);
            $pipeline = RagPipeline::make()->embeddings($embeddings);

            expect($pipeline)->toBeInstanceOf(RagPipeline::class);
        });

        it('sets vector store', function () {
            $store = Mockery::mock(VectorStoreInterface::class);
            $pipeline = RagPipeline::make()->store($store);

            expect($pipeline)->toBeInstanceOf(RagPipeline::class);
        });

        it('sets custom loader', function () {
            $loader = Mockery::mock(DocumentLoaderInterface::class);
            $pipeline = RagPipeline::make()->loader($loader);

            expect($pipeline)->toBeInstanceOf(RagPipeline::class);
        });

        it('sets custom chunker', function () {
            $chunker = Mockery::mock(ChunkingStrategyInterface::class);
            $pipeline = RagPipeline::make()->chunker($chunker);

            expect($pipeline)->toBeInstanceOf(RagPipeline::class);
        });

        it('sets custom retriever', function () {
            $retriever = Mockery::mock(RetrieverInterface::class);
            $pipeline = RagPipeline::make()->retriever($retriever);

            expect($pipeline)->toBeInstanceOf(RagPipeline::class);
        });

        it('sets custom reranker', function () {
            $reranker = Mockery::mock(RerankerInterface::class);
            $pipeline = RagPipeline::make()->reranker($reranker);

            expect($pipeline)->toBeInstanceOf(RagPipeline::class);
        });

        it('sets source path', function () {
            $pipeline = RagPipeline::make()->from('/path/to/docs');

            expect($pipeline)->toBeInstanceOf(RagPipeline::class);
        });

        it('chains all configuration methods fluently', function () {
            $embeddings = Mockery::mock(EmbeddingProviderInterface::class);
            $store = Mockery::mock(VectorStoreInterface::class);

            $pipeline = RagPipeline::make()
                ->namespace('my_ns')
                ->embeddings($embeddings)
                ->store($store)
                ->chunkSize(800)
                ->chunkOverlap(100)
                ->limit(10)
                ->threshold(0.8);

            $config = $pipeline->getConfig();
            expect($config->namespace)->toBe('my_ns')
                ->and($config->chunkSize)->toBe(800)
                ->and($config->chunkOverlap)->toBe(100)
                ->and($config->retrieveLimit)->toBe(10)
                ->and($config->scoreThreshold)->toBe(0.8);
        });
    });

    describe('document management', function () {
        it('adds raw text content for ingestion', function () {
            $pipeline = RagPipeline::make()
                ->fromText('Some document content', ['source' => 'test']);

            expect($pipeline)->toBeInstanceOf(RagPipeline::class);
        });

        it('adds a document for ingestion', function () {
            $doc = Document::fromText('Test content');
            $pipeline = RagPipeline::make()->addDocument($doc);

            expect($pipeline)->toBeInstanceOf(RagPipeline::class);
        });

        it('adds multiple documents for ingestion', function () {
            $docs = [
                Document::fromText('Doc 1'),
                Document::fromText('Doc 2'),
                Document::fromText('Doc 3'),
            ];

            $pipeline = RagPipeline::make()->addDocuments($docs);

            expect($pipeline)->toBeInstanceOf(RagPipeline::class);
        });
    });

    describe('ingestion validation', function () {
        it('throws when embeddings provider is missing', function () {
            $store = Mockery::mock(VectorStoreInterface::class);

            $pipeline = RagPipeline::make()
                ->store($store)
                ->fromText('content');

            expect(fn () => $pipeline->ingest())->toThrow(
                InvalidArgumentException::class,
                'Embedding provider is required for ingestion'
            );
        });

        it('throws when vector store is missing', function () {
            $embeddings = Mockery::mock(EmbeddingProviderInterface::class);

            $pipeline = RagPipeline::make()
                ->embeddings($embeddings)
                ->fromText('content');

            expect(fn () => $pipeline->ingest())->toThrow(
                InvalidArgumentException::class,
                'Vector store is required for ingestion'
            );
        });

        it('returns zero-count result when no documents to ingest', function () {
            $embeddings = Mockery::mock(EmbeddingProviderInterface::class);
            $store = Mockery::mock(VectorStoreInterface::class);

            $pipeline = RagPipeline::make()
                ->embeddings($embeddings)
                ->store($store);

            $result = $pipeline->ingest();

            expect($result)->toBeInstanceOf(RagPipelineResult::class)
                ->and($result->documentsProcessed)->toBe(0)
                ->and($result->chunksCreated)->toBe(0)
                ->and($result->isIngest())->toBeTrue();
        });
    });

    describe('ingestion with documents', function () {
        it('ingests documents using custom chunker', function () {
            $embeddings = Mockery::mock(EmbeddingProviderInterface::class);
            $embeddings->shouldReceive('embedBatch')
                ->andReturn([[0.1, 0.2], [0.3, 0.4]]);

            $store = Mockery::mock(VectorStoreInterface::class);
            $store->shouldReceive('upsertBatch')->once();

            $chunk1 = Document::fromText('Chunk 1');
            $chunk2 = Document::fromText('Chunk 2');

            $chunker = Mockery::mock(ChunkingStrategyInterface::class);
            $chunker->shouldReceive('setChunkSize')->andReturnSelf();
            $chunker->shouldReceive('setOverlap')->andReturnSelf();
            $chunker->shouldReceive('chunkAll')->andReturn([$chunk1, $chunk2]);

            $pipeline = RagPipeline::make()
                ->embeddings($embeddings)
                ->store($store)
                ->chunker($chunker)
                ->fromText('Full document text');

            $result = $pipeline->ingest();

            expect($result->documentsProcessed)->toBe(1)
                ->and($result->chunksCreated)->toBe(2)
                ->and($result->isIngest())->toBeTrue()
                ->and($result->durationMs)->toBeGreaterThan(0);
        });

        it('ingests documents from custom loader', function () {
            $embeddings = Mockery::mock(EmbeddingProviderInterface::class);
            $embeddings->shouldReceive('embedBatch')
                ->andReturn([[0.1, 0.2]]);

            $store = Mockery::mock(VectorStoreInterface::class);
            $store->shouldReceive('upsertBatch')->once();

            $loadedDoc = Document::fromText('Loaded content');
            $loader = Mockery::mock(DocumentLoaderInterface::class);
            $loader->shouldReceive('load')->with('/path/to/file.txt')->andReturn([$loadedDoc]);

            $chunker = Mockery::mock(ChunkingStrategyInterface::class);
            $chunker->shouldReceive('setChunkSize')->andReturnSelf();
            $chunker->shouldReceive('setOverlap')->andReturnSelf();
            $chunker->shouldReceive('chunkAll')->andReturn([$loadedDoc]);

            $pipeline = RagPipeline::make()
                ->embeddings($embeddings)
                ->store($store)
                ->loader($loader)
                ->chunker($chunker)
                ->from('/path/to/file.txt');

            $result = $pipeline->ingest();

            expect($result->documentsProcessed)->toBe(1)
                ->and($result->isIngest())->toBeTrue();
        });

        it('resets state after ingestion', function () {
            $embeddings = Mockery::mock(EmbeddingProviderInterface::class);
            $embeddings->shouldReceive('embedBatch')->andReturn([[0.1]]);

            $store = Mockery::mock(VectorStoreInterface::class);
            $store->shouldReceive('upsertBatch');

            $chunker = Mockery::mock(ChunkingStrategyInterface::class);
            $chunker->shouldReceive('setChunkSize')->andReturnSelf();
            $chunker->shouldReceive('setOverlap')->andReturnSelf();
            $chunker->shouldReceive('chunkAll')->andReturn([Document::fromText('chunk')]);

            $pipeline = RagPipeline::make()
                ->embeddings($embeddings)
                ->store($store)
                ->chunker($chunker)
                ->fromText('document content');

            $pipeline->ingest();

            // Second ingest without adding new documents should yield zero
            $result = $pipeline->ingest();

            expect($result->documentsProcessed)->toBe(0);
        });
    });

    describe('query validation', function () {
        it('throws when embeddings provider is missing for query', function () {
            $store = Mockery::mock(VectorStoreInterface::class);

            $pipeline = RagPipeline::make()->store($store);

            expect(fn () => $pipeline->query('test query'))->toThrow(
                InvalidArgumentException::class,
                'Embedding provider is required for querying'
            );
        });

        it('throws when vector store is missing for query', function () {
            $embeddings = Mockery::mock(EmbeddingProviderInterface::class);

            $pipeline = RagPipeline::make()->embeddings($embeddings);

            expect(fn () => $pipeline->query('test query'))->toThrow(
                InvalidArgumentException::class,
                'Vector store is required for querying'
            );
        });
    });

    describe('query execution', function () {
        it('queries using custom retriever', function () {
            $embeddings = Mockery::mock(EmbeddingProviderInterface::class);
            $store = Mockery::mock(VectorStoreInterface::class);

            $searchResult = Mockery::mock(VectorSearchResult::class);

            $retriever = Mockery::mock(RetrieverInterface::class);
            $retriever->shouldReceive('setThreshold')->andReturnSelf();
            $retriever->shouldReceive('retrieve')
                ->with('test question', 5, Mockery::type('array'))
                ->andReturn([$searchResult]);

            $pipeline = RagPipeline::make()
                ->embeddings($embeddings)
                ->store($store)
                ->retriever($retriever);

            $result = $pipeline->query('test question');

            expect($result)->toBeInstanceOf(RagPipelineResult::class)
                ->and($result->isQuery())->toBeTrue()
                ->and($result->query)->toBe('test question')
                ->and($result->count())->toBe(1)
                ->and($result->durationMs)->toBeGreaterThan(0);
        });

        it('applies reranker when configured', function () {
            $embeddings = Mockery::mock(EmbeddingProviderInterface::class);
            $store = Mockery::mock(VectorStoreInterface::class);

            $rawResult = Mockery::mock(VectorSearchResult::class);
            $rerankedResult = Mockery::mock(VectorSearchResult::class);

            $retriever = Mockery::mock(RetrieverInterface::class);
            $retriever->shouldReceive('setThreshold')->andReturnSelf();
            $retriever->shouldReceive('retrieve')->andReturn([$rawResult]);

            $reranker = Mockery::mock(RerankerInterface::class);
            $reranker->shouldReceive('rerank')
                ->with([$rawResult], 'my query')
                ->andReturn([$rerankedResult]);

            $pipeline = RagPipeline::make()
                ->embeddings($embeddings)
                ->store($store)
                ->retriever($retriever)
                ->reranker($reranker);

            $result = $pipeline->query('my query');

            expect($result->getResults())->toBe([$rerankedResult]);
        });

        it('respects configured limit and namespace', function () {
            $embeddings = Mockery::mock(EmbeddingProviderInterface::class);
            $store = Mockery::mock(VectorStoreInterface::class);

            $retriever = Mockery::mock(RetrieverInterface::class);
            $retriever->shouldReceive('setThreshold')->andReturnSelf();
            $retriever->shouldReceive('retrieve')
                ->with('query', 10, ['namespace' => 'custom_ns'])
                ->andReturn([]);

            $pipeline = RagPipeline::make()
                ->embeddings($embeddings)
                ->store($store)
                ->retriever($retriever)
                ->namespace('custom_ns')
                ->limit(10);

            $result = $pipeline->query('query');

            expect($result->isEmpty())->toBeTrue();
        });
    });

    describe('search alias', function () {
        it('delegates to query method', function () {
            $embeddings = Mockery::mock(EmbeddingProviderInterface::class);
            $store = Mockery::mock(VectorStoreInterface::class);

            $retriever = Mockery::mock(RetrieverInterface::class);
            $retriever->shouldReceive('setThreshold')->andReturnSelf();
            $retriever->shouldReceive('retrieve')->andReturn([]);

            $pipeline = RagPipeline::make()
                ->embeddings($embeddings)
                ->store($store)
                ->retriever($retriever);

            $result = $pipeline->search('search term');

            expect($result)->toBeInstanceOf(RagPipelineResult::class)
                ->and($result->query)->toBe('search term')
                ->and($result->isQuery())->toBeTrue();
        });
    });

    describe('delete and clear', function () {
        it('throws when store is not configured for delete', function () {
            $pipeline = RagPipeline::make();

            expect(fn () => $pipeline->delete())->toThrow(
                InvalidArgumentException::class,
                'Vector store is required'
            );
        });

        it('deletes by filter with namespace prepended', function () {
            $store = Mockery::mock(VectorStoreInterface::class);
            $store->shouldReceive('deleteByFilter')
                ->with(['namespace' => 'default', 'source' => 'old_file.txt'])
                ->andReturn(3);

            $pipeline = RagPipeline::make()->store($store);

            $count = $pipeline->delete(['source' => 'old_file.txt']);

            expect($count)->toBe(3);
        });

        it('clears all documents in namespace', function () {
            $store = Mockery::mock(VectorStoreInterface::class);
            $store->shouldReceive('deleteByFilter')
                ->with(['namespace' => 'test_ns'])
                ->andReturn(5);

            $pipeline = RagPipeline::make()
                ->store($store)
                ->namespace('test_ns');

            $count = $pipeline->clear();

            expect($count)->toBe(5);
        });
    });

    describe('getConfig and getNamespace', function () {
        it('returns the current config', function () {
            $config = new RagConfig(namespace: 'test', chunkSize: 500);
            $pipeline = RagPipeline::make($config);

            expect($pipeline->getConfig())->toBeInstanceOf(RagConfig::class)
                ->and($pipeline->getConfig()->chunkSize)->toBe(500);
        });

        it('returns the effective namespace', function () {
            $pipeline = RagPipeline::make()->namespace('docs');

            expect($pipeline->getNamespace())->toBe('docs');
        });

        it('returns tenant-prefixed namespace', function () {
            $pipeline = RagPipeline::make()
                ->namespace('docs')
                ->forTenant('t1');

            expect($pipeline->getNamespace())->toBe('tenant_t1_docs');
        });
    });
});
