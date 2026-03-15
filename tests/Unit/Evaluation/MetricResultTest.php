<?php

declare(strict_types=1);

use AgenticOrchestrator\Evaluation\MetricResult;

describe('MetricResult', function () {
    it('creates a metric result with all properties', function () {
        $result = new MetricResult(
            name: 'accuracy',
            score: 0.85,
            reasoning: 'Good accuracy overall',
            threshold: 0.7,
            metadata: ['model' => 'gpt-4'],
        );

        expect($result->name)->toBe('accuracy');
        expect($result->score)->toBe(0.85);
        expect($result->reasoning)->toBe('Good accuracy overall');
        expect($result->threshold)->toBe(0.7);
        expect($result->metadata)->toBe(['model' => 'gpt-4']);
    });

    it('uses default values for optional parameters', function () {
        $result = new MetricResult(name: 'relevance', score: 0.5);

        expect($result->reasoning)->toBe('');
        expect($result->threshold)->toBe(0.7);
        expect($result->metadata)->toBe([]);
    });

    it('passes when score meets threshold', function () {
        $result = new MetricResult(name: 'test', score: 0.8, threshold: 0.7);

        expect($result->passes())->toBeTrue();
    });

    it('passes when score equals threshold', function () {
        $result = new MetricResult(name: 'test', score: 0.7, threshold: 0.7);

        expect($result->passes())->toBeTrue();
    });

    it('fails when score is below threshold', function () {
        $result = new MetricResult(name: 'test', score: 0.5, threshold: 0.7);

        expect($result->passes())->toBeFalse();
    });

    it('calculates percentage from score', function () {
        expect((new MetricResult(name: 'test', score: 0.85))->getPercentage())->toBe(85.0);
        expect((new MetricResult(name: 'test', score: 1.0))->getPercentage())->toBe(100.0);
        expect((new MetricResult(name: 'test', score: 0.0))->getPercentage())->toBe(0.0);
    });

    it('returns correct grade for score ranges', function () {
        expect((new MetricResult(name: 'test', score: 0.95))->getGrade())->toBe('A');
        expect((new MetricResult(name: 'test', score: 0.90))->getGrade())->toBe('A');
        expect((new MetricResult(name: 'test', score: 0.85))->getGrade())->toBe('B');
        expect((new MetricResult(name: 'test', score: 0.80))->getGrade())->toBe('B');
        expect((new MetricResult(name: 'test', score: 0.75))->getGrade())->toBe('C');
        expect((new MetricResult(name: 'test', score: 0.70))->getGrade())->toBe('C');
        expect((new MetricResult(name: 'test', score: 0.65))->getGrade())->toBe('D');
        expect((new MetricResult(name: 'test', score: 0.60))->getGrade())->toBe('D');
        expect((new MetricResult(name: 'test', score: 0.55))->getGrade())->toBe('F');
        expect((new MetricResult(name: 'test', score: 0.0))->getGrade())->toBe('F');
    });

    it('converts to array with all fields', function () {
        $result = new MetricResult(
            name: 'accuracy',
            score: 0.85,
            reasoning: 'Solid accuracy',
            threshold: 0.8,
            metadata: ['key' => 'val'],
        );

        $array = $result->toArray();

        expect($array)->toBe([
            'name' => 'accuracy',
            'score' => 0.85,
            'percentage' => 85.0,
            'grade' => 'B',
            'passes' => true,
            'threshold' => 0.8,
            'reasoning' => 'Solid accuracy',
            'metadata' => ['key' => 'val'],
        ]);
    });

    it('serializes to JSON', function () {
        $result = new MetricResult(name: 'test', score: 0.9);

        $json = json_encode($result);

        expect($json)->toBeString();
        $decoded = json_decode($json, true);
        expect($decoded['name'])->toBe('test');
        expect($decoded['score'])->toBe(0.9);
        expect($decoded['grade'])->toBe('A');
        expect($decoded['passes'])->toBeTrue();
    });

    it('implements JsonSerializable', function () {
        $result = new MetricResult(name: 'test', score: 0.5);

        expect($result)->toBeInstanceOf(JsonSerializable::class);
        expect($result->jsonSerialize())->toBe($result->toArray());
    });
});
