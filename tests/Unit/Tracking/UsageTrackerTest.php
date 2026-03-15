<?php

declare(strict_types=1);

use AgenticOrchestrator\Tracking\CostCalculator;
use AgenticOrchestrator\Tracking\UsageRecord;
use AgenticOrchestrator\Tracking\UsageTracker;
use Illuminate\Support\Facades\DB;

describe('UsageTracker', function () {

    beforeEach(function () {
        $this->costCalculator = Mockery::mock(CostCalculator::class);
        $this->tracker = new UsageTracker($this->costCalculator);
    });

    describe('construction', function () {
        it('creates an instance via constructor', function () {
            expect($this->tracker)->toBeInstanceOf(UsageTracker::class);
        });

        it('creates an instance via make factory without arguments', function () {
            $tracker = UsageTracker::make();
            expect($tracker)->toBeInstanceOf(UsageTracker::class);
        });

        it('creates an instance via make factory with custom cost calculator', function () {
            $tracker = UsageTracker::make($this->costCalculator);
            expect($tracker)->toBeInstanceOf(UsageTracker::class);
        });
    });

    describe('track', function () {
        it('creates a usage record with correct data', function () {
            $this->costCalculator
                ->shouldReceive('calculate')
                ->with('gpt-4o', 100, 50)
                ->once()
                ->andReturn(0.00075);

            $record = $this->tracker->track(
                agentClass: 'App\\Agents\\WriterAgent',
                model: 'gpt-4o',
                inputTokens: 100,
                outputTokens: 50,
                latencyMs: 250.5,
                teamId: 1,
                userId: 42,
                metadata: ['task' => 'summarize'],
            );

            expect($record)->toBeInstanceOf(UsageRecord::class)
                ->and($record->agentClass)->toBe('App\\Agents\\WriterAgent')
                ->and($record->model)->toBe('gpt-4o')
                ->and($record->inputTokens)->toBe(100)
                ->and($record->outputTokens)->toBe(50)
                ->and($record->cost)->toBe(0.00075)
                ->and($record->latencyMs)->toBe(250.5)
                ->and($record->teamId)->toBe(1)
                ->and($record->userId)->toBe(42)
                ->and($record->requestId)->not->toBeEmpty()
                ->and($record->metadata)->toBe(['task' => 'summarize'])
                ->and($record->timestamp)->not->toBeNull();
        });

        it('adds record to buffer', function () {
            $this->costCalculator
                ->shouldReceive('calculate')
                ->andReturn(0.001);

            $this->tracker->track('Agent', 'gpt-4o', 100, 50, 200.0);

            expect($this->tracker->getBuffer())->toHaveCount(1);
        });

        it('accepts optional null teamId and userId', function () {
            $this->costCalculator
                ->shouldReceive('calculate')
                ->andReturn(0.001);

            $record = $this->tracker->track('Agent', 'gpt-4o', 100, 50, 200.0);

            expect($record->teamId)->toBeNull()
                ->and($record->userId)->toBeNull();
        });

        it('auto-flushes when buffer reaches buffer size', function () {
            $this->costCalculator
                ->shouldReceive('calculate')
                ->andReturn(0.001);

            $this->tracker->setBufferSize(3);

            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('insert')
                ->once();

            $this->tracker->track('Agent', 'model', 10, 5, 100.0);
            $this->tracker->track('Agent', 'model', 10, 5, 100.0);
            $this->tracker->track('Agent', 'model', 10, 5, 100.0);

            expect($this->tracker->getBuffer())->toBeEmpty();
        });
    });

    describe('trackEmbedding', function () {
        it('creates an embedding usage record', function () {
            $this->costCalculator
                ->shouldReceive('calculateEmbedding')
                ->with('text-embedding-3-small', 500)
                ->once()
                ->andReturn(0.00001);

            $record = $this->tracker->trackEmbedding(
                model: 'text-embedding-3-small',
                tokens: 500,
                teamId: 1,
                userId: 2,
                metadata: ['source' => 'doc'],
            );

            expect($record)->toBeInstanceOf(UsageRecord::class)
                ->and($record->agentClass)->toBe('embedding')
                ->and($record->model)->toBe('text-embedding-3-small')
                ->and($record->inputTokens)->toBe(500)
                ->and($record->outputTokens)->toBe(0)
                ->and($record->metadata)->toHaveKey('type', 'embedding')
                ->and($record->metadata)->toHaveKey('source', 'doc');
        });

        it('adds embedding record to buffer', function () {
            $this->costCalculator
                ->shouldReceive('calculateEmbedding')
                ->andReturn(0.00001);

            $this->tracker->trackEmbedding('text-embedding-3-small', 500);

            expect($this->tracker->getBuffer())->toHaveCount(1);
        });

        it('auto-flushes embeddings when buffer is full', function () {
            $this->costCalculator
                ->shouldReceive('calculateEmbedding')
                ->andReturn(0.00001);

            $this->tracker->setBufferSize(2);

            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('insert')
                ->once();

            $this->tracker->trackEmbedding('model', 100);
            $this->tracker->trackEmbedding('model', 100);

            expect($this->tracker->getBuffer())->toBeEmpty();
        });
    });

    describe('flush', function () {
        it('returns zero when buffer is empty', function () {
            expect($this->tracker->flush())->toBe(0);
        });

        it('flushes records to database and returns count', function () {
            $this->costCalculator
                ->shouldReceive('calculate')
                ->andReturn(0.001);

            $this->tracker->track('Agent', 'model', 10, 5, 100.0, 1, 2);
            $this->tracker->track('Agent', 'model', 20, 10, 200.0, 1, 2);

            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('insert')
                ->once()
                ->withArgs(function ($data) {
                    return count($data) === 2
                        && $data[0]['agent_class'] === 'Agent'
                        && $data[0]['input_tokens'] === 10
                        && $data[1]['input_tokens'] === 20
                        && is_string($data[0]['metadata']);
                });

            $count = $this->tracker->flush();

            expect($count)->toBe(2)
                ->and($this->tracker->getBuffer())->toBeEmpty();
        });

        it('re-adds records to buffer on failure and rethrows', function () {
            $this->costCalculator
                ->shouldReceive('calculate')
                ->andReturn(0.001);

            $this->tracker->track('Agent', 'model', 10, 5, 100.0);

            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('insert')
                ->once()
                ->andThrow(new RuntimeException('DB connection failed'));

            expect(fn () => $this->tracker->flush())
                ->toThrow(RuntimeException::class, 'DB connection failed');

            expect($this->tracker->getBuffer())->toHaveCount(1);
        });
    });

    describe('setBufferSize', function () {
        it('sets buffer size', function () {
            $result = $this->tracker->setBufferSize(50);
            expect($result)->toBeInstanceOf(UsageTracker::class);
        });

        it('enforces minimum buffer size of 1', function () {
            $this->costCalculator
                ->shouldReceive('calculate')
                ->andReturn(0.001);

            $this->tracker->setBufferSize(0);

            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('insert')
                ->once();

            // Buffer size of 0 becomes 1, so first track should auto-flush
            $this->tracker->track('Agent', 'model', 10, 5, 100.0);

            expect($this->tracker->getBuffer())->toBeEmpty();
        });

        it('returns fluent interface', function () {
            $result = $this->tracker->setBufferSize(10);
            expect($result)->toBe($this->tracker);
        });
    });

    describe('clearBuffer', function () {
        it('clears buffer without flushing', function () {
            $this->costCalculator
                ->shouldReceive('calculate')
                ->andReturn(0.001);

            $this->tracker->track('Agent', 'model', 10, 5, 100.0);
            expect($this->tracker->getBuffer())->toHaveCount(1);

            $result = $this->tracker->clearBuffer();

            expect($this->tracker->getBuffer())->toBeEmpty()
                ->and($result)->toBeInstanceOf(UsageTracker::class);
        });
    });

    describe('getTeamCost', function () {
        it('queries total cost for a team without date filters', function () {
            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->with('team_id', 1)
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('sum')
                ->with('cost')
                ->once()
                ->andReturn(12.50);

            $cost = $this->tracker->getTeamCost(1);

            expect($cost)->toBe(12.50);
        });

        it('applies from date filter', function () {
            $from = new DateTimeImmutable('2026-01-01');

            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->with('team_id', 1)
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->with('created_at', '>=', $from)
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('sum')
                ->with('cost')
                ->once()
                ->andReturn(5.00);

            $cost = $this->tracker->getTeamCost(1, $from);

            expect($cost)->toBe(5.00);
        });

        it('applies both from and to date filters', function () {
            $from = new DateTimeImmutable('2026-01-01');
            $to = new DateTimeImmutable('2026-01-31');

            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->with('team_id', 1)
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->with('created_at', '>=', $from)
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->with('created_at', '<=', $to)
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('sum')
                ->with('cost')
                ->once()
                ->andReturn(3.25);

            $cost = $this->tracker->getTeamCost(1, $from, $to);

            expect($cost)->toBe(3.25);
        });
    });

    describe('getTeamSummary', function () {
        it('returns team summary without date filters', function () {
            $result = (object) [
                'total_requests' => 100,
                'total_input_tokens' => 50000,
                'total_output_tokens' => 25000,
                'total_cost' => 1.2345,
                'avg_latency_ms' => 350.678,
            ];

            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->with('team_id', 5)
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('selectRaw')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('first')
                ->once()
                ->andReturn($result);

            $summary = $this->tracker->getTeamSummary(5);

            expect($summary['team_id'])->toBe(5)
                ->and($summary['total_requests'])->toBe(100)
                ->and($summary['total_input_tokens'])->toBe(50000)
                ->and($summary['total_output_tokens'])->toBe(25000)
                ->and($summary['total_tokens'])->toBe(75000)
                ->and($summary['total_cost'])->toBe(1.2345)
                ->and($summary['avg_latency_ms'])->toBe(350.68)
                ->and($summary['from'])->toBeNull()
                ->and($summary['to'])->toBeNull();
        });

        it('includes date range in summary when provided', function () {
            $from = new DateTimeImmutable('2026-01-01');
            $to = new DateTimeImmutable('2026-01-31');

            $result = (object) [
                'total_requests' => 0,
                'total_input_tokens' => null,
                'total_output_tokens' => null,
                'total_cost' => null,
                'avg_latency_ms' => null,
            ];

            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->with('team_id', 1)
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->with('created_at', '>=', $from)
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->with('created_at', '<=', $to)
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('selectRaw')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('first')
                ->once()
                ->andReturn($result);

            $summary = $this->tracker->getTeamSummary(1, $from, $to);

            expect($summary['from'])->toBe('2026-01-01')
                ->and($summary['to'])->toBe('2026-01-31')
                ->and($summary['total_requests'])->toBe(0)
                ->and($summary['total_input_tokens'])->toBe(0)
                ->and($summary['total_output_tokens'])->toBe(0)
                ->and($summary['total_tokens'])->toBe(0)
                ->and($summary['total_cost'])->toBe(0.0)
                ->and($summary['avg_latency_ms'])->toBe(0.0);
        });
    });

    describe('getUsageByAgent', function () {
        it('returns usage grouped by agent', function () {
            $rows = collect([
                (object) [
                    'agent_class' => 'App\\Agents\\WriterAgent',
                    'request_count' => 50,
                    'input_tokens' => 10000,
                    'output_tokens' => 5000,
                    'cost' => 0.5678,
                    'avg_latency_ms' => 300.123,
                ],
                (object) [
                    'agent_class' => 'App\\Agents\\ReviewerAgent',
                    'request_count' => 20,
                    'input_tokens' => 4000,
                    'output_tokens' => 2000,
                    'cost' => 0.1234,
                    'avg_latency_ms' => 200.456,
                ],
            ]);

            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->with('team_id', 1)
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('selectRaw')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('groupBy')
                ->with('agent_class')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('orderByDesc')
                ->with('cost')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('get')
                ->once()
                ->andReturn($rows);

            $result = $this->tracker->getUsageByAgent(1);

            expect($result)->toHaveCount(2)
                ->and($result[0]['agent'])->toBe('WriterAgent')
                ->and($result[0]['agent_class'])->toBe('App\\Agents\\WriterAgent')
                ->and($result[0]['request_count'])->toBe(50)
                ->and($result[0]['cost'])->toBe(0.5678)
                ->and($result[1]['agent'])->toBe('ReviewerAgent');
        });

        it('applies date filters to agent usage query', function () {
            $from = new DateTimeImmutable('2026-01-01');
            $to = new DateTimeImmutable('2026-01-31');

            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->with('team_id', 1)
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->with('created_at', '>=', $from)
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->with('created_at', '<=', $to)
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('selectRaw')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('groupBy')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('orderByDesc')
                ->once()
                ->andReturnSelf();
            DB::shouldReceive('get')
                ->once()
                ->andReturn(collect([]));

            $result = $this->tracker->getUsageByAgent(1, $from, $to);

            expect($result)->toBeArray()->toBeEmpty();
        });
    });
});
