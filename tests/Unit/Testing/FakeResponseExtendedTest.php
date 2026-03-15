<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\AgentResponse;
use AgenticOrchestrator\Testing\FakeResponse;

describe('FakeResponse Extended', function () {
    describe('make factory', function () {
        it('creates a new builder instance', function () {
            $builder = FakeResponse::make();

            expect($builder)->toBeInstanceOf(FakeResponse::class);
        });
    });

    describe('builder fluent API', function () {
        it('sets content fluently', function () {
            $response = FakeResponse::make()
                ->content('test content')
                ->build();

            expect($response->content)->toBe('test content');
        });

        it('sets latency fluently', function () {
            $response = FakeResponse::make()
                ->content('test')
                ->latency(150.5)
                ->build();

            expect($response->latency)->toBe(150.5);
        });

        it('sets metadata fluently', function () {
            $response = FakeResponse::make()
                ->content('test')
                ->metadata(['model' => 'gpt-4', 'temperature' => 0.7])
                ->build();

            expect($response->metadata)->toBe(['model' => 'gpt-4', 'temperature' => 0.7]);
        });

        it('sets finish reason fluently', function () {
            $response = FakeResponse::make()
                ->content('test')
                ->finishReason('content_filter')
                ->build();

            expect($response->finishReason)->toBe('content_filter');
        });

        it('sets finish reason to null', function () {
            $response = FakeResponse::make()
                ->content('test')
                ->finishReason(null)
                ->build();

            expect($response->finishReason)->toBeNull();
        });

        it('chains all builder methods together', function () {
            $response = FakeResponse::make()
                ->content('full chain')
                ->tokens(100, 200)
                ->finishReason('stop')
                ->latency(250.0)
                ->metadata(['key' => 'value'])
                ->withToolCall('tc-1', 'search', ['query' => 'test'], 'result')
                ->build();

            expect($response->content)->toBe('full chain')
                ->and($response->getPromptTokens())->toBe(100)
                ->and($response->getCompletionTokens())->toBe(200)
                ->and($response->getTotalTokens())->toBe(300)
                ->and($response->finishReason)->toBe('stop')
                ->and($response->latency)->toBe(250.0)
                ->and($response->metadata)->toBe(['key' => 'value'])
                ->and($response->hasToolCalls())->toBeTrue();
        });
    });

    describe('build defaults', function () {
        it('builds with default values when nothing is set', function () {
            $response = FakeResponse::make()->build();

            expect($response)->toBeInstanceOf(AgentResponse::class)
                ->and($response->content)->toBe('')
                ->and($response->getPromptTokens())->toBe(10)
                ->and($response->getCompletionTokens())->toBe(20)
                ->and($response->getTotalTokens())->toBe(30)
                ->and($response->finishReason)->toBe('stop')
                ->and($response->hasToolCalls())->toBeFalse()
                ->and($response->metadata)->toBe([])
                ->and($response->latency)->toBeNull();
        });
    });

    describe('withToolCall', function () {
        it('adds a single tool call', function () {
            $response = FakeResponse::make()
                ->withToolCall('call-1', 'my_tool', ['arg1' => 'val1'], 'result1')
                ->build();

            $toolCalls = $response->getToolCalls();
            expect($toolCalls)->toHaveCount(1)
                ->and($toolCalls[0]['id'])->toBe('call-1')
                ->and($toolCalls[0]['name'])->toBe('my_tool')
                ->and($toolCalls[0]['arguments'])->toBe(['arg1' => 'val1'])
                ->and($toolCalls[0]['result'])->toBe('result1');
        });

        it('adds multiple tool calls', function () {
            $response = FakeResponse::make()
                ->withToolCall('call-1', 'tool_a', ['x' => 1])
                ->withToolCall('call-2', 'tool_b', ['y' => 2])
                ->withToolCall('call-3', 'tool_c')
                ->build();

            expect($response->getToolCalls())->toHaveCount(3);
        });

        it('adds tool call with empty arguments', function () {
            $response = FakeResponse::make()
                ->withToolCall('call-1', 'no_args')
                ->build();

            $toolCalls = $response->getToolCalls();
            expect($toolCalls[0]['arguments'])->toBe([])
                ->and($toolCalls[0]['result'])->toBeNull();
        });
    });

    describe('static text helper', function () {
        it('creates a simple response with default tokens', function () {
            $response = FakeResponse::text('Simple message');

            expect($response)->toBeInstanceOf(AgentResponse::class)
                ->and($response->content)->toBe('Simple message')
                ->and($response->hasToolCalls())->toBeFalse()
                ->and($response->finishReason)->toBe('stop');
        });

        it('creates response with empty content', function () {
            $response = FakeResponse::text('');

            expect($response->content)->toBe('');
        });
    });

    describe('static withTools helper', function () {
        it('creates a response with tool calls and tool_calls finish reason', function () {
            $response = FakeResponse::withTools('Processing', [
                ['id' => 'tc-1', 'name' => 'search', 'arguments' => ['q' => 'test']],
                ['id' => 'tc-2', 'name' => 'fetch'],
            ]);

            expect($response->content)->toBe('Processing')
                ->and($response->finishReason)->toBe('tool_calls')
                ->and($response->getToolCalls())->toHaveCount(2);
        });

        it('handles tool calls with result values', function () {
            $response = FakeResponse::withTools('Done', [
                ['id' => 'tc-1', 'name' => 'tool', 'arguments' => ['a' => 1], 'result' => 'found'],
            ]);

            $calls = $response->getToolCalls();
            expect($calls[0]['result'])->toBe('found');
        });

        it('handles tool calls with missing optional fields', function () {
            $response = FakeResponse::withTools('Result', [
                ['id' => 'tc-1', 'name' => 'minimal'],
            ]);

            $calls = $response->getToolCalls();
            expect($calls[0]['arguments'])->toBe([])
                ->and($calls[0]['result'])->toBeNull();
        });
    });

    describe('static error helper', function () {
        it('creates an error response with default message', function () {
            $response = FakeResponse::error();

            expect($response->content)->toBe('An error occurred')
                ->and($response->finishReason)->toBe('error');
        });

        it('creates an error response with custom message', function () {
            $response = FakeResponse::error('Rate limit exceeded');

            expect($response->content)->toBe('Rate limit exceeded')
                ->and($response->finishReason)->toBe('error');
        });
    });

    describe('static truncated helper', function () {
        it('creates a truncated response with length finish reason', function () {
            $response = FakeResponse::truncated('Partial output...');

            expect($response->content)->toBe('Partial output...')
                ->and($response->finishReason)->toBe('length')
                ->and($response->wasTruncated())->toBeTrue();
        });
    });
});
