<?php

declare(strict_types=1);

use AgenticOrchestrator\Evaluation\Models\AgentEvaluation;
use Illuminate\Database\Eloquent\Model;

describe('AgentEvaluation', function () {
    describe('constants', function () {
        it('defines status constants', function () {
            expect(AgentEvaluation::STATUS_PENDING)->toBe('pending')
                ->and(AgentEvaluation::STATUS_RUNNING)->toBe('running')
                ->and(AgentEvaluation::STATUS_COMPLETED)->toBe('completed')
                ->and(AgentEvaluation::STATUS_FAILED)->toBe('failed');
        });
    });

    describe('table name', function () {
        it('uses agent_evaluations table', function () {
            $model = new AgentEvaluation;

            expect($model->getTable())->toBe('agent_evaluations');
        });
    });

    describe('fillable attributes', function () {
        it('has all expected fillable attributes', function () {
            $model = new AgentEvaluation;
            $fillable = $model->getFillable();

            expect($fillable)->toContain('evaluation_id')
                ->and($fillable)->toContain('suite_class')
                ->and($fillable)->toContain('agent_class')
                ->and($fillable)->toContain('team_id')
                ->and($fillable)->toContain('status')
                ->and($fillable)->toContain('total_cases')
                ->and($fillable)->toContain('passed_cases')
                ->and($fillable)->toContain('failed_cases')
                ->and($fillable)->toContain('error_cases')
                ->and($fillable)->toContain('pass_rate')
                ->and($fillable)->toContain('average_score')
                ->and($fillable)->toContain('metric_scores')
                ->and($fillable)->toContain('results')
                ->and($fillable)->toContain('duration_ms')
                ->and($fillable)->toContain('metadata')
                ->and($fillable)->toContain('started_at')
                ->and($fillable)->toContain('completed_at');
        });
    });

    describe('casts', function () {
        it('casts attributes to correct types', function () {
            $model = new AgentEvaluation;
            $casts = $model->getCasts();

            expect($casts['team_id'])->toBe('integer')
                ->and($casts['total_cases'])->toBe('integer')
                ->and($casts['passed_cases'])->toBe('integer')
                ->and($casts['failed_cases'])->toBe('integer')
                ->and($casts['error_cases'])->toBe('integer')
                ->and($casts['pass_rate'])->toBe('float')
                ->and($casts['average_score'])->toBe('float')
                ->and($casts['metric_scores'])->toBe('array')
                ->and($casts['results'])->toBe('array')
                ->and($casts['duration_ms'])->toBe('float')
                ->and($casts['metadata'])->toBe('array')
                ->and($casts['started_at'])->toBe('datetime')
                ->and($casts['completed_at'])->toBe('datetime');
        });
    });

    describe('passed', function () {
        it('returns true when pass rate is 100', function () {
            $model = new AgentEvaluation;
            $model->pass_rate = 100.0;

            expect($model->passed())->toBeTrue();
        });

        it('returns true when pass rate exceeds 100', function () {
            $model = new AgentEvaluation;
            $model->pass_rate = 100.5;

            expect($model->passed())->toBeTrue();
        });

        it('returns false when pass rate is below 100', function () {
            $model = new AgentEvaluation;
            $model->pass_rate = 99.9;

            expect($model->passed())->toBeFalse();
        });

        it('returns false when pass rate is zero', function () {
            $model = new AgentEvaluation;
            $model->pass_rate = 0.0;

            expect($model->passed())->toBeFalse();
        });
    });

    describe('getDurationAttribute', function () {
        it('formats duration in milliseconds for small values', function () {
            $model = new AgentEvaluation;
            $model->duration_ms = 500.0;

            expect($model->duration)->toBe('500ms');
        });

        it('formats duration in seconds for large values', function () {
            $model = new AgentEvaluation;
            $model->duration_ms = 2500.0;

            expect($model->duration)->toBe('2.5s');
        });

        it('formats duration in seconds with rounding', function () {
            $model = new AgentEvaluation;
            $model->duration_ms = 1234.5;

            expect($model->duration)->toBe('1.23s');
        });

        it('handles zero duration', function () {
            $model = new AgentEvaluation;
            $model->duration_ms = 0.0;

            expect($model->duration)->toBe('0ms');
        });

        it('handles exactly 1000ms as seconds', function () {
            $model = new AgentEvaluation;
            $model->duration_ms = 1000.0;

            expect($model->duration)->toBe('1s');
        });

        it('handles sub-millisecond as milliseconds', function () {
            $model = new AgentEvaluation;
            $model->duration_ms = 0.5;

            expect($model->duration)->toBe('1ms');
        });
    });

    describe('model instantiation', function () {
        it('extends Eloquent Model', function () {
            $model = new AgentEvaluation;

            expect($model)->toBeInstanceOf(Model::class);
        });

        it('can set attributes via fill', function () {
            $model = new AgentEvaluation;
            $model->fill([
                'evaluation_id' => 'test-uuid',
                'suite_class' => 'App\\Tests\\MySuite',
                'agent_class' => 'App\\Agents\\MyAgent',
                'status' => AgentEvaluation::STATUS_PENDING,
            ]);

            expect($model->evaluation_id)->toBe('test-uuid')
                ->and($model->suite_class)->toBe('App\\Tests\\MySuite')
                ->and($model->agent_class)->toBe('App\\Agents\\MyAgent')
                ->and($model->status)->toBe('pending');
        });
    });
});
