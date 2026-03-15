<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation\Assertions;

use AgenticOrchestrator\Evaluation\AssertionResult;
use AgenticOrchestrator\Evaluation\Contracts\AssertionInterface;
use AgenticOrchestrator\Evaluation\TestCase;

/**
 * Not Contains Assertion - Checks if output does NOT contain specified strings.
 */
class NotContainsAssertion implements AssertionInterface
{
    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'not_contains';
    }

    /**
     * {@inheritDoc}
     */
    public function evaluate(string $actualOutput, mixed $config, TestCase $testCase): AssertionResult
    {
        $forbidden = is_array($config) ? $config : [$config];
        $caseSensitive = $testCase->metadata['case_sensitive'] ?? false;

        $output = $caseSensitive ? $actualOutput : mb_strtolower($actualOutput);
        $found = [];

        foreach ($forbidden as $needle) {
            $searchNeedle = $caseSensitive ? $needle : mb_strtolower($needle);

            if (str_contains($output, $searchNeedle)) {
                $found[] = $needle;
            }
        }

        if (empty($found)) {
            return AssertionResult::pass(
                name: $this->name(),
                message: 'Output does not contain any forbidden strings',
                expected: 'None of: '.implode(', ', $forbidden),
                actual: 'None found',
            );
        }

        return AssertionResult::fail(
            name: $this->name(),
            message: 'Output contains forbidden strings: '.implode(', ', $found),
            expected: 'None of: '.implode(', ', $forbidden),
            actual: $found,
            metadata: ['found' => $found],
        );
    }
}
