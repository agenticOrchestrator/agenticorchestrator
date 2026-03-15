<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Tracking;

use AgenticOrchestrator\Tracking\TokenCounter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenCounter::class)]
class TokenCounterTest extends TestCase
{
    #[Test]
    public function it_estimates_tokens_from_text(): void
    {
        $text = 'Hello, this is a test message for token counting.';

        $tokens = TokenCounter::estimate($text);

        // Approximately 12-13 tokens for this text
        $this->assertGreaterThan(10, $tokens);
        $this->assertLessThan(20, $tokens);
    }

    #[Test]
    public function it_returns_zero_for_empty_text(): void
    {
        $this->assertSame(0, TokenCounter::count(''));
    }

    #[Test]
    public function it_counts_message_tokens(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
        ];

        $tokens = TokenCounter::countMessages($messages);

        $this->assertGreaterThan(5, $tokens);
    }

    #[Test]
    public function it_formats_token_counts(): void
    {
        $this->assertSame('500', TokenCounter::format(500));
        $this->assertSame('1.5K', TokenCounter::format(1500));
        $this->assertSame('1.25M', TokenCounter::format(1250000));
    }

    #[Test]
    public function it_calculates_cost(): void
    {
        $pricing = ['input' => 0.01, 'output' => 0.03];

        $cost = TokenCounter::calculateCost(
            inputTokens: 1000,
            outputTokens: 500,
            pricing: $pricing
        );

        // 1000 * 0.01/1000 + 500 * 0.03/1000 = 0.01 + 0.015 = 0.025
        $this->assertEqualsWithDelta(0.025, $cost, 0.0001);
    }
}
