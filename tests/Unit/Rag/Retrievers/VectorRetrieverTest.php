<?php

declare(strict_types=1);

use AgenticOrchestrator\Embeddings\Contracts\EmbeddingProviderInterface;
use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Embeddings\VectorDocument;
use AgenticOrchestrator\Embeddings\VectorSearchResult;
use AgenticOrchestrator\Rag\Retrievers\VectorRetriever;

describe('VectorRetriever', function () {
    beforeEach(function () {
        $this->embeddings = Mockery::mock(EmbeddingProviderInterface::class);
        $this->store = Mockery::mock(VectorStoreInterface::class);
        $this->retriever = new VectorRetriever($this->embeddings, $this->store);
    });

    it('retrieves documents using vector similarity search', function () {
        $queryEmbedding = [0.1, 0.2, 0.3];

        $doc = new VectorDocument(id: 'doc-1', content: 'Test content');
        $searchResult = new VectorSearchResult(document: $doc, score: 0.9, distance: 0.1);

        $this->embeddings->shouldReceive('embed')
            ->once()
            ->with('test query')
            ->andReturn($queryEmbedding);

        $this->store->shouldReceive('search')
            ->once()
            ->andReturn([$searchResult]);

        $results = $this->retriever->retrieve('test query');

        expect($results)->toHaveCount(1);
        expect($results[0]->score)->toBe(0.9);
        expect($results[0]->getContent())->toBe('Test content');
    });

    it('filters results below threshold', function () {
        $queryEmbedding = [0.1, 0.2, 0.3];

        $doc1 = new VectorDocument(id: 'doc-1', content: 'High score');
        $doc2 = new VectorDocument(id: 'doc-2', content: 'Low score');

        $this->embeddings->shouldReceive('embed')->andReturn($queryEmbedding);
        $this->store->shouldReceive('search')->andReturn([
            new VectorSearchResult(document: $doc1, score: 0.9),
            new VectorSearchResult(document: $doc2, score: 0.5),
        ]);

        $results = $this->retriever->retrieve('test query');

        expect($results)->toHaveCount(1);
        expect($results[0]->getContent())->toBe('High score');
    });

    it('passes limit and filter to store', function () {
        $queryEmbedding = [0.1, 0.2, 0.3];
        $filter = ['category' => 'tech'];

        $this->embeddings->shouldReceive('embed')->andReturn($queryEmbedding);
        $this->store->shouldReceive('search')
            ->once()
            ->andReturn([]);

        $results = $this->retriever->retrieve('test query', 10, $filter);

        expect($results)->toBeEmpty();
    });

    it('sets and gets threshold', function () {
        expect($this->retriever->getThreshold())->toBe(0.7);

        $result = $this->retriever->setThreshold(0.5);

        expect($result)->toBeInstanceOf(VectorRetriever::class);
        expect($this->retriever->getThreshold())->toBe(0.5);
    });

    it('clamps threshold to valid range', function () {
        $this->retriever->setThreshold(1.5);
        expect($this->retriever->getThreshold())->toBe(1.0);

        $this->retriever->setThreshold(-0.5);
        expect($this->retriever->getThreshold())->toBe(0.0);
    });

    it('returns empty array when no results match threshold', function () {
        $queryEmbedding = [0.1, 0.2, 0.3];
        $doc = new VectorDocument(id: 'doc-1', content: 'Low score content');

        $this->embeddings->shouldReceive('embed')->andReturn($queryEmbedding);
        $this->store->shouldReceive('search')->andReturn([
            new VectorSearchResult(document: $doc, score: 0.3),
        ]);

        $results = $this->retriever->retrieve('test query');

        expect($results)->toBeEmpty();
    });

    it('returns empty array when store returns no results', function () {
        $this->embeddings->shouldReceive('embed')->andReturn([0.1, 0.2]);
        $this->store->shouldReceive('search')->andReturn([]);

        $results = $this->retriever->retrieve('empty search');

        expect($results)->toBeEmpty();
    });

    it('re-indexes array values after filtering', function () {
        $queryEmbedding = [0.1, 0.2, 0.3];

        $doc1 = new VectorDocument(id: 'doc-1', content: 'Below threshold');
        $doc2 = new VectorDocument(id: 'doc-2', content: 'Above threshold');

        $this->embeddings->shouldReceive('embed')->andReturn($queryEmbedding);
        $this->store->shouldReceive('search')->andReturn([
            new VectorSearchResult(document: $doc1, score: 0.3),
            new VectorSearchResult(document: $doc2, score: 0.9),
        ]);

        $results = $this->retriever->retrieve('test query');

        expect($results)->toHaveCount(1);
        expect(array_keys($results))->toBe([0]);
    });

    it('respects custom threshold when filtering', function () {
        $queryEmbedding = [0.1];

        $doc1 = new VectorDocument(id: 'doc-1', content: 'Content A');
        $doc2 = new VectorDocument(id: 'doc-2', content: 'Content B');

        $this->embeddings->shouldReceive('embed')->andReturn($queryEmbedding);
        $this->store->shouldReceive('search')->andReturn([
            new VectorSearchResult(document: $doc1, score: 0.4),
            new VectorSearchResult(document: $doc2, score: 0.6),
        ]);

        $this->retriever->setThreshold(0.3);
        $results = $this->retriever->retrieve('query');

        expect($results)->toHaveCount(2);
    });
});
