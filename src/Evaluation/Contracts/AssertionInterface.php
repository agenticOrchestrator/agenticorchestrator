<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation\Contracts;

use AgenticOrchestrator\Evaluation\AssertionResult;
use AgenticOrchestrator\Evaluation\TestCase;

/**
 * Assertion Interface - Contract for evaluation assertions.
 */
interface AssertionInterface
{
    /**
     * Get the assertion name.
     */
    public function name(): string;

    /**
     * Evaluate the assertion against the actual output.
     *
     * @param  string  $actualOutput  The actual output from the agent
     * @param  mixed  $config  The assertion configuration from the test case
     * @param  TestCase  $testCase  The full test case for context
     */
    public function evaluate(string $actualOutput, mixed $config, TestCase $testCase): AssertionResult;
}
