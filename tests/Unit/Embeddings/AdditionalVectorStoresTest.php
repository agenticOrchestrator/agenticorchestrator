<?php

declare(strict_types=1);

use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Embeddings\Stores\ChromaVectorStore;
use AgenticOrchestrator\Embeddings\Stores\QdrantVectorStore;
use AgenticOrchestrator\Embeddings\Stores\WeaviateVectorStore;

describe('WeaviateVectorStore', function () {
    it('creates with config', function () {
        $store = new WeaviateVectorStore([
            'host' => 'http://weaviate.example.com',
            'api_key' => 'test-key',
            'class_name' => 'Documents',
            'timeout' => 60,
        ]);

        expect($store)->toBeInstanceOf(WeaviateVectorStore::class);
    });

    it('creates with static make', function () {
        $store = WeaviateVectorStore::make([
            'host' => 'http://localhost:8080',
        ]);

        expect($store)->toBeInstanceOf(WeaviateVectorStore::class);
    });
});

describe('QdrantVectorStore', function () {
    it('creates with config', function () {
        $store = new QdrantVectorStore([
            'host' => 'http://qdrant.example.com:6333',
            'api_key' => 'test-key',
            'collection' => 'my_collection',
            'timeout' => 60,
        ]);

        expect($store)->toBeInstanceOf(QdrantVectorStore::class);
    });

    it('creates with static make', function () {
        $store = QdrantVectorStore::make([
            'host' => 'http://localhost:6333',
        ]);

        expect($store)->toBeInstanceOf(QdrantVectorStore::class);
    });
});

describe('ChromaVectorStore', function () {
    it('creates with config', function () {
        $store = new ChromaVectorStore([
            'host' => 'http://chroma.example.com:8000',
            'collection' => 'my_collection',
            'tenant' => 'my_tenant',
            'database' => 'my_database',
            'timeout' => 60,
        ]);

        expect($store)->toBeInstanceOf(ChromaVectorStore::class);
    });

    it('creates with static make', function () {
        $store = ChromaVectorStore::make([
            'host' => 'http://localhost:8000',
        ]);

        expect($store)->toBeInstanceOf(ChromaVectorStore::class);
    });
});

describe('Vector Store Interface Compliance', function () {
    it('weaviate implements interface', function () {
        $store = WeaviateVectorStore::make();

        expect($store)->toBeInstanceOf(VectorStoreInterface::class);
    });

    it('qdrant implements interface', function () {
        $store = QdrantVectorStore::make();

        expect($store)->toBeInstanceOf(VectorStoreInterface::class);
    });

    it('chroma implements interface', function () {
        $store = ChromaVectorStore::make();

        expect($store)->toBeInstanceOf(VectorStoreInterface::class);
    });
});
