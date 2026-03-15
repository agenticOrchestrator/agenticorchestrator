<?php

declare(strict_types=1);

use AgenticOrchestrator\Tracking\UsageRecord;

describe('UsageRecord', function () {
    it('stores all required properties', function () {
        $record = new UsageRecord(
            agentClass: 'App\\Agents\\WriterAgent',
            model: 'gpt-4o',
            inputTokens: 500,
            outputTokens: 200,
            cost: 0.0035,
            latencyMs: 1250.5,
        );

        expect($record->agentClass)->toBe('App\\Agents\\WriterAgent')
            ->and($record->model)->toBe('gpt-4o')
            ->and($record->inputTokens)->toBe(500)
            ->and($record->outputTokens)->toBe(200)
            ->and($record->cost)->toBe(0.0035)
            ->and($record->latencyMs)->toBe(1250.5);
    });

    it('defaults optional properties to null or empty', function () {
        $record = new UsageRecord(
            agentClass: 'TestAgent',
            model: 'claude-3',
            inputTokens: 100,
            outputTokens: 50,
            cost: 0.001,
            latencyMs: 500.0,
        );

        expect($record->teamId)->toBeNull()
            ->and($record->userId)->toBeNull()
            ->and($record->requestId)->toBeNull()
            ->and($record->metadata)->toBe([])
            ->and($record->timestamp)->toBeNull();
    });

    it('accepts all optional properties', function () {
        $timestamp = new DateTimeImmutable('2026-01-15 10:30:00');

        $record = new UsageRecord(
            agentClass: 'TestAgent',
            model: 'gpt-4o',
            inputTokens: 1000,
            outputTokens: 500,
            cost: 0.015,
            latencyMs: 2000.0,
            teamId: 42,
            userId: 7,
            requestId: 'req-abc-123',
            metadata: ['source' => 'api', 'version' => '2.0'],
            timestamp: $timestamp,
        );

        expect($record->teamId)->toBe(42)
            ->and($record->userId)->toBe(7)
            ->and($record->requestId)->toBe('req-abc-123')
            ->and($record->metadata)->toBe(['source' => 'api', 'version' => '2.0'])
            ->and($record->timestamp)->toBe($timestamp);
    });

    it('calculates total tokens', function () {
        $record = new UsageRecord(
            agentClass: 'TestAgent',
            model: 'gpt-4o',
            inputTokens: 300,
            outputTokens: 150,
            cost: 0.002,
            latencyMs: 800.0,
        );

        expect($record->totalTokens())->toBe(450);
    });

    it('converts to array with correct keys', function () {
        $timestamp = new DateTimeImmutable('2026-03-10 14:00:00');

        $record = new UsageRecord(
            agentClass: 'App\\Agents\\AnalyzerAgent',
            model: 'claude-3-5-sonnet',
            inputTokens: 800,
            outputTokens: 400,
            cost: 0.012,
            latencyMs: 1500.0,
            teamId: 5,
            userId: 3,
            requestId: 'req-xyz',
            metadata: ['tag' => 'analysis'],
            timestamp: $timestamp,
        );

        $array = $record->toArray();

        expect($array)->toBe([
            'agent_class' => 'App\\Agents\\AnalyzerAgent',
            'model' => 'claude-3-5-sonnet',
            'input_tokens' => 800,
            'output_tokens' => 400,
            'total_tokens' => 1200,
            'cost' => 0.012,
            'latency_ms' => 1500.0,
            'team_id' => 5,
            'user_id' => 3,
            'request_id' => 'req-xyz',
            'metadata' => ['tag' => 'analysis'],
            'timestamp' => '2026-03-10 14:00:00',
        ]);
    });

    it('formats null timestamp as null in array', function () {
        $record = new UsageRecord(
            agentClass: 'TestAgent',
            model: 'gpt-4o',
            inputTokens: 100,
            outputTokens: 50,
            cost: 0.001,
            latencyMs: 300.0,
        );

        expect($record->toArray()['timestamp'])->toBeNull();
    });

    it('implements JsonSerializable', function () {
        $record = new UsageRecord(
            agentClass: 'TestAgent',
            model: 'gpt-4o',
            inputTokens: 100,
            outputTokens: 50,
            cost: 0.001,
            latencyMs: 300.0,
        );

        expect($record)->toBeInstanceOf(JsonSerializable::class)
            ->and($record->jsonSerialize())->toBe($record->toArray());
    });

    it('produces valid json output', function () {
        $record = new UsageRecord(
            agentClass: 'TestAgent',
            model: 'gpt-4o',
            inputTokens: 100,
            outputTokens: 50,
            cost: 0.001,
            latencyMs: 300.0,
        );

        $json = json_encode($record, JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        expect($decoded['agent_class'])->toBe('TestAgent')
            ->and($decoded['total_tokens'])->toBe(150);
    });
});
