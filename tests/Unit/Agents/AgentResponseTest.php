<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\AgentResponse;

test('creates response with content', function () {
    $response = new AgentResponse(
        content: 'Hello, world!',
    );

    expect($response->content)->toBe('Hello, world!');
    expect((string) $response)->toBe('Hello, world!');
});

test('tracks tool calls', function () {
    $response = new AgentResponse(
        content: 'Result',
        toolCalls: [
            [
                'id' => 'call_123',
                'name' => 'lookup_order',
                'arguments' => ['order_id' => '12345'],
                'result' => ['status' => 'shipped'],
            ],
        ],
    );

    expect($response->hasToolCalls())->toBeTrue();
    expect($response->getToolCalls())->toHaveCount(1);
    expect($response->getToolCalls()[0]['name'])->toBe('lookup_order');
});

test('tracks token usage', function () {
    $response = new AgentResponse(
        content: 'Hello',
        usage: [
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
        ],
    );

    expect($response->getTotalTokens())->toBe(150);
    expect($response->getPromptTokens())->toBe(100);
    expect($response->getCompletionTokens())->toBe(50);
});

test('detects successful completion', function () {
    $response = new AgentResponse(
        content: 'Done',
        finishReason: 'stop',
    );

    expect($response->isSuccessful())->toBeTrue();
    expect($response->wasTruncated())->toBeFalse();
});

test('detects truncated response', function () {
    $response = new AgentResponse(
        content: 'Partial...',
        finishReason: 'length',
    );

    expect($response->isSuccessful())->toBeFalse();
    expect($response->wasTruncated())->toBeTrue();
});

test('converts to array', function () {
    $response = new AgentResponse(
        content: 'Test',
        toolCalls: [],
        usage: ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        metadata: ['key' => 'value'],
        latency: 123.45,
        finishReason: 'stop',
    );

    $array = $response->toArray();

    expect($array)->toHaveKeys(['content', 'tool_calls', 'usage', 'metadata', 'latency', 'finish_reason']);
    expect($array['content'])->toBe('Test');
    expect($array['latency'])->toBe(123.45);
});

test('creates from provider response', function () {
    $response = AgentResponse::fromProviderResponse([
        'content' => 'Generated content',
        'usage' => [
            'prompt_tokens' => 50,
            'completion_tokens' => 25,
            'total_tokens' => 75,
        ],
        'finish_reason' => 'stop',
    ]);

    expect($response->content)->toBe('Generated content');
    expect($response->getTotalTokens())->toBe(75);
});

test('creates empty response', function () {
    $response = AgentResponse::empty('Error occurred');

    expect($response->content)->toBe('Error occurred');
    expect($response->finishReason)->toBe('error');
});
