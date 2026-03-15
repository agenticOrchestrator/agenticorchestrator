<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation\Metrics;

use AgenticOrchestrator\Evaluation\TestCase;

/**
 * Accuracy Metric - Measures factual correctness of the response.
 */
class AccuracyMetric extends AbstractMetric
{
    protected float $defaultThreshold = 0.8;

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'accuracy';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string
    {
        return 'Measures the factual correctness and accuracy of the response';
    }

    /**
     * {@inheritDoc}
     */
    public function getPrompt(string $input, string $actualOutput, TestCase $testCase): string
    {
        $reference = '';
        if ($testCase->expectedOutput) {
            $reference = <<<REF

Reference/Expected Output (use for comparison):
{$testCase->expectedOutput}
REF;
        }

        $context = '';
        if (! empty($testCase->context)) {
            $contextJson = json_encode($testCase->context, JSON_PRETTY_PRINT);
            $context = <<<CTX

Context Information (ground truth):
{$contextJson}
CTX;
        }

        return <<<PROMPT
{$this->getBasePrompt()}

Evaluation Criteria - ACCURACY:
- Are the facts and claims in the response correct?
- Does the response avoid false or misleading information?
- Are any numbers, dates, or specific details accurate?
- If a reference is provided, does the response align with it?

Scoring Guide:
- 1.0: Completely accurate, no errors
- 0.7-0.9: Mostly accurate with minor inaccuracies
- 0.4-0.6: Mix of accurate and inaccurate information
- 0.1-0.3: Mostly inaccurate
- 0.0: Completely false or fabricated

User Input:
{$input}

Agent Response:
{$actualOutput}
{$reference}
{$context}

Evaluate the ACCURACY of this response:
PROMPT;
    }
}
