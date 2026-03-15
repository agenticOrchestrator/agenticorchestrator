<?php

declare(strict_types=1);

use AgenticOrchestrator\Tools\ToolResult;

describe('constructor', function () {
    it('creates a tool result with all properties', function () {
        $result = new ToolResult(
            toolCallId: 'call_123',
            name: 'search',
            arguments: ['query' => 'test'],
            result: 'found it',
            success: true,
            error: null,
            duration: 150.5,
            cached: true,
        );

        expect($result->toolCallId)->toBe('call_123');
        expect($result->name)->toBe('search');
        expect($result->arguments)->toBe(['query' => 'test']);
        expect($result->result)->toBe('found it');
        expect($result->success)->toBeTrue();
        expect($result->error)->toBeNull();
        expect($result->duration)->toBe(150.5);
        expect($result->cached)->toBeTrue();
    });

    it('uses default values for optional parameters', function () {
        $result = new ToolResult(
            toolCallId: 'call_123',
            name: 'search',
            arguments: [],
            result: null,
        );

        expect($result->success)->toBeTrue();
        expect($result->error)->toBeNull();
        expect($result->duration)->toBeNull();
        expect($result->cached)->toBeFalse();
    });
});

describe('success factory', function () {
    it('creates a successful result', function () {
        $result = ToolResult::success(
            toolCallId: 'call_456',
            name: 'weather',
            arguments: ['city' => 'London'],
            result: ['temp' => 20],
        );

        expect($result->toolCallId)->toBe('call_456');
        expect($result->name)->toBe('weather');
        expect($result->arguments)->toBe(['city' => 'London']);
        expect($result->result)->toBe(['temp' => 20]);
        expect($result->success)->toBeTrue();
        expect($result->error)->toBeNull();
        expect($result->duration)->toBeNull();
        expect($result->cached)->toBeFalse();
    });

    it('creates a successful result with duration', function () {
        $result = ToolResult::success(
            toolCallId: 'call_456',
            name: 'weather',
            arguments: [],
            result: 'data',
            duration: 250.0,
        );

        expect($result->duration)->toBe(250.0);
    });

    it('creates a successful cached result', function () {
        $result = ToolResult::success(
            toolCallId: 'call_456',
            name: 'weather',
            arguments: [],
            result: 'cached_data',
            duration: 1.0,
            cached: true,
        );

        expect($result->cached)->toBeTrue();
    });
});

describe('failure factory', function () {
    it('creates a failed result', function () {
        $result = ToolResult::failure(
            toolCallId: 'call_789',
            name: 'database',
            arguments: ['sql' => 'SELECT 1'],
            error: 'Connection refused',
        );

        expect($result->toolCallId)->toBe('call_789');
        expect($result->name)->toBe('database');
        expect($result->arguments)->toBe(['sql' => 'SELECT 1']);
        expect($result->result)->toBeNull();
        expect($result->success)->toBeFalse();
        expect($result->error)->toBe('Connection refused');
        expect($result->cached)->toBeFalse();
    });

    it('creates a failed result with duration', function () {
        $result = ToolResult::failure(
            toolCallId: 'call_789',
            name: 'database',
            arguments: [],
            error: 'Timeout',
            duration: 5000.0,
        );

        expect($result->duration)->toBe(5000.0);
    });
});

describe('isSuccess', function () {
    it('returns true for successful result', function () {
        $result = ToolResult::success('id', 'name', [], 'data');

        expect($result->isSuccess())->toBeTrue();
    });

    it('returns false for failed result', function () {
        $result = ToolResult::failure('id', 'name', [], 'error');

        expect($result->isSuccess())->toBeFalse();
    });
});

describe('isFailure', function () {
    it('returns true for failed result', function () {
        $result = ToolResult::failure('id', 'name', [], 'error');

        expect($result->isFailure())->toBeTrue();
    });

    it('returns false for successful result', function () {
        $result = ToolResult::success('id', 'name', [], 'data');

        expect($result->isFailure())->toBeFalse();
    });
});

describe('getContentForLlm', function () {
    it('returns error message for failed result', function () {
        $result = ToolResult::failure('id', 'name', [], 'Connection refused');

        expect($result->getContentForLlm())->toBe('Error: Connection refused');
    });

    it('returns string result directly', function () {
        $result = ToolResult::success('id', 'name', [], 'The weather is sunny');

        expect($result->getContentForLlm())->toBe('The weather is sunny');
    });

    it('returns JSON-encoded array result', function () {
        $data = ['temperature' => 22, 'condition' => 'sunny'];
        $result = ToolResult::success('id', 'name', [], $data);

        $content = $result->getContentForLlm();
        $decoded = json_decode($content, true);

        expect($decoded)->toBe($data);
    });

    it('returns JSON-encoded integer result', function () {
        $result = ToolResult::success('id', 'name', [], 42);

        expect($result->getContentForLlm())->toBe('42');
    });

    it('returns JSON-encoded null result for successful result with null', function () {
        $result = new ToolResult(
            toolCallId: 'id',
            name: 'name',
            arguments: [],
            result: null,
            success: true,
        );

        expect($result->getContentForLlm())->toBe('null');
    });

    it('returns JSON-encoded boolean result', function () {
        $result = ToolResult::success('id', 'name', [], true);

        expect($result->getContentForLlm())->toBe('true');
    });
});

describe('toArray', function () {
    it('converts successful result to array', function () {
        $result = ToolResult::success(
            toolCallId: 'call_123',
            name: 'search',
            arguments: ['q' => 'test'],
            result: 'found',
            duration: 100.0,
            cached: true,
        );

        $array = $result->toArray();

        expect($array)->toBe([
            'tool_call_id' => 'call_123',
            'name' => 'search',
            'arguments' => ['q' => 'test'],
            'result' => 'found',
            'success' => true,
            'error' => null,
            'duration' => 100.0,
            'cached' => true,
        ]);
    });

    it('converts failed result to array', function () {
        $result = ToolResult::failure(
            toolCallId: 'call_456',
            name: 'api',
            arguments: ['url' => 'http://example.com'],
            error: 'Timeout',
            duration: 5000.0,
        );

        $array = $result->toArray();

        expect($array['success'])->toBeFalse();
        expect($array['error'])->toBe('Timeout');
        expect($array['result'])->toBeNull();
        expect($array['cached'])->toBeFalse();
    });
});

describe('jsonSerialize', function () {
    it('returns same as toArray', function () {
        $result = ToolResult::success('id', 'name', ['a' => 1], 'data', 50.0, true);

        expect($result->jsonSerialize())->toBe($result->toArray());
    });

    it('is JSON serializable', function () {
        $result = ToolResult::success('id', 'name', [], 'data');

        $json = json_encode($result);
        $decoded = json_decode($json, true);

        expect($decoded['tool_call_id'])->toBe('id');
        expect($decoded['name'])->toBe('name');
        expect($decoded['success'])->toBeTrue();
    });
});
