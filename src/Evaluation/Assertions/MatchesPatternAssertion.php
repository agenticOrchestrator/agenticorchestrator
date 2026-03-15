<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation\Assertions;

use AgenticOrchestrator\Evaluation\AssertionResult;
use AgenticOrchestrator\Evaluation\Contracts\AssertionInterface;
use AgenticOrchestrator\Evaluation\TestCase;

/**
 * Matches Pattern Assertion - Checks if output matches a regex pattern.
 */
class MatchesPatternAssertion implements AssertionInterface
{
    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'matches';
    }

    /**
     * {@inheritDoc}
     */
    public function evaluate(string $actualOutput, mixed $config, TestCase $testCase): AssertionResult
    {
        $patterns = is_array($config) ? $config : [$config];
        $failed = [];

        foreach ($patterns as $pattern) {
            // Ensure pattern has delimiters
            if (! preg_match('/^[\/\#\~\@]/', $pattern)) {
                $pattern = '/'.$pattern.'/i';
            }

            if (@preg_match($pattern, $actualOutput) !== 1) {
                $failed[] = $pattern;
            }
        }

        if (empty($failed)) {
            return AssertionResult::pass(
                name: $this->name(),
                message: 'Output matches all patterns',
                expected: $patterns,
                actual: 'All matched',
            );
        }

        return AssertionResult::fail(
            name: $this->name(),
            message: 'Output does not match patterns: '.implode(', ', $failed),
            expected: $patterns,
            actual: $failed,
            metadata: ['unmatched' => $failed],
        );
    }
}
