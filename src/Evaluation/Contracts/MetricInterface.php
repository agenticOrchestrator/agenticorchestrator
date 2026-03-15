<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation\Contracts;

use AgenticOrchestrator\Evaluation\MetricResult;
use AgenticOrchestrator\Evaluation\TestCase;

/**
 * Metric Interface - Contract for LLM-judged evaluation metrics.
 */
interface MetricInterface
{
    /**
     * Get the metric name.
     */
    public function name(): string;

    /**
     * Get the metric description.
     */
    public function description(): string;

    /**
     * Get the evaluation prompt for the LLM judge.
     *
     * @param  string  $input  The original input
     * @param  string  $actualOutput  The agent's output
     * @param  TestCase  $testCase  The full test case for context
     */
    public function getPrompt(string $input, string $actualOutput, TestCase $testCase): string;

    /**
     * Parse the LLM's response into a metric result.
     *
     * @param  string  $response  The LLM's response
     * @param  mixed  $config  The metric configuration from the test case
     */
    public function parseResponse(string $response, mixed $config): MetricResult;
}
