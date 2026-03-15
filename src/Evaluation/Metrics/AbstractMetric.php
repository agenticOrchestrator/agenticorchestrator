<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation\Metrics;

use AgenticOrchestrator\Evaluation\Contracts\MetricInterface;
use AgenticOrchestrator\Evaluation\MetricResult;

/**
 * Abstract Metric - Base class for LLM-judged metrics.
 */
abstract class AbstractMetric implements MetricInterface
{
    /**
     * Default threshold for passing.
     */
    protected float $defaultThreshold = 0.7;

    /**
     * {@inheritDoc}
     */
    public function parseResponse(string $response, mixed $config): MetricResult
    {
        $threshold = $config['threshold'] ?? $this->defaultThreshold;

        // Try to extract score from various formats
        $score = $this->extractScore($response);
        $reasoning = $this->extractReasoning($response);

        return new MetricResult(
            name: $this->name(),
            score: $score,
            reasoning: $reasoning,
            threshold: $threshold,
            metadata: ['raw_response' => $response],
        );
    }

    /**
     * Extract the score from the LLM response.
     */
    protected function extractScore(string $response): float
    {
        // Try JSON format first
        if (preg_match('/\{[^}]*"score"\s*:\s*([\d.]+)[^}]*\}/s', $response, $matches)) {
            return min(1.0, max(0.0, (float) $matches[1]));
        }

        // Try "Score: X/10" or "Score: X"
        if (preg_match('/score[:\s]+(\d+(?:\.\d+)?)\s*(?:\/\s*10|\/\s*100|%)?/i', $response, $matches)) {
            $score = (float) $matches[1];

            // Normalize to 0-1
            if ($score > 1) {
                $score = $score > 10 ? $score / 100 : $score / 10;
            }

            return min(1.0, max(0.0, $score));
        }

        // Try percentage
        if (preg_match('/(\d+(?:\.\d+)?)\s*%/', $response, $matches)) {
            return min(1.0, max(0.0, (float) $matches[1] / 100));
        }

        // Try decimal at the start
        if (preg_match('/^(0?\.\d+|\d\.\d+)/', trim($response), $matches)) {
            return min(1.0, max(0.0, (float) $matches[1]));
        }

        // Default to middle score if parsing fails
        return 0.5;
    }

    /**
     * Extract reasoning from the LLM response.
     */
    protected function extractReasoning(string $response): string
    {
        // Try JSON format
        if (preg_match('/"reasoning"\s*:\s*"([^"]+)"/s', $response, $matches)) {
            return $matches[1];
        }

        // Try "Reasoning: ..." format
        if (preg_match('/reasoning[:\s]+(.+?)(?:\n\n|\n[A-Z]|$)/is', $response, $matches)) {
            return trim($matches[1]);
        }

        // Try "Explanation: ..." format
        if (preg_match('/explanation[:\s]+(.+?)(?:\n\n|\n[A-Z]|$)/is', $response, $matches)) {
            return trim($matches[1]);
        }

        // Return full response as reasoning (stripped of score)
        $cleaned = preg_replace('/score[:\s]+\d+(?:\.\d+)?[^\n]*/i', '', $response);

        return trim($cleaned);
    }

    /**
     * Get the base prompt template.
     */
    protected function getBasePrompt(): string
    {
        return <<<'PROMPT'
You are an evaluation judge. Evaluate the following response on a scale of 0.0 to 1.0.

Respond in JSON format:
{
    "score": 0.X,
    "reasoning": "Brief explanation of your evaluation"
}
PROMPT;
    }
}
