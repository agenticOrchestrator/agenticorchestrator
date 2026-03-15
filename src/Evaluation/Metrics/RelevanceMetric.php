<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation\Metrics;

use AgenticOrchestrator\Evaluation\TestCase;

/**
 * Relevance Metric - Measures how relevant the response is to the input.
 */
class RelevanceMetric extends AbstractMetric
{
    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'relevance';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string
    {
        return 'Measures how relevant and on-topic the response is to the user\'s input';
    }

    /**
     * {@inheritDoc}
     */
    public function getPrompt(string $input, string $actualOutput, TestCase $testCase): string
    {
        $context = '';
        if ($testCase->expectedOutput) {
            $context = "\n\nExpected/Reference Output:\n{$testCase->expectedOutput}";
        }

        return <<<PROMPT
{$this->getBasePrompt()}

Evaluation Criteria - RELEVANCE:
- Does the response directly address the user's input?
- Is the response on-topic and focused?
- Does it avoid irrelevant tangents or information?
- Would the user find this response helpful for their specific query?

Scoring Guide:
- 1.0: Perfectly relevant, directly addresses the input
- 0.7-0.9: Mostly relevant with minor tangents
- 0.4-0.6: Partially relevant but misses key aspects
- 0.1-0.3: Mostly irrelevant or off-topic
- 0.0: Completely unrelated to the input

User Input:
{$input}

Agent Response:
{$actualOutput}
{$context}

Evaluate the RELEVANCE of this response:
PROMPT;
    }
}
