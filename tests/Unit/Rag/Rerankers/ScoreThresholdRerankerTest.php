<?php

declare(strict_types=1);

use AgenticOrchestrator\Embeddings\VectorDocument;
use AgenticOrchestrator\Embeddings\VectorSearchResult;
use AgenticOrchestrator\Rag\Contracts\RerankerInterface;
use AgenticOrchestrator\Rag\Rerankers\ScoreThresholdReranker;

function makeSearchResult(string $id, float $score): VectorSearchResult
{
    return new VectorSearchResult(
        document: new VectorDocument(id: $id, content: "Content for {$id}"),
        score: $score,
    );
}

describe('ScoreThresholdReranker', function () {
    it('implements RerankerInterface', function () {
        $reranker = new ScoreThresholdReranker;

        expect($reranker)->toBeInstanceOf(RerankerInterface::class);
    });

    it('uses default threshold of 0.7', function () {
        $reranker = new ScoreThresholdReranker;

        $results = [
            makeSearchResult('high', 0.9),
            makeSearchResult('medium', 0.75),
            makeSearchResult('low', 0.5),
            makeSearchResult('very-low', 0.3),
        ];

        $filtered = $reranker->rerank($results, 'test query');

        expect($filtered)->toHaveCount(2);
        expect($filtered[0]->document->id)->toBe('high');
        expect($filtered[1]->document->id)->toBe('medium');
    });

    it('filters by custom threshold', function () {
        $reranker = new ScoreThresholdReranker(threshold: 0.5);

        $results = [
            makeSearchResult('a', 0.9),
            makeSearchResult('b', 0.6),
            makeSearchResult('c', 0.4),
        ];

        $filtered = $reranker->rerank($results, 'query');

        expect($filtered)->toHaveCount(2);
    });

    it('sorts results by score descending', function () {
        $reranker = new ScoreThresholdReranker(threshold: 0.0);

        $results = [
            makeSearchResult('low', 0.3),
            makeSearchResult('high', 0.9),
            makeSearchResult('medium', 0.6),
        ];

        $filtered = $reranker->rerank($results, 'query');

        expect($filtered[0]->score)->toBe(0.9);
        expect($filtered[1]->score)->toBe(0.6);
        expect($filtered[2]->score)->toBe(0.3);
    });

    it('limits results with maxResults', function () {
        $reranker = new ScoreThresholdReranker(threshold: 0.0, maxResults: 2);

        $results = [
            makeSearchResult('a', 0.9),
            makeSearchResult('b', 0.8),
            makeSearchResult('c', 0.7),
            makeSearchResult('d', 0.6),
        ];

        $filtered = $reranker->rerank($results, 'query');

        expect($filtered)->toHaveCount(2);
        expect($filtered[0]->document->id)->toBe('a');
        expect($filtered[1]->document->id)->toBe('b');
    });

    it('returns empty array when no results pass threshold', function () {
        $reranker = new ScoreThresholdReranker(threshold: 0.9);

        $results = [
            makeSearchResult('a', 0.5),
            makeSearchResult('b', 0.3),
        ];

        $filtered = $reranker->rerank($results, 'query');

        expect($filtered)->toBeEmpty();
    });

    it('handles empty input', function () {
        $reranker = new ScoreThresholdReranker;

        $filtered = $reranker->rerank([], 'query');

        expect($filtered)->toBeEmpty();
    });

    it('includes results at exact threshold', function () {
        $reranker = new ScoreThresholdReranker(threshold: 0.7);

        $results = [
            makeSearchResult('exact', 0.7),
            makeSearchResult('below', 0.69),
        ];

        $filtered = $reranker->rerank($results, 'query');

        expect($filtered)->toHaveCount(1);
        expect($filtered[0]->document->id)->toBe('exact');
    });

    it('sets threshold with clamping', function () {
        $reranker = new ScoreThresholdReranker;

        $reranker->setThreshold(0.5);
        $results = [makeSearchResult('a', 0.6), makeSearchResult('b', 0.4)];
        expect($reranker->rerank($results, 'q'))->toHaveCount(1);

        // Clamp above 1.0
        $reranker->setThreshold(1.5);
        $results = [makeSearchResult('a', 0.99)];
        expect($reranker->rerank($results, 'q'))->toBeEmpty();

        // Clamp below 0.0
        $reranker->setThreshold(-0.5);
        $results = [makeSearchResult('a', 0.01)];
        expect($reranker->rerank($results, 'q'))->toHaveCount(1);
    });

    it('allows fluent setMaxResults', function () {
        $reranker = new ScoreThresholdReranker(threshold: 0.0);

        $result = $reranker->setMaxResults(1);

        expect($result)->toBeInstanceOf(ScoreThresholdReranker::class);

        $results = [
            makeSearchResult('a', 0.9),
            makeSearchResult('b', 0.8),
        ];

        $filtered = $reranker->rerank($results, 'q');
        expect($filtered)->toHaveCount(1);
    });

    it('removes maxResults limit when set to null', function () {
        $reranker = new ScoreThresholdReranker(threshold: 0.0, maxResults: 1);
        $reranker->setMaxResults(null);

        $results = [
            makeSearchResult('a', 0.9),
            makeSearchResult('b', 0.8),
            makeSearchResult('c', 0.7),
        ];

        $filtered = $reranker->rerank($results, 'q');

        expect($filtered)->toHaveCount(3);
    });

    it('filters by score gap', function () {
        $reranker = new ScoreThresholdReranker(threshold: 0.0);
        $reranker->setMinScoreGap(0.1);

        $results = [
            makeSearchResult('a', 0.95),
            makeSearchResult('b', 0.90),  // gap: 0.05 - OK
            makeSearchResult('c', 0.70),  // gap: 0.20 - exceeds 0.1, cut off
            makeSearchResult('d', 0.65),
        ];

        $filtered = $reranker->rerank($results, 'query');

        expect($filtered)->toHaveCount(2);
        expect($filtered[0]->document->id)->toBe('a');
        expect($filtered[1]->document->id)->toBe('b');
    });

    it('applies score gap then max results', function () {
        $reranker = new ScoreThresholdReranker(threshold: 0.0, maxResults: 1);
        $reranker->setMinScoreGap(0.1);

        $results = [
            makeSearchResult('a', 0.95),
            makeSearchResult('b', 0.90),
            makeSearchResult('c', 0.70),
        ];

        $filtered = $reranker->rerank($results, 'query');

        // Score gap keeps a and b, maxResults limits to 1
        expect($filtered)->toHaveCount(1);
        expect($filtered[0]->document->id)->toBe('a');
    });

    it('disables score gap when set to null', function () {
        $reranker = new ScoreThresholdReranker(threshold: 0.0);
        $reranker->setMinScoreGap(0.01);
        $reranker->setMinScoreGap(null);

        $results = [
            makeSearchResult('a', 0.9),
            makeSearchResult('b', 0.5),
            makeSearchResult('c', 0.1),
        ];

        $filtered = $reranker->rerank($results, 'q');

        expect($filtered)->toHaveCount(3);
    });

    it('returns fluent interface from all setters', function () {
        $reranker = new ScoreThresholdReranker;

        expect($reranker->setThreshold(0.5))->toBeInstanceOf(ScoreThresholdReranker::class);
        expect($reranker->setMaxResults(10))->toBeInstanceOf(ScoreThresholdReranker::class);
        expect($reranker->setMinScoreGap(0.1))->toBeInstanceOf(ScoreThresholdReranker::class);
    });

    it('combines threshold and maxResults filtering', function () {
        $reranker = new ScoreThresholdReranker(threshold: 0.5, maxResults: 2);

        $results = [
            makeSearchResult('a', 0.95),
            makeSearchResult('b', 0.80),
            makeSearchResult('c', 0.60),
            makeSearchResult('d', 0.40),  // below threshold
        ];

        $filtered = $reranker->rerank($results, 'query');

        expect($filtered)->toHaveCount(2);
        expect($filtered[0]->document->id)->toBe('a');
        expect($filtered[1]->document->id)->toBe('b');
    });

    it('returns re-indexed array values', function () {
        $reranker = new ScoreThresholdReranker(threshold: 0.5);

        $results = [
            makeSearchResult('low', 0.3),
            makeSearchResult('high', 0.9),
        ];

        $filtered = $reranker->rerank($results, 'q');

        // Should have consecutive 0-based keys
        expect(array_keys($filtered))->toBe([0]);
        expect($filtered[0]->document->id)->toBe('high');
    });
});
