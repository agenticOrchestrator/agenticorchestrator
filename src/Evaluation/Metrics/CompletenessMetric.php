<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation\Metrics;

use AgenticOrchestrator\Evaluation\TestCase;

/**
 * Completeness Metric - Measures how completely the response addresses the input.
 */
class CompletenessMetric extends AbstractMetric
{
    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'completeness';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string
    {
        return 'Measures how completely the response addresses all aspects of the input';
    }

    /**
     * {@inheritDoc}
     */
    public function getPrompt(string $input, string $actualOutput, TestCase $testCase): string
    {
        $requiredElements = '';
        if (isset($testCase->metadata['required_elements'])) {
            $elements = implode("\n- ", $testCase->metadata['required_elements']);
            $requiredElements = <<<REQ

Required Elements (all should be addressed):
- {$elements}
REQ;
        }

        return <<<PROMPT
{$this->getBasePrompt()}

Evaluation Criteria - COMPLETENESS:
- Does the response address all parts of the user's input?
- Are there any unanswered questions?
- Does it cover the topic thoroughly without unnecessary verbosity?
- If multiple points were raised, are all addressed?
{$requiredElements}

Scoring Guide:
- 1.0: Completely addresses all aspects
- 0.7-0.9: Addresses most aspects, minor omissions
- 0.4-0.6: Addresses some aspects, notable gaps
- 0.1-0.3: Addresses few aspects, major omissions
- 0.0: Fails to address the input at all

User Input:
{$input}

Agent Response:
{$actualOutput}

Evaluate the COMPLETENESS of this response:
PROMPT;
    }
}
