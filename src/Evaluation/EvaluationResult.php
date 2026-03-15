<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation;

use JsonSerializable;

/**
 * Evaluation Result - Aggregated results from running a test suite.
 */
class EvaluationResult implements JsonSerializable
{
    /**
     * Create a new evaluation result.
     *
     * @param  string  $suiteClass  The test suite class name
     * @param  string  $agentClass  The agent class being tested
     * @param  array<TestCaseResult>  $results  Individual test case results
     * @param  float  $totalDurationMs  Total time in milliseconds
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function __construct(
        public readonly string $suiteClass,
        public readonly string $agentClass,
        public readonly array $results,
        public readonly float $totalDurationMs = 0,
        public readonly array $metadata = [],
    ) {}

    /**
     * Get total number of test cases.
     */
    public function total(): int
    {
        return count($this->results);
    }

    /**
     * Get number of passed tests.
     */
    public function passedCount(): int
    {
        return count(array_filter($this->results, fn (TestCaseResult $r) => $r->hasPassed()));
    }

    /**
     * Get number of failed tests.
     */
    public function failedCount(): int
    {
        return count(array_filter($this->results, fn (TestCaseResult $r) => $r->hasFailed()));
    }

    /**
     * Get number of errored tests.
     */
    public function errorCount(): int
    {
        return count(array_filter($this->results, fn (TestCaseResult $r) => $r->hasErrored()));
    }

    /**
     * Get number of skipped tests.
     */
    public function skippedCount(): int
    {
        return count(array_filter($this->results, fn (TestCaseResult $r) => $r->wasSkipped()));
    }

    /**
     * Get pass rate as a percentage.
     */
    public function passRate(): float
    {
        $total = $this->total();
        if ($total === 0) {
            return 0.0;
        }

        return ($this->passedCount() / $total) * 100;
    }

    /**
     * Check if all tests passed.
     */
    public function allPassed(): bool
    {
        return $this->passedCount() === $this->total();
    }

    /**
     * Check if any tests failed.
     */
    public function hasFailed(): bool
    {
        return $this->failedCount() > 0;
    }

    /**
     * Check if any tests errored.
     */
    public function hasErrors(): bool
    {
        return $this->errorCount() > 0;
    }

    /**
     * Get all passed tests.
     *
     * @return array<TestCaseResult>
     */
    public function passed(): array
    {
        return array_filter($this->results, fn (TestCaseResult $r) => $r->hasPassed());
    }

    /**
     * Get all failed tests.
     *
     * @return array<TestCaseResult>
     */
    public function failed(): array
    {
        return array_values(array_filter($this->results, fn (TestCaseResult $r) => $r->hasFailed()));
    }

    /**
     * Get all errored tests.
     *
     * @return array<TestCaseResult>
     */
    public function errors(): array
    {
        return array_values(array_filter($this->results, fn (TestCaseResult $r) => $r->hasErrored()));
    }

    /**
     * Get average metric score across all tests.
     */
    public function averageMetricScore(): float
    {
        $scores = array_map(
            fn (TestCaseResult $r) => $r->getAverageMetricScore(),
            $this->results,
        );

        $nonZeroScores = array_filter($scores, fn ($s) => $s > 0);

        if (empty($nonZeroScores)) {
            return 0.0;
        }

        return array_sum($nonZeroScores) / count($nonZeroScores);
    }

    /**
     * Get average metric scores grouped by metric name.
     *
     * @return array<string, float>
     */
    public function averageMetricsByName(): array
    {
        $metricScores = [];

        foreach ($this->results as $result) {
            foreach ($result->metricResults as $name => $metric) {
                if (! isset($metricScores[$name])) {
                    $metricScores[$name] = [];
                }
                $metricScores[$name][] = $metric->score;
            }
        }

        $averages = [];
        foreach ($metricScores as $name => $scores) {
            $averages[$name] = array_sum($scores) / count($scores);
        }

        return $averages;
    }

    /**
     * Get average duration per test.
     */
    public function averageDurationMs(): float
    {
        $total = $this->total();
        if ($total === 0) {
            return 0.0;
        }

        $totalDuration = array_sum(array_map(
            fn (TestCaseResult $r) => $r->durationMs,
            $this->results,
        ));

        return $totalDuration / $total;
    }

    /**
     * Get a result by test case name.
     */
    public function getResult(string $name): ?TestCaseResult
    {
        foreach ($this->results as $result) {
            if ($result->testCase->name === $name) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Get a summary of the evaluation.
     */
    public function summary(): array
    {
        return [
            'suite' => class_basename($this->suiteClass),
            'agent' => class_basename($this->agentClass),
            'total' => $this->total(),
            'passed' => $this->passedCount(),
            'failed' => $this->failedCount(),
            'errors' => $this->errorCount(),
            'skipped' => $this->skippedCount(),
            'pass_rate' => round($this->passRate(), 2),
            'average_metric_score' => round($this->averageMetricScore(), 3),
            'duration_ms' => round($this->totalDurationMs, 2),
        ];
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'suite_class' => $this->suiteClass,
            'agent_class' => $this->agentClass,
            'summary' => $this->summary(),
            'metrics_by_name' => $this->averageMetricsByName(),
            'results' => array_map(fn (TestCaseResult $r) => $r->toArray(), $this->results),
            'total_duration_ms' => $this->totalDurationMs,
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
