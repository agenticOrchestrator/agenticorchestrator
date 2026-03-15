<?php

declare(strict_types=1);

use AgenticOrchestrator\Tracking\TokenCounter;

describe('TokenCounter - Extended Coverage', function () {

    describe('count', function () {
        it('returns zero for empty string', function () {
            expect(TokenCounter::count(''))->toBe(0);
        });

        it('counts tokens for a non-empty string', function () {
            $tokens = TokenCounter::count('Hello world');
            expect($tokens)->toBeGreaterThan(0);
        });

        it('counts tokens with a specific model parameter', function () {
            $tokens = TokenCounter::count('Hello world', 'gpt-4o');
            expect($tokens)->toBeGreaterThan(0);
        });

        it('falls back to estimate when tiktoken is not available', function () {
            // tiktoken is not installed in the test environment, so count falls back to estimate
            $text = 'This is a test string for token counting.';
            $countResult = TokenCounter::count($text);
            $estimateResult = TokenCounter::estimate($text);

            expect($countResult)->toBe($estimateResult);
        });
    });

    describe('estimate', function () {
        it('estimates tokens based on character count', function () {
            // "abcd" = 4 chars / 4 chars_per_token = 1 token
            expect(TokenCounter::estimate('abcd'))->toBe(1);
        });

        it('rounds up for partial tokens', function () {
            // "abcde" = 5 chars / 4 = 1.25 => ceil = 2
            expect(TokenCounter::estimate('abcde'))->toBe(2);
        });

        it('applies claude model multiplier', function () {
            $text = str_repeat('a', 40); // 40 chars / 4 = 10 base tokens
            $withoutModel = TokenCounter::estimate($text);
            $withClaude = TokenCounter::estimate($text, 'claude-3-opus');

            // Claude multiplier is 1.1, so 10 * 1.1 = 11
            expect($withoutModel)->toBe(10)
                ->and($withClaude)->toBe(11);
        });

        it('applies gpt-4 model multiplier of 1.0', function () {
            $text = str_repeat('a', 40); // 40 chars / 4 = 10 base tokens
            $withGpt4 = TokenCounter::estimate($text, 'gpt-4');

            expect($withGpt4)->toBe(10);
        });

        it('applies gpt-3.5 model multiplier of 1.0', function () {
            $text = str_repeat('a', 40);
            $withGpt35 = TokenCounter::estimate($text, 'gpt-3.5-turbo');

            expect($withGpt35)->toBe(10);
        });

        it('applies llama model multiplier of 1.0', function () {
            $text = str_repeat('a', 40);
            $result = TokenCounter::estimate($text, 'llama-2-70b');

            expect($result)->toBe(10);
        });

        it('applies mistral model multiplier of 1.0', function () {
            $text = str_repeat('a', 40);
            $result = TokenCounter::estimate($text, 'mistral-7b');

            expect($result)->toBe(10);
        });

        it('returns 1.0 multiplier for unknown model', function () {
            $text = str_repeat('a', 40);
            $result = TokenCounter::estimate($text, 'some-unknown-model');

            expect($result)->toBe(10);
        });

        it('returns 1.0 multiplier for null model', function () {
            $text = str_repeat('a', 40);
            $result = TokenCounter::estimate($text, null);

            expect($result)->toBe(10);
        });

        it('handles multibyte characters', function () {
            // mb_strlen counts characters, not bytes
            $text = str_repeat("\u{00e9}", 8); // 8 accented characters / 4 = 2
            expect(TokenCounter::estimate($text))->toBe(2);
        });
    });

    describe('countMessages', function () {
        it('counts tokens for empty messages array', function () {
            // 0 messages + 3 base overhead = 3
            expect(TokenCounter::countMessages([]))->toBe(3);
        });

        it('includes per-message overhead', function () {
            $messages = [
                ['role' => 'user', 'content' => ''],
                ['role' => 'assistant', 'content' => ''],
            ];

            // 0 content tokens + 2*4 overhead + 3 base = 11
            $tokens = TokenCounter::countMessages($messages);
            expect($tokens)->toBe(11);
        });

        it('handles messages without content key', function () {
            $messages = [
                ['role' => 'system'],
            ];

            // empty string content = 0 tokens + 4 overhead + 3 base = 7
            $tokens = TokenCounter::countMessages($messages);
            expect($tokens)->toBe(7);
        });

        it('passes model to count for each message', function () {
            $messages = [
                ['role' => 'user', 'content' => str_repeat('a', 40)],
            ];

            $withoutModel = TokenCounter::countMessages($messages);
            $withClaude = TokenCounter::countMessages($messages, 'claude-3-opus');

            // Without model: 10 + 4 + 3 = 17
            // With claude (1.1x): 11 + 4 + 3 = 18
            expect($withoutModel)->toBe(17)
                ->and($withClaude)->toBe(18);
        });
    });

    describe('format', function () {
        it('formats numbers under 1000 as plain string', function () {
            expect(TokenCounter::format(0))->toBe('0')
                ->and(TokenCounter::format(1))->toBe('1')
                ->and(TokenCounter::format(999))->toBe('999');
        });

        it('formats thousands with K suffix', function () {
            expect(TokenCounter::format(1000))->toBe('1K')
                ->and(TokenCounter::format(1500))->toBe('1.5K')
                ->and(TokenCounter::format(10000))->toBe('10K')
                ->and(TokenCounter::format(999999))->toBe('1000K');
        });

        it('formats millions with M suffix', function () {
            expect(TokenCounter::format(1000000))->toBe('1M')
                ->and(TokenCounter::format(1250000))->toBe('1.25M')
                ->and(TokenCounter::format(10000000))->toBe('10M');
        });
    });

    describe('calculateCost', function () {
        it('calculates cost for zero tokens', function () {
            $pricing = ['input' => 0.01, 'output' => 0.03];
            expect(TokenCounter::calculateCost(0, 0, $pricing))->toBe(0.0);
        });

        it('calculates cost for input only', function () {
            $pricing = ['input' => 0.01, 'output' => 0.03];
            // 1000 / 1000 * 0.01 = 0.01
            expect(TokenCounter::calculateCost(1000, 0, $pricing))->toBe(0.01);
        });

        it('calculates cost for output only', function () {
            $pricing = ['input' => 0.01, 'output' => 0.03];
            // 1000 / 1000 * 0.03 = 0.03
            expect(TokenCounter::calculateCost(0, 1000, $pricing))->toBe(0.03);
        });

        it('calculates combined cost', function () {
            $pricing = ['input' => 0.003, 'output' => 0.015];
            // 2000/1000*0.003 + 1000/1000*0.015 = 0.006 + 0.015 = 0.021
            $cost = TokenCounter::calculateCost(2000, 1000, $pricing);
            expect($cost)->toEqualWithDelta(0.021, 0.0001);
        });
    });
});
