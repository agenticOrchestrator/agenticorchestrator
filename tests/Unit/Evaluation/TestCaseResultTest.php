<?php

declare(strict_types=1);

use AgenticOrchestrator\Evaluation\AssertionResult;
use AgenticOrchestrator\Evaluation\MetricResult;
use AgenticOrchestrator\Evaluation\TestCase;
use AgenticOrchestrator\Evaluation\TestCaseResult;

describe('TestCaseResult', function () {
    beforeEach(function () {
        $this->testCase = new TestCase(name: 'test-case', input: 'Hello');
    });

    it('creates a passed result via static factory', function () {
        $result = TestCaseResult::passed(
            testCase: $this->testCase,
            actualOutput: 'Hello there',
            durationMs: 42.5,
        );

        expect($result->status)->toBe(TestCaseResult::STATUS_PASSED);
        expect($result->actualOutput)->toBe('Hello there');
        expect($result->durationMs)->toBe(42.5);
        expect($result->hasPassed())->toBeTrue();
        expect($result->hasFailed())->toBeFalse();
        expect($result->hasErrored())->toBeFalse();
        expect($result->wasSkipped())->toBeFalse();
    });

    it('creates a failed result via static factory', function () {
        $result = TestCaseResult::failed(
            testCase: $this->testCase,
            actualOutput: 'Bad response',
            durationMs: 100.0,
        );

        expect($result->status)->toBe(TestCaseResult::STATUS_FAILED);
        expect($result->hasPassed())->toBeFalse();
        expect($result->hasFailed())->toBeTrue();
    });

    it('creates an error result via static factory', function () {
        $exception = new RuntimeException('Something went wrong');
        $result = TestCaseResult::error(
            testCase: $this->testCase,
            error: $exception,
            durationMs: 5.0,
        );

        expect($result->status)->toBe(TestCaseResult::STATUS_ERROR);
        expect($result->hasErrored())->toBeTrue();
        expect($result->hasPassed())->toBeFalse();
        expect($result->error)->toBe($exception);
        expect($result->actualOutput)->toBeNull();
    });

    it('creates a skipped result via static factory', function () {
        $result = TestCaseResult::skipped(
            testCase: $this->testCase,
            reason: 'Missing API key',
        );

        expect($result->status)->toBe(TestCaseResult::STATUS_SKIPPED);
        expect($result->wasSkipped())->toBeTrue();
        expect($result->hasPassed())->toBeFalse();
        expect($result->metadata['skip_reason'])->toBe('Missing API key');
    });

    it('skipped result merges metadata', function () {
        $result = TestCaseResult::skipped(
            testCase: $this->testCase,
            reason: 'disabled',
            metadata: ['env' => 'ci'],
        );

        expect($result->metadata)->toBe(['env' => 'ci', 'skip_reason' => 'disabled']);
    });

    it('gets failed assertions', function () {
        $assertions = [
            'contains' => AssertionResult::pass('contains', 'Found keyword'),
            'matches' => AssertionResult::fail('matches', 'Pattern not matched'),
            'length' => AssertionResult::fail('length', 'Too short'),
        ];

        $result = new TestCaseResult(
            testCase: $this->testCase,
            status: TestCaseResult::STATUS_FAILED,
            actualOutput: 'output',
            assertionResults: $assertions,
        );

        $failed = $result->getFailedAssertions();

        expect($failed)->toHaveCount(2);
        expect(array_keys($failed))->toBe(['matches', 'length']);
    });

    it('calculates average metric score', function () {
        $metrics = [
            'relevance' => new MetricResult('relevance', 0.8),
            'accuracy' => new MetricResult('accuracy', 0.6),
            'safety' => new MetricResult('safety', 1.0),
        ];

        $result = new TestCaseResult(
            testCase: $this->testCase,
            status: TestCaseResult::STATUS_PASSED,
            actualOutput: 'output',
            metricResults: $metrics,
        );

        expect($result->getAverageMetricScore())->toEqualWithDelta(0.8, 0.001);
    });

    it('returns zero average when no metrics', function () {
        $result = TestCaseResult::passed(
            testCase: $this->testCase,
            actualOutput: 'output',
        );

        expect($result->getAverageMetricScore())->toBe(0.0);
    });

    it('gets specific assertion result by name', function () {
        $assertions = [
            'contains' => AssertionResult::pass('contains'),
        ];

        $result = new TestCaseResult(
            testCase: $this->testCase,
            status: TestCaseResult::STATUS_PASSED,
            actualOutput: 'output',
            assertionResults: $assertions,
        );

        expect($result->getAssertionResult('contains'))->toBeInstanceOf(AssertionResult::class);
        expect($result->getAssertionResult('contains')->passed)->toBeTrue();
        expect($result->getAssertionResult('unknown'))->toBeNull();
    });

    it('gets specific metric result by name', function () {
        $metrics = [
            'accuracy' => new MetricResult('accuracy', 0.9),
        ];

        $result = new TestCaseResult(
            testCase: $this->testCase,
            status: TestCaseResult::STATUS_PASSED,
            actualOutput: 'output',
            metricResults: $metrics,
        );

        expect($result->getMetricResult('accuracy'))->toBeInstanceOf(MetricResult::class);
        expect($result->getMetricResult('accuracy')->score)->toBe(0.9);
        expect($result->getMetricResult('unknown'))->toBeNull();
    });

    it('converts to array', function () {
        $assertions = [
            'contains' => AssertionResult::pass('contains', 'Found it'),
        ];
        $metrics = [
            'relevance' => new MetricResult('relevance', 0.85),
        ];

        $result = new TestCaseResult(
            testCase: $this->testCase,
            status: TestCaseResult::STATUS_PASSED,
            actualOutput: 'Test output',
            assertionResults: $assertions,
            metricResults: $metrics,
            durationMs: 75.0,
            metadata: ['key' => 'value'],
        );

        $array = $result->toArray();

        expect($array['test_case'])->toBe('test-case');
        expect($array['status'])->toBe('passed');
        expect($array['actual_output'])->toBe('Test output');
        expect($array['duration_ms'])->toBe(75.0);
        expect($array['error'])->toBeNull();
        expect($array['metadata'])->toBe(['key' => 'value']);
        expect($array['assertion_results'])->toHaveCount(1);
        expect($array['metric_results'])->toHaveCount(1);
    });

    it('includes error message in array when error exists', function () {
        $result = TestCaseResult::error(
            testCase: $this->testCase,
            error: new RuntimeException('Boom'),
        );

        $array = $result->toArray();

        expect($array['error'])->toBe('Boom');
    });

    it('serializes to JSON', function () {
        $result = TestCaseResult::passed(
            testCase: $this->testCase,
            actualOutput: 'output',
        );

        $json = json_encode($result);

        expect($json)->toBeString();
        $decoded = json_decode($json, true);
        expect($decoded['status'])->toBe('passed');
        expect($decoded['test_case'])->toBe('test-case');
    });

    it('implements JsonSerializable', function () {
        $result = TestCaseResult::passed(
            testCase: $this->testCase,
            actualOutput: 'output',
        );

        expect($result)->toBeInstanceOf(JsonSerializable::class);
        expect($result->jsonSerialize())->toBe($result->toArray());
    });

    it('preserves assertion and metric results in passed factory', function () {
        $assertions = ['check' => AssertionResult::pass('check')];
        $metrics = ['tone' => new MetricResult('tone', 0.7)];

        $result = TestCaseResult::passed(
            testCase: $this->testCase,
            actualOutput: 'output',
            assertionResults: $assertions,
            metricResults: $metrics,
            durationMs: 10.0,
            metadata: ['meta' => 'data'],
        );

        expect($result->assertionResults)->toBe($assertions);
        expect($result->metricResults)->toBe($metrics);
        expect($result->durationMs)->toBe(10.0);
        expect($result->metadata)->toBe(['meta' => 'data']);
    });
});
