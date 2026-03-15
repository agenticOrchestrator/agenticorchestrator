<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation\Assertions;

use AgenticOrchestrator\Evaluation\AssertionResult;
use AgenticOrchestrator\Evaluation\Contracts\AssertionInterface;
use AgenticOrchestrator\Evaluation\TestCase;

/**
 * Length Assertion - Checks if output length is within bounds.
 */
class LengthAssertion implements AssertionInterface
{
    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'length';
    }

    /**
     * {@inheritDoc}
     */
    public function evaluate(string $actualOutput, mixed $config, TestCase $testCase): AssertionResult
    {
        $length = mb_strlen($actualOutput);

        // Config can be: int (exact), [min, max], ['min' => x], ['max' => y], ['min' => x, 'max' => y]
        if (is_int($config)) {
            if ($length === $config) {
                return AssertionResult::pass(
                    name: $this->name(),
                    message: "Output length is exactly {$config} characters",
                    expected: $config,
                    actual: $length,
                );
            }

            return AssertionResult::fail(
                name: $this->name(),
                message: "Expected length {$config}, got {$length}",
                expected: $config,
                actual: $length,
            );
        }

        if (is_array($config)) {
            $min = $config['min'] ?? $config[0] ?? null;
            $max = $config['max'] ?? $config[1] ?? null;

            if ($min !== null && $length < $min) {
                return AssertionResult::fail(
                    name: $this->name(),
                    message: "Output length {$length} is less than minimum {$min}",
                    expected: ['min' => $min, 'max' => $max],
                    actual: $length,
                );
            }

            if ($max !== null && $length > $max) {
                return AssertionResult::fail(
                    name: $this->name(),
                    message: "Output length {$length} exceeds maximum {$max}",
                    expected: ['min' => $min, 'max' => $max],
                    actual: $length,
                );
            }

            return AssertionResult::pass(
                name: $this->name(),
                message: "Output length {$length} is within bounds",
                expected: ['min' => $min, 'max' => $max],
                actual: $length,
            );
        }

        return AssertionResult::fail(
            name: $this->name(),
            message: 'Invalid length configuration',
            expected: $config,
            actual: $length,
        );
    }
}
