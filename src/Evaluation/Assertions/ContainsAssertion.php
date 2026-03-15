<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation\Assertions;

use AgenticOrchestrator\Evaluation\AssertionResult;
use AgenticOrchestrator\Evaluation\Contracts\AssertionInterface;
use AgenticOrchestrator\Evaluation\TestCase;

/**
 * Contains Assertion - Checks if output contains specified strings.
 */
class ContainsAssertion implements AssertionInterface
{
    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'contains';
    }

    /**
     * {@inheritDoc}
     */
    public function evaluate(string $actualOutput, mixed $config, TestCase $testCase): AssertionResult
    {
        $needles = is_array($config) ? $config : [$config];
        $caseSensitive = $testCase->metadata['case_sensitive'] ?? false;

        $output = $caseSensitive ? $actualOutput : mb_strtolower($actualOutput);
        $missing = [];

        foreach ($needles as $needle) {
            $searchNeedle = $caseSensitive ? $needle : mb_strtolower($needle);

            if (! str_contains($output, $searchNeedle)) {
                $missing[] = $needle;
            }
        }

        if (empty($missing)) {
            return AssertionResult::pass(
                name: $this->name(),
                message: 'Output contains all expected strings',
                expected: $needles,
                actual: 'All found',
            );
        }

        return AssertionResult::fail(
            name: $this->name(),
            message: 'Output missing expected strings: '.implode(', ', $missing),
            expected: $needles,
            actual: $missing,
            metadata: ['missing' => $missing],
        );
    }
}
