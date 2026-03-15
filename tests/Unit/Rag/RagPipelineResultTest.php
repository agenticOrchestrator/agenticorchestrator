<?php

declare(strict_types=1);

use AgenticOrchestrator\Embeddings\VectorDocument;
use AgenticOrchestrator\Embeddings\VectorSearchResult;
use AgenticOrchestrator\Rag\RagPipelineResult;

describe('RagPipelineResult', function () {
    it('creates empty result', function () {
        $result = new RagPipelineResult;

        expect($result->hasContext())->toBeFalse();
        expect($result->isEmpty())->toBeTrue();
        expect($result->count())->toBe(0);
        expect($result->getResults())->toBe([]);
    });

    it('creates ingest result', function () {
        $result = RagPipelineResult::forIngest(
            documentsProcessed: 10,
            chunksCreated: 25,
            durationMs: 150.5,
            metadata: ['namespace' => 'test'],
        );

        expect($result->isIngest())->toBeTrue();
        expect($result->isQuery())->toBeFalse();
        expect($result->documentsProcessed)->toBe(10);
        expect($result->chunksCreated)->toBe(25);
        expect($result->durationMs)->toBe(150.5);
        expect($result->getMeta('namespace'))->toBe('test');
    });

    it('creates query result', function () {
        $results = createSearchResults(3);

        $result = RagPipelineResult::forQuery(
            results: $results,
            query: 'test query',
            durationMs: 50.0,
            metadata: ['limit' => 5],
        );

        expect($result->isQuery())->toBeTrue();
        expect($result->isIngest())->toBeFalse();
        expect($result->query)->toBe('test query');
        expect($result->count())->toBe(3);
        expect($result->hasContext())->toBeTrue();
    });

    it('gets context string', function () {
        $results = [
            createSearchResult('First chunk', 0.9),
            createSearchResult('Second chunk', 0.8),
        ];

        $result = RagPipelineResult::forQuery($results, 'query', 0.0);

        $context = $result->getContext();

        expect($context)->toContain('First chunk');
        expect($context)->toContain('Second chunk');
        expect($context)->toContain('---');
    });

    it('gets context with custom separator', function () {
        $results = [
            createSearchResult('First', 0.9),
            createSearchResult('Second', 0.8),
        ];

        $result = RagPipelineResult::forQuery($results, 'query', 0.0);

        $context = $result->getContext("\n\n");

        expect($context)->toBe("First\n\nSecond");
    });

    it('gets context with scores', function () {
        $results = [
            createSearchResult('Content', 0.85),
        ];

        $result = RagPipelineResult::forQuery($results, 'query', 0.0);

        $context = $result->getContextWithScores();

        expect($context)->toContain('[Relevance: 85%]');
        expect($context)->toContain('Content');
    });

    it('gets best result', function () {
        $results = [
            createSearchResult('Best', 0.95),
            createSearchResult('Second', 0.8),
        ];

        $result = RagPipelineResult::forQuery($results, 'query', 0.0);

        $best = $result->getBestResult();

        expect($best)->not->toBeNull();
        expect($best->score)->toBe(0.95);
    });

    it('returns null for best result when empty', function () {
        $result = new RagPipelineResult;

        expect($result->getBestResult())->toBeNull();
    });

    it('gets best match content', function () {
        $results = [
            createSearchResult('Top content', 0.9),
        ];

        $result = RagPipelineResult::forQuery($results, 'query', 0.0);

        expect($result->getBestMatch())->toBe('Top content');
    });

    it('gets results above threshold', function () {
        $results = [
            createSearchResult('High', 0.9),
            createSearchResult('Medium', 0.7),
            createSearchResult('Low', 0.5),
        ];

        $result = RagPipelineResult::forQuery($results, 'query', 0.0);

        $filtered = $result->getResultsAboveThreshold(0.75);

        expect(count($filtered))->toBe(1);
        expect($filtered[0]->score)->toBe(0.9);
    });

    it('calculates average score', function () {
        $results = [
            createSearchResult('A', 0.9),
            createSearchResult('B', 0.7),
        ];

        $result = RagPipelineResult::forQuery($results, 'query', 0.0);

        expect($result->getAverageScore())->toBe(0.8);
    });

    it('returns zero average for empty results', function () {
        $result = new RagPipelineResult;

        expect($result->getAverageScore())->toBe(0.0);
    });

    it('gets unique sources', function () {
        $results = [
            createSearchResult('A', 0.9, ['source' => '/file1.txt']),
            createSearchResult('B', 0.8, ['source' => '/file2.txt']),
            createSearchResult('C', 0.7, ['source' => '/file1.txt']),
        ];

        $result = RagPipelineResult::forQuery($results, 'query', 0.0);

        $sources = $result->getSources();

        expect($sources)->toBe(['/file1.txt', '/file2.txt']);
    });

    it('gets duration in seconds', function () {
        $result = RagPipelineResult::forIngest(0, 0, 1500.0);

        expect($result->getDurationSeconds())->toBe(1.5);
    });

    it('converts to array', function () {
        $results = createSearchResults(2);
        $result = RagPipelineResult::forQuery($results, 'test', 100.0);

        $array = $result->toArray();

        expect($array)->toHaveKey('operation', 'query');
        expect($array)->toHaveKey('query', 'test');
        expect($array)->toHaveKey('result_count', 2);
        expect($array)->toHaveKey('duration_ms', 100.0);
        expect($array)->toHaveKey('has_context', true);
    });

    it('serializes to JSON', function () {
        $result = RagPipelineResult::forIngest(5, 10, 50.0);
        $json = json_encode($result);

        expect($json)->toContain('"operation":"ingest"');
        expect($json)->toContain('"documents_processed":5');
    });
});

// Helper functions
function createSearchResult(string $content, float $score, array $metadata = []): VectorSearchResult
{
    $doc = new VectorDocument(
        id: 'doc_'.substr(md5($content), 0, 8),
        content: $content,
        embedding: [],
        metadata: $metadata,
    );

    return new VectorSearchResult($doc, $score);
}

function createSearchResults(int $count): array
{
    $results = [];
    for ($i = 0; $i < $count; $i++) {
        $results[] = createSearchResult("Content {$i}", 0.9 - ($i * 0.1));
    }

    return $results;
}
