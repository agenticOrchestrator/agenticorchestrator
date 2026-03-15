<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation\Metrics;

use AgenticOrchestrator\Evaluation\TestCase;

/**
 * Helpfulness Metric - Measures how helpful the response is to the user.
 */
class HelpfulnessMetric extends AbstractMetric
{
    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'helpfulness';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string
    {
        return 'Measures how helpful and useful the response is to the user';
    }

    /**
     * {@inheritDoc}
     */
    public function getPrompt(string $input, string $actualOutput, TestCase $testCase): string
    {
        return <<<PROMPT
{$this->getBasePrompt()}

Evaluation Criteria - HELPFULNESS:
- Does the response provide useful, actionable information?
- Does it fully address the user's needs?
- Is the response clear and easy to understand?
- Does it go beyond a minimal answer to be truly helpful?
- Would the user feel satisfied with this response?

Scoring Guide:
- 1.0: Exceptionally helpful, exceeds expectations
- 0.7-0.9: Very helpful, addresses the need well
- 0.4-0.6: Somewhat helpful but incomplete
- 0.1-0.3: Minimally helpful or confusing
- 0.0: Not helpful at all

User Input:
{$input}

Agent Response:
{$actualOutput}

Evaluate the HELPFULNESS of this response:
PROMPT;
    }
}
