<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tracking;

use Closure;

/**
 * Latency Recorder - Records and analyzes latency metrics.
 */
class LatencyRecorder
{
    /**
     * Recorded latencies by category.
     *
     * @var array<string, array<float>>
     */
    protected array $recordings = [];

    /**
     * Maximum recordings to keep per category.
     */
    protected int $maxRecordings = 1000;

    /**
     * Create a new latency recorder.
     */
    public static function make(int $maxRecordings = 1000): static
    {
        $instance = new static;
        $instance->maxRecordings = $maxRecordings;

        return $instance;
    }

    /**
     * Record a latency value.
     *
     * @param  string  $category  The category (e.g., 'llm_call', 'tool_execution')
     * @param  float  $latencyMs  Latency in milliseconds
     */
    public function record(string $category, float $latencyMs): static
    {
        if (! isset($this->recordings[$category])) {
            $this->recordings[$category] = [];
        }

        $this->recordings[$category][] = $latencyMs;

        // Trim if exceeds max
        if (count($this->recordings[$category]) > $this->maxRecordings) {
            $this->recordings[$category] = array_slice(
                $this->recordings[$category],
                -$this->maxRecordings
            );
        }

        return $this;
    }

    /**
     * Measure the execution time of a callback.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return array{result: T, latency_ms: float}
     */
    public function measure(string $category, Closure $callback): array
    {
        $start = microtime(true);
        $result = $callback();
        $latencyMs = (microtime(true) - $start) * 1000;

        $this->record($category, $latencyMs);

        return [
            'result' => $result,
            'latency_ms' => $latencyMs,
        ];
    }

    /**
     * Get statistics for a category.
     */
    public function stats(string $category): array
    {
        $recordings = $this->recordings[$category] ?? [];

        if (empty($recordings)) {
            return [
                'count' => 0,
                'min' => 0,
                'max' => 0,
                'avg' => 0,
                'median' => 0,
                'p95' => 0,
                'p99' => 0,
                'total' => 0,
            ];
        }

        sort($recordings);
        $count = count($recordings);

        return [
            'count' => $count,
            'min' => round(min($recordings), 2),
            'max' => round(max($recordings), 2),
            'avg' => round(array_sum($recordings) / $count, 2),
            'median' => round($this->percentile($recordings, 50), 2),
            'p95' => round($this->percentile($recordings, 95), 2),
            'p99' => round($this->percentile($recordings, 99), 2),
            'total' => round(array_sum($recordings), 2),
        ];
    }

    /**
     * Get all statistics.
     *
     * @return array<string, array>
     */
    public function allStats(): array
    {
        $stats = [];

        foreach (array_keys($this->recordings) as $category) {
            $stats[$category] = $this->stats($category);
        }

        return $stats;
    }

    /**
     * Get raw recordings for a category.
     *
     * @return array<float>
     */
    public function get(string $category): array
    {
        return $this->recordings[$category] ?? [];
    }

    /**
     * Clear recordings for a category or all.
     */
    public function clear(?string $category = null): static
    {
        if ($category === null) {
            $this->recordings = [];
        } else {
            unset($this->recordings[$category]);
        }

        return $this;
    }

    /**
     * Get all categories.
     *
     * @return array<string>
     */
    public function categories(): array
    {
        return array_keys($this->recordings);
    }

    /**
     * Calculate percentile from sorted array.
     */
    protected function percentile(array $sorted, float $percentile): float
    {
        $count = count($sorted);
        $index = ($percentile / 100) * ($count - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);

        if ($lower === $upper) {
            return $sorted[$lower];
        }

        $fraction = $index - $lower;

        return $sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * $fraction;
    }

    /**
     * Export recordings to array.
     */
    public function toArray(): array
    {
        return [
            'recordings' => $this->recordings,
            'stats' => $this->allStats(),
        ];
    }
}
