<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tracking;

use Yethee\Tiktoken\EncoderProvider;

/**
 * Token Counter - Utility for counting and estimating tokens.
 */
class TokenCounter
{
    /**
     * Average characters per token for estimation.
     */
    protected const CHARS_PER_TOKEN = 4;

    /**
     * Known token multipliers by model family.
     */
    protected static array $modelMultipliers = [
        'gpt-4' => 1.0,
        'gpt-3.5' => 1.0,
        'claude' => 1.1,  // Claude uses slightly more tokens
        'llama' => 1.0,
        'mistral' => 1.0,
    ];

    /**
     * Count tokens in a string (estimation).
     */
    public static function count(string $text, ?string $model = null): int
    {
        if (empty($text)) {
            return 0;
        }

        // Use tiktoken if available
        if (class_exists('\Yethee\Tiktoken\EncoderProvider')) {
            return self::countWithTiktoken($text, $model);
        }

        // Fall back to estimation
        return self::estimate($text, $model);
    }

    /**
     * Estimate tokens using character count.
     */
    public static function estimate(string $text, ?string $model = null): int
    {
        $baseCount = (int) ceil(mb_strlen($text) / self::CHARS_PER_TOKEN);

        // Apply model multiplier if known
        $multiplier = self::getModelMultiplier($model);

        return (int) ceil($baseCount * $multiplier);
    }

    /**
     * Count tokens using tiktoken library.
     */
    protected static function countWithTiktoken(string $text, ?string $model = null): int
    {
        try {
            $provider = new EncoderProvider;

            // Map model to encoding
            $encoding = match (true) {
                str_contains($model ?? '', 'gpt-4') => 'cl100k_base',
                str_contains($model ?? '', 'gpt-3.5') => 'cl100k_base',
                default => 'cl100k_base',
            };

            $encoder = $provider->get($encoding);

            return count($encoder->encode($text));
        } catch (\Throwable) {
            return self::estimate($text, $model);
        }
    }

    /**
     * Count tokens in an array of messages.
     *
     * @param  array<array{role: string, content: string}>  $messages
     */
    public static function countMessages(array $messages, ?string $model = null): int
    {
        $total = 0;

        // Overhead per message (role tokens, etc.)
        $perMessageOverhead = 4;

        foreach ($messages as $message) {
            $content = $message['content'] ?? '';
            $total += self::count($content, $model);
            $total += $perMessageOverhead;
        }

        // Base overhead for the conversation
        $total += 3;

        return $total;
    }

    /**
     * Get the model multiplier.
     */
    protected static function getModelMultiplier(?string $model): float
    {
        if ($model === null) {
            return 1.0;
        }

        $modelLower = strtolower($model);

        foreach (self::$modelMultipliers as $family => $multiplier) {
            if (str_contains($modelLower, $family)) {
                return $multiplier;
            }
        }

        return 1.0;
    }

    /**
     * Format token count for display.
     */
    public static function format(int $tokens): string
    {
        if ($tokens >= 1000000) {
            return round($tokens / 1000000, 2).'M';
        }

        if ($tokens >= 1000) {
            return round($tokens / 1000, 1).'K';
        }

        return (string) $tokens;
    }

    /**
     * Calculate cost for tokens based on pricing.
     *
     * @param  array{input: float, output: float}  $pricing  Per 1K token prices
     */
    public static function calculateCost(int $inputTokens, int $outputTokens, array $pricing): float
    {
        $inputCost = ($inputTokens / 1000) * $pricing['input'];
        $outputCost = ($outputTokens / 1000) * $pricing['output'];

        return $inputCost + $outputCost;
    }
}
