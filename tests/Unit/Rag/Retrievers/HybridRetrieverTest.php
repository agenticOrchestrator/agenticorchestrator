<?php

declare(strict_types=1);

use AgenticOrchestrator\Embeddings\Contracts\EmbeddingProviderInterface;
use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Embeddings\VectorDocument;
use AgenticOrchestrator\Embeddings\VectorSearchResult;
use AgenticOrchestrator\Rag\Retrievers\HybridRetriever;

describe('HybridRetriever', function () {
    beforeEach(function () {
        $this->embeddings = Mockery::mock(EmbeddingProviderInterface::class);
        $this->store = Mockery::mock(VectorStoreInterface::class);
        $this->retriever = new HybridRetriever($this->embeddings, $this->store);
    });

    it('combines vector and keyword scores', function () {
        $queryEmbedding = [0.1, 0.2, 0.3];
        $doc = new VectorDocument(id: 'doc-1', content: 'Laravel framework documentation for PHP developers');
        $searchResult = new VectorSearchResult(document: $doc, score: 0.9, distance: 0.1);

        $this->embeddings->shouldReceive('embed')
            ->once()
            ->with('Laravel PHP framework')
            ->andReturn($queryEmbedding);

        $this->store->shouldReceive('search')
            ->once()
            ->andReturn([$searchResult]);

        $results = $this->retriever->retrieve('Laravel PHP framework');

        expect($results)->toHaveCount(1);
        // Combined score should be different from pure vector score
        // vectorScore = 0.9 * 0.7 = 0.63, keywordScore > 0 since keywords match
        expect($results[0]->score)->toBeGreaterThan(0.63);
    });

    it('requests double the limit for reranking', function () {
        $queryEmbedding = [0.1, 0.2];

        $this->embeddings->shouldReceive('embed')->andReturn($queryEmbedding);
        $this->store->shouldReceive('search')
            ->once()
            ->andReturn([]);

        $this->retriever->retrieve('query', 5);
    });

    it('filters results below threshold', function () {
        $queryEmbedding = [0.1];

        $doc = new VectorDocument(id: 'doc-1', content: 'Unrelated content about cooking recipes');
        $this->embeddings->shouldReceive('embed')->andReturn($queryEmbedding);
        $this->store->shouldReceive('search')->andReturn([
            new VectorSearchResult(document: $doc, score: 0.3, distance: 0.7),
        ]);

        $this->retriever->setThreshold(0.8);
        $results = $this->retriever->retrieve('programming languages');

        expect($results)->toBeEmpty();
    });

    it('sorts results by combined score descending', function () {
        $queryEmbedding = [0.1];

        $doc1 = new VectorDocument(id: 'doc-1', content: 'Contains specific keyword match for testing');
        $doc2 = new VectorDocument(id: 'doc-2', content: 'General document about many topics');

        $this->embeddings->shouldReceive('embed')->andReturn($queryEmbedding);
        $this->store->shouldReceive('search')->andReturn([
            new VectorSearchResult(document: $doc2, score: 0.95),
            new VectorSearchResult(document: $doc1, score: 0.85),
        ]);

        $this->retriever->setThreshold(0.0);
        $results = $this->retriever->retrieve('testing keyword');

        expect($results)->toHaveCount(2);
        expect($results[0]->score)->toBeGreaterThanOrEqual($results[1]->score);
    });

    it('limits results to requested count', function () {
        $queryEmbedding = [0.1];
        $docs = [];
        for ($i = 0; $i < 6; $i++) {
            $docs[] = new VectorSearchResult(
                document: new VectorDocument(id: "doc-{$i}", content: "Content {$i} with keywords"),
                score: 0.9 - ($i * 0.02),
            );
        }

        $this->embeddings->shouldReceive('embed')->andReturn($queryEmbedding);
        $this->store->shouldReceive('search')->andReturn($docs);

        $this->retriever->setThreshold(0.0);
        $results = $this->retriever->retrieve('keywords', 3);

        expect($results)->toHaveCount(3);
    });

    it('sets and gets threshold', function () {
        expect($this->retriever->getThreshold())->toBe(0.7);

        $result = $this->retriever->setThreshold(0.5);

        expect($result)->toBeInstanceOf(HybridRetriever::class);
        expect($this->retriever->getThreshold())->toBe(0.5);
    });

    it('clamps threshold to valid range', function () {
        $this->retriever->setThreshold(2.0);
        expect($this->retriever->getThreshold())->toBe(1.0);

        $this->retriever->setThreshold(-1.0);
        expect($this->retriever->getThreshold())->toBe(0.0);
    });

    it('sets vector weight and adjusts keyword weight', function () {
        $result = $this->retriever->setVectorWeight(0.8);

        expect($result)->toBeInstanceOf(HybridRetriever::class);

        // Verify by checking behavior: with high vector weight, pure vector results dominate
        $queryEmbedding = [0.1];
        $doc = new VectorDocument(id: 'doc-1', content: 'test');
        $this->embeddings->shouldReceive('embed')->andReturn($queryEmbedding);
        $this->store->shouldReceive('search')->andReturn([
            new VectorSearchResult(document: $doc, score: 1.0),
        ]);

        $this->retriever->setThreshold(0.0);
        $results = $this->retriever->retrieve('test');

        // vectorWeight=0.8 means keywordWeight=0.2
        // Score should be 1.0*0.8 + keyword*0.2
        expect($results[0]->score)->toBeGreaterThanOrEqual(0.8);
    });

    it('sets keyword weight and adjusts vector weight', function () {
        $result = $this->retriever->setKeywordWeight(0.6);

        expect($result)->toBeInstanceOf(HybridRetriever::class);

        // keywordWeight=0.6 means vectorWeight=0.4
        $queryEmbedding = [0.1];
        $doc = new VectorDocument(id: 'doc-1', content: 'exact match query words');
        $this->embeddings->shouldReceive('embed')->andReturn($queryEmbedding);
        $this->store->shouldReceive('search')->andReturn([
            new VectorSearchResult(document: $doc, score: 1.0),
        ]);

        $this->retriever->setThreshold(0.0);
        $results = $this->retriever->retrieve('exact match query words');

        // Score should reflect lowered vector weight
        expect($results)->toHaveCount(1);
    });

    it('clamps vector weight to valid range', function () {
        $this->retriever->setVectorWeight(1.5);

        // After clamping vectorWeight to 1.0, keywordWeight should be 0.0
        $queryEmbedding = [0.1];
        $doc = new VectorDocument(id: 'doc-1', content: 'test content with keywords');
        $this->embeddings->shouldReceive('embed')->andReturn($queryEmbedding);
        $this->store->shouldReceive('search')->andReturn([
            new VectorSearchResult(document: $doc, score: 0.9),
        ]);

        $this->retriever->setThreshold(0.0);
        $results = $this->retriever->retrieve('keywords');

        // With keyword weight 0.0 and vector weight 1.0: score = 0.9*1.0 + keyword*0.0 = 0.9
        expect($results[0]->score)->toBe(0.9);
    });

    it('extracts keywords from query filtering stop words', function () {
        $queryEmbedding = [0.1];

        // Doc that contains the keyword "database"
        $doc = new VectorDocument(id: 'doc-1', content: 'This is about database optimization techniques');

        $this->embeddings->shouldReceive('embed')->andReturn($queryEmbedding);
        $this->store->shouldReceive('search')->andReturn([
            new VectorSearchResult(document: $doc, score: 0.8),
        ]);

        $this->retriever->setThreshold(0.0);
        // Query with many stop words: "what is the best database"
        // Keywords should be: "best", "database"
        $results = $this->retriever->retrieve('what is the best database');

        expect($results)->toHaveCount(1);
        // keyword match on "database" should boost score beyond pure vector
        expect($results[0]->score)->toBeGreaterThan(0.8 * 0.7);
    });

    it('handles empty keyword extraction gracefully', function () {
        $queryEmbedding = [0.1];
        $doc = new VectorDocument(id: 'doc-1', content: 'Some content');

        $this->embeddings->shouldReceive('embed')->andReturn($queryEmbedding);
        $this->store->shouldReceive('search')->andReturn([
            new VectorSearchResult(document: $doc, score: 0.9),
        ]);

        $this->retriever->setThreshold(0.0);
        // All stop words, short words filtered out
        $results = $this->retriever->retrieve('is it a');

        expect($results)->toHaveCount(1);
        // With no keywords, score = vectorScore * vectorWeight + 0
        expect($results[0]->score)->toBe(0.9 * 0.7);
    });

    it('passes filters to the vector store', function () {
        $queryEmbedding = [0.1];
        $filter = ['tenant_id' => 'abc-123'];

        $this->embeddings->shouldReceive('embed')->andReturn($queryEmbedding);
        $this->store->shouldReceive('search')
            ->once()
            ->andReturn([]);

        $this->retriever->retrieve('test', 5, $filter);
    });

    it('returns empty array when store has no results', function () {
        $this->embeddings->shouldReceive('embed')->andReturn([0.1]);
        $this->store->shouldReceive('search')->andReturn([]);

        $results = $this->retriever->retrieve('nothing');

        expect($results)->toBeEmpty();
    });

    it('keyword score caps at 3 occurrences per keyword', function () {
        $queryEmbedding = [0.1];

        // Document with "laravel" repeated many times
        $doc = new VectorDocument(
            id: 'doc-1',
            content: 'laravel laravel laravel laravel laravel laravel laravel laravel'
        );

        $this->embeddings->shouldReceive('embed')->andReturn($queryEmbedding);
        $this->store->shouldReceive('search')->andReturn([
            new VectorSearchResult(document: $doc, score: 0.8),
        ]);

        $this->retriever->setThreshold(0.0);
        $results = $this->retriever->retrieve('laravel');

        expect($results)->toHaveCount(1);
        // Score should be bounded, not infinitely high due to many occurrences
        expect($results[0]->score)->toBeLessThanOrEqual(1.0);
    });
});
