<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation\Metrics;

use AgenticOrchestrator\Evaluation\TestCase;

/**
 * Tone Metric - Measures if the response has the appropriate tone.
 */
class ToneMetric extends AbstractMetric
{
    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'tone';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string
    {
        return 'Measures if the response has the appropriate tone for the context';
    }

    /**
     * {@inheritDoc}
     */
    public function getPrompt(string $input, string $actualOutput, TestCase $testCase): string
    {
        $expectedTone = $testCase->metadata['expected_tone'] ?? 'professional and friendly';

        return <<<PROMPT
{$this->getBasePrompt()}

Evaluation Criteria - TONE:
- Does the response match the expected tone: "{$expectedTone}"?
- Is the tone appropriate for the context of the conversation?
- Is it professional without being cold or robotic?
- Does it show appropriate empathy if the situation calls for it?
- Is the language level appropriate for the audience?

Scoring Guide:
- 1.0: Perfect tone match, very natural
- 0.7-0.9: Good tone, minor adjustments could help
- 0.4-0.6: Tone is acceptable but noticeably off
- 0.1-0.3: Inappropriate tone for the context
- 0.0: Completely wrong tone (rude, dismissive, etc.)

User Input:
{$input}

Agent Response:
{$actualOutput}

Expected Tone: {$expectedTone}

Evaluate the TONE of this response:
PROMPT;
    }
}
