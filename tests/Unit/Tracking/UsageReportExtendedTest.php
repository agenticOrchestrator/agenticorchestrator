<?php

declare(strict_types=1);

use AgenticOrchestrator\Tracking\UsageReport;
use Illuminate\Support\Facades\DB;

describe('UsageReport - Extended Coverage', function () {

    describe('generate', function () {
        it('generates a full report with all sections', function () {
            $summaryResult = (object) [
                'total_requests' => 10,
                'total_input_tokens' => 5000,
                'total_output_tokens' => 2500,
                'total_cost' => 1.5,
                'avg_latency_ms' => 300.0,
                'min_latency_ms' => 100.0,
                'max_latency_ms' => 500.0,
                'unique_agents' => 2,
                'unique_users' => 3,
            ];

            $byAgentRows = collect([
                (object) [
                    'agent_class' => 'App\\Agents\\Writer',
                    'request_count' => 5,
                    'input_tokens' => 2500,
                    'output_tokens' => 1250,
                    'cost' => 0.75,
                    'avg_latency_ms' => 280.0,
                ],
            ]);

            $byModelRows = collect([
                (object) [
                    'model' => 'gpt-4o',
                    'request_count' => 10,
                    'input_tokens' => 5000,
                    'output_tokens' => 2500,
                    'cost' => 1.5,
                ],
            ]);

            $timelineRows = collect([
                (object) [
                    'period' => '2026-01-15',
                    'request_count' => 5,
                    'input_tokens' => 2500,
                    'output_tokens' => 1250,
                    'cost' => 0.75,
                ],
            ]);

            $topUsersRows = collect([
                (object) [
                    'user_id' => 42,
                    'request_count' => 8,
                    'cost' => 1.2,
                ],
            ]);

            // The generate method calls baseQuery multiple times (once for each section).
            // Each call chains several methods. We mock the DB facade to handle all of them.
            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->andReturnSelf();
            DB::shouldReceive('selectRaw')
                ->andReturnSelf();
            DB::shouldReceive('first')
                ->andReturn($summaryResult);
            DB::shouldReceive('groupBy')
                ->andReturnSelf();
            DB::shouldReceive('orderBy')
                ->andReturnSelf();
            DB::shouldReceive('orderByDesc')
                ->andReturnSelf();
            DB::shouldReceive('whereNotNull')
                ->andReturnSelf();
            DB::shouldReceive('limit')
                ->andReturnSelf();
            DB::shouldReceive('get')
                ->andReturn($byAgentRows, $byModelRows, $timelineRows, $topUsersRows);

            $report = UsageReport::forTeam(1)
                ->from(new DateTimeImmutable('2026-01-01'))
                ->to(new DateTimeImmutable('2026-01-31'))
                ->daily()
                ->generate();

            expect($report)->toBeInstanceOf(UsageReport::class)
                ->and($report->totalCost())->toBe(1.5)
                ->and($report->totalTokens())->toBe(7500)
                ->and($report->totalRequests())->toBe(10);

            $summary = $report->summary();
            expect($summary['total_requests'])->toBe(10)
                ->and($summary['total_input_tokens'])->toBe(5000)
                ->and($summary['total_output_tokens'])->toBe(2500)
                ->and($summary['total_tokens'])->toBe(7500)
                ->and($summary['avg_latency_ms'])->toBe(300.0)
                ->and($summary['min_latency_ms'])->toBe(100.0)
                ->and($summary['max_latency_ms'])->toBe(500.0)
                ->and($summary['unique_agents'])->toBe(2)
                ->and($summary['unique_users'])->toBe(3);

            $byAgent = $report->byAgent();
            expect($byAgent)->toHaveCount(1)
                ->and($byAgent[0]['agent'])->toBe('Writer')
                ->and($byAgent[0]['agent_class'])->toBe('App\\Agents\\Writer')
                ->and($byAgent[0]['request_count'])->toBe(5);

            $byModel = $report->byModel();
            expect($byModel)->toHaveCount(1)
                ->and($byModel[0]['model'])->toBe('gpt-4o');

            $timeline = $report->timeline();
            expect($timeline)->toHaveCount(1)
                ->and($timeline[0]['period'])->toBe('2026-01-15');
        });
    });

    describe('toArray', function () {
        it('auto-generates report when toArray is called without generate', function () {
            $summaryResult = (object) [
                'total_requests' => 0,
                'total_input_tokens' => null,
                'total_output_tokens' => null,
                'total_cost' => null,
                'avg_latency_ms' => null,
                'min_latency_ms' => null,
                'max_latency_ms' => null,
                'unique_agents' => 0,
                'unique_users' => 0,
            ];

            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->andReturnSelf();
            DB::shouldReceive('selectRaw')
                ->andReturnSelf();
            DB::shouldReceive('first')
                ->andReturn($summaryResult);
            DB::shouldReceive('groupBy')
                ->andReturnSelf();
            DB::shouldReceive('orderBy')
                ->andReturnSelf();
            DB::shouldReceive('orderByDesc')
                ->andReturnSelf();
            DB::shouldReceive('whereNotNull')
                ->andReturnSelf();
            DB::shouldReceive('limit')
                ->andReturnSelf();
            DB::shouldReceive('get')
                ->andReturn(collect([]), collect([]), collect([]), collect([]));

            $report = UsageReport::make();
            $data = $report->toArray();

            expect($data)->toBeArray()
                ->and($data)->toHaveKey('summary')
                ->and($data)->toHaveKey('by_agent')
                ->and($data)->toHaveKey('by_model')
                ->and($data)->toHaveKey('timeline')
                ->and($data)->toHaveKey('top_users')
                ->and($data)->toHaveKey('metadata');
        });

        it('does not regenerate if already generated', function () {
            $summaryResult = (object) [
                'total_requests' => 5,
                'total_input_tokens' => 1000,
                'total_output_tokens' => 500,
                'total_cost' => 0.5,
                'avg_latency_ms' => 200.0,
                'min_latency_ms' => 100.0,
                'max_latency_ms' => 300.0,
                'unique_agents' => 1,
                'unique_users' => 1,
            ];

            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->andReturnSelf();
            DB::shouldReceive('selectRaw')
                ->andReturnSelf();
            DB::shouldReceive('first')
                ->andReturn($summaryResult);
            DB::shouldReceive('groupBy')
                ->andReturnSelf();
            DB::shouldReceive('orderBy')
                ->andReturnSelf();
            DB::shouldReceive('orderByDesc')
                ->andReturnSelf();
            DB::shouldReceive('whereNotNull')
                ->andReturnSelf();
            DB::shouldReceive('limit')
                ->andReturnSelf();
            DB::shouldReceive('get')
                ->andReturn(collect([]), collect([]), collect([]), collect([]));

            $report = UsageReport::make()->generate();
            $data1 = $report->toArray();
            $data2 = $report->toArray();

            expect($data1)->toBe($data2);
        });
    });

    describe('jsonSerialize', function () {
        it('returns the same as toArray', function () {
            $summaryResult = (object) [
                'total_requests' => 0,
                'total_input_tokens' => null,
                'total_output_tokens' => null,
                'total_cost' => null,
                'avg_latency_ms' => null,
                'min_latency_ms' => null,
                'max_latency_ms' => null,
                'unique_agents' => 0,
                'unique_users' => 0,
            ];

            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->andReturnSelf();
            DB::shouldReceive('selectRaw')
                ->andReturnSelf();
            DB::shouldReceive('first')
                ->andReturn($summaryResult);
            DB::shouldReceive('groupBy')
                ->andReturnSelf();
            DB::shouldReceive('orderBy')
                ->andReturnSelf();
            DB::shouldReceive('orderByDesc')
                ->andReturnSelf();
            DB::shouldReceive('whereNotNull')
                ->andReturnSelf();
            DB::shouldReceive('limit')
                ->andReturnSelf();
            DB::shouldReceive('get')
                ->andReturn(collect([]), collect([]), collect([]), collect([]));

            $report = UsageReport::make();
            expect($report->jsonSerialize())->toBe($report->toArray());
        });
    });

    describe('metadata output', function () {
        it('includes correct metadata in generated report', function () {
            $summaryResult = (object) [
                'total_requests' => 0,
                'total_input_tokens' => null,
                'total_output_tokens' => null,
                'total_cost' => null,
                'avg_latency_ms' => null,
                'min_latency_ms' => null,
                'max_latency_ms' => null,
                'unique_agents' => 0,
                'unique_users' => 0,
            ];

            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->andReturnSelf();
            DB::shouldReceive('selectRaw')
                ->andReturnSelf();
            DB::shouldReceive('first')
                ->andReturn($summaryResult);
            DB::shouldReceive('groupBy')
                ->andReturnSelf();
            DB::shouldReceive('orderBy')
                ->andReturnSelf();
            DB::shouldReceive('orderByDesc')
                ->andReturnSelf();
            DB::shouldReceive('whereNotNull')
                ->andReturnSelf();
            DB::shouldReceive('limit')
                ->andReturnSelf();
            DB::shouldReceive('get')
                ->andReturn(collect([]), collect([]), collect([]), collect([]));

            $report = UsageReport::forTeam(42)
                ->from(new DateTimeImmutable('2026-01-01'))
                ->to(new DateTimeImmutable('2026-06-30'))
                ->monthly()
                ->generate();

            $data = $report->toArray();
            $meta = $data['metadata'];

            expect($meta['team_id'])->toBe(42)
                ->and($meta['agent_class'])->toBeNull()
                ->and($meta['from'])->toBe('2026-01-01')
                ->and($meta['to'])->toBe('2026-06-30')
                ->and($meta['group_by'])->toBe('month')
                ->and($meta['generated_at'])->not->toBeNull();
        });
    });

    describe('forAgent filter', function () {
        it('generates report filtered by agent class', function () {
            $summaryResult = (object) [
                'total_requests' => 3,
                'total_input_tokens' => 600,
                'total_output_tokens' => 300,
                'total_cost' => 0.1,
                'avg_latency_ms' => 150.0,
                'min_latency_ms' => 100.0,
                'max_latency_ms' => 200.0,
                'unique_agents' => 1,
                'unique_users' => 1,
            ];

            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->andReturnSelf();
            DB::shouldReceive('selectRaw')
                ->andReturnSelf();
            DB::shouldReceive('first')
                ->andReturn($summaryResult);
            DB::shouldReceive('groupBy')
                ->andReturnSelf();
            DB::shouldReceive('orderBy')
                ->andReturnSelf();
            DB::shouldReceive('orderByDesc')
                ->andReturnSelf();
            DB::shouldReceive('whereNotNull')
                ->andReturnSelf();
            DB::shouldReceive('limit')
                ->andReturnSelf();
            DB::shouldReceive('get')
                ->andReturn(collect([]), collect([]), collect([]), collect([]));

            $report = UsageReport::forAgent('App\\Agents\\WriterAgent')->generate();

            expect($report->totalRequests())->toBe(3);

            $data = $report->toArray();
            expect($data['metadata']['agent_class'])->toBe('App\\Agents\\WriterAgent');
        });
    });

    describe('groupBy timeline variations', function () {
        it('supports weekly grouping in generate', function () {
            $summaryResult = (object) [
                'total_requests' => 0,
                'total_input_tokens' => null,
                'total_output_tokens' => null,
                'total_cost' => null,
                'avg_latency_ms' => null,
                'min_latency_ms' => null,
                'max_latency_ms' => null,
                'unique_agents' => 0,
                'unique_users' => 0,
            ];

            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->andReturnSelf();
            DB::shouldReceive('selectRaw')
                ->andReturnSelf();
            DB::shouldReceive('first')
                ->andReturn($summaryResult);
            DB::shouldReceive('groupBy')
                ->andReturnSelf();
            DB::shouldReceive('orderBy')
                ->andReturnSelf();
            DB::shouldReceive('orderByDesc')
                ->andReturnSelf();
            DB::shouldReceive('whereNotNull')
                ->andReturnSelf();
            DB::shouldReceive('limit')
                ->andReturnSelf();
            DB::shouldReceive('get')
                ->andReturn(collect([]), collect([]), collect([]), collect([]));

            $report = UsageReport::make()->weekly()->generate();
            $data = $report->toArray();

            expect($data['metadata']['group_by'])->toBe('week');
        });

        it('supports hour grouping in generate', function () {
            $summaryResult = (object) [
                'total_requests' => 0,
                'total_input_tokens' => null,
                'total_output_tokens' => null,
                'total_cost' => null,
                'avg_latency_ms' => null,
                'min_latency_ms' => null,
                'max_latency_ms' => null,
                'unique_agents' => 0,
                'unique_users' => 0,
            ];

            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->andReturnSelf();
            DB::shouldReceive('selectRaw')
                ->andReturnSelf();
            DB::shouldReceive('first')
                ->andReturn($summaryResult);
            DB::shouldReceive('groupBy')
                ->andReturnSelf();
            DB::shouldReceive('orderBy')
                ->andReturnSelf();
            DB::shouldReceive('orderByDesc')
                ->andReturnSelf();
            DB::shouldReceive('whereNotNull')
                ->andReturnSelf();
            DB::shouldReceive('limit')
                ->andReturnSelf();
            DB::shouldReceive('get')
                ->andReturn(collect([]), collect([]), collect([]), collect([]));

            $report = UsageReport::make()->groupBy('hour')->generate();
            $data = $report->toArray();

            expect($data['metadata']['group_by'])->toBe('hour');
        });
    });

    describe('summary with null result fields', function () {
        it('handles null fields in summary by defaulting to zero', function () {
            $summaryResult = (object) [
                'total_requests' => null,
                'total_input_tokens' => null,
                'total_output_tokens' => null,
                'total_cost' => null,
                'avg_latency_ms' => null,
                'min_latency_ms' => null,
                'max_latency_ms' => null,
                'unique_agents' => null,
                'unique_users' => null,
            ];

            DB::shouldReceive('table')
                ->with('agent_usage_logs')
                ->andReturnSelf();
            DB::shouldReceive('where')
                ->andReturnSelf();
            DB::shouldReceive('selectRaw')
                ->andReturnSelf();
            DB::shouldReceive('first')
                ->andReturn($summaryResult);
            DB::shouldReceive('groupBy')
                ->andReturnSelf();
            DB::shouldReceive('orderBy')
                ->andReturnSelf();
            DB::shouldReceive('orderByDesc')
                ->andReturnSelf();
            DB::shouldReceive('whereNotNull')
                ->andReturnSelf();
            DB::shouldReceive('limit')
                ->andReturnSelf();
            DB::shouldReceive('get')
                ->andReturn(collect([]), collect([]), collect([]), collect([]));

            $report = UsageReport::make()->generate();

            expect($report->totalCost())->toBe(0.0)
                ->and($report->totalTokens())->toBe(0)
                ->and($report->totalRequests())->toBe(0);

            $summary = $report->summary();
            expect($summary['total_requests'])->toBe(0)
                ->and($summary['total_input_tokens'])->toBe(0)
                ->and($summary['total_output_tokens'])->toBe(0)
                ->and($summary['total_tokens'])->toBe(0)
                ->and($summary['total_cost'])->toBe(0.0)
                ->and($summary['avg_latency_ms'])->toBe(0.0)
                ->and($summary['min_latency_ms'])->toBe(0.0)
                ->and($summary['max_latency_ms'])->toBe(0.0)
                ->and($summary['unique_agents'])->toBe(0)
                ->and($summary['unique_users'])->toBe(0);
        });
    });
});
