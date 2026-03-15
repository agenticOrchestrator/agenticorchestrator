<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation;

use JsonSerializable;
use Throwable;

/**
 * Test Case Result - Results from running a single test case.
 */
class TestCaseResult implements JsonSerializable
{
    public const STATUS_PASSED = 'passed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ERROR = 'error';

    public const STATUS_SKIPPED = 'skipped';

    /**
     * Create a new test case result.
     *
     * @param  TestCase  $testCase  The test case that was run
     * @param  string  $status  Result status (passed, failed, error, skipped)
     * @param  string|null  $actualOutput  The actual output from the agent
     * @param  array<string, AssertionResult>  $assertionResults  Results of each assertion
     * @param  array<string, MetricResult>  $metricResults  Results of each metric evaluation
     * @param  float  $durationMs  Time taken in milliseconds
     * @param  Throwable|null  $error  Error if status is 'error'
     * @param  array<string, mixed>  $metadata  Additional result metadata
     */
    public function __construct(
        public readonly TestCase $testCase,
        public readonly string $status,
        public readonly ?string $actualOutput = null,
        public readonly array $assertionResults = [],
        public readonly array $metricResults = [],
        public readonly float $durationMs = 0,
        public readonly ?Throwable $error = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a passed result.
     */
    public static function passed(
        TestCase $testCase,
        string $actualOutput,
        array $assertionResults = [],
        array $metricResults = [],
        float $durationMs = 0,
        array $metadata = [],
    ): static {
        return new static(
            testCase: $testCase,
            status: self::STATUS_PASSED,
            actualOutput: $actualOutput,
            assertionResults: $assertionResults,
            metricResults: $metricResults,
            durationMs: $durationMs,
            metadata: $metadata,
        );
    }

    /**
     * Create a failed result.
     */
    public static function failed(
        TestCase $testCase,
        string $actualOutput,
        array $assertionResults = [],
        array $metricResults = [],
        float $durationMs = 0,
        array $metadata = [],
    ): static {
        return new static(
            testCase: $testCase,
            status: self::STATUS_FAILED,
            actualOutput: $actualOutput,
            assertionResults: $assertionResults,
            metricResults: $metricResults,
            durationMs: $durationMs,
            metadata: $metadata,
        );
    }

    /**
     * Create an error result.
     */
    public static function error(
        TestCase $testCase,
        Throwable $error,
        float $durationMs = 0,
        array $metadata = [],
    ): static {
        return new static(
            testCase: $testCase,
            status: self::STATUS_ERROR,
            error: $error,
            durationMs: $durationMs,
            metadata: $metadata,
        );
    }

    /**
     * Create a skipped result.
     */
    public static function skipped(
        TestCase $testCase,
        string $reason = '',
        array $metadata = [],
    ): static {
        return new static(
            testCase: $testCase,
            status: self::STATUS_SKIPPED,
            metadata: array_merge($metadata, ['skip_reason' => $reason]),
        );
    }

    /**
     * Check if the test passed.
     */
    public function hasPassed(): bool
    {
        return $this->status === self::STATUS_PASSED;
    }

    /**
     * Check if the test failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if the test errored.
     */
    public function hasErrored(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    /**
     * Check if the test was skipped.
     */
    public function wasSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    /**
     * Get failed assertions.
     *
     * @return array<string, AssertionResult>
     */
    public function getFailedAssertions(): array
    {
        return array_filter(
            $this->assertionResults,
            fn (AssertionResult $result) => ! $result->passed,
        );
    }

    /**
     * Get the average metric score.
     */
    public function getAverageMetricScore(): float
    {
        if (empty($this->metricResults)) {
            return 0.0;
        }

        $sum = array_sum(array_map(
            fn (MetricResult $result) => $result->score,
            $this->metricResults,
        ));

        return $sum / count($this->metricResults);
    }

    /**
     * Get a specific assertion result.
     */
    public function getAssertionResult(string $name): ?AssertionResult
    {
        return $this->assertionResults[$name] ?? null;
    }

    /**
     * Get a specific metric result.
     */
    public function getMetricResult(string $name): ?MetricResult
    {
        return $this->metricResults[$name] ?? null;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'test_case' => $this->testCase->name,
            'status' => $this->status,
            'actual_output' => $this->actualOutput,
            'assertion_results' => array_map(
                fn (AssertionResult $r) => $r->toArray(),
                $this->assertionResults,
            ),
            'metric_results' => array_map(
                fn (MetricResult $r) => $r->toArray(),
                $this->metricResults,
            ),
            'duration_ms' => $this->durationMs,
            'error' => $this->error?->getMessage(),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
