<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation;

use JsonSerializable;

/**
 * Metric Result - Result from an LLM-judged metric evaluation.
 */
class MetricResult implements JsonSerializable
{
    /**
     * Create a new metric result.
     *
     * @param  string  $name  The metric name
     * @param  float  $score  Score from 0.0 to 1.0
     * @param  string  $reasoning  LLM's reasoning for the score
     * @param  float  $threshold  Minimum score to pass
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function __construct(
        public readonly string $name,
        public readonly float $score,
        public readonly string $reasoning = '',
        public readonly float $threshold = 0.7,
        public readonly array $metadata = [],
    ) {}

    /**
     * Check if the metric passes the threshold.
     */
    public function passes(): bool
    {
        return $this->score >= $this->threshold;
    }

    /**
     * Get the score as a percentage.
     */
    public function getPercentage(): float
    {
        return $this->score * 100;
    }

    /**
     * Get the score as a grade (A, B, C, D, F).
     */
    public function getGrade(): string
    {
        return match (true) {
            $this->score >= 0.9 => 'A',
            $this->score >= 0.8 => 'B',
            $this->score >= 0.7 => 'C',
            $this->score >= 0.6 => 'D',
            default => 'F',
        };
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'score' => $this->score,
            'percentage' => $this->getPercentage(),
            'grade' => $this->getGrade(),
            'passes' => $this->passes(),
            'threshold' => $this->threshold,
            'reasoning' => $this->reasoning,
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
