<?php

declare(strict_types=1);

use AgenticOrchestrator\Contracts\WorkflowInterface;
use AgenticOrchestrator\Testing\FakeWorkflow;
use AgenticOrchestrator\Workflows\WorkflowContext;
use AgenticOrchestrator\Workflows\WorkflowDefinition;
use AgenticOrchestrator\Workflows\WorkflowResult;
use PHPUnit\Framework\AssertionFailedError;

covers(FakeWorkflow::class);

describe('FakeWorkflow', function () {

    it('creates via static make method', function () {
        $workflow = FakeWorkflow::make();

        expect($workflow)->toBeInstanceOf(FakeWorkflow::class)
            ->and($workflow)->toBeInstanceOf(WorkflowInterface::class);
    });

    it('sets name via named()', function () {
        $workflow = FakeWorkflow::make()->named('TestWorkflow');

        $definition = $workflow->definition();

        expect($definition)->toBeInstanceOf(WorkflowDefinition::class);
    });

    it('returns workflow definition', function () {
        $workflow = FakeWorkflow::make()->named('CustomFlow');

        $definition = $workflow->definition();

        expect($definition)->toBeInstanceOf(WorkflowDefinition::class);
    });

    describe('run()', function () {
        it('succeeds with array output', function () {
            $workflow = FakeWorkflow::make()
                ->succeedsWith(['result' => 'done']);

            $result = $workflow->run(['input' => 'test']);

            expect($result)->toBeInstanceOf(WorkflowResult::class)
                ->and($result->isSuccess())->toBeTrue()
                ->and($result->getOutput())->toBe(['result' => 'done']);
        });

        it('succeeds with closure returning array', function () {
            $workflow = FakeWorkflow::make()
                ->succeedsWith(fn ($input) => ['processed' => $input['data']]);

            $result = $workflow->run(['data' => 'hello']);

            expect($result->isSuccess())->toBeTrue()
                ->and($result->getOutput())->toBe(['processed' => 'hello']);
        });

        it('succeeds with closure returning WorkflowResult', function () {
            $customResult = new WorkflowResult(
                executionId: 'custom-exec',
                status: 'success',
                output: ['custom' => true],
                context: new WorkflowContext([]),
                duration: 50.0,
            );

            $workflow = FakeWorkflow::make()
                ->succeedsWith(fn ($input) => $customResult);

            $result = $workflow->run([]);

            expect($result)->toBe($customResult);
        });

        it('fails with message and no step', function () {
            $workflow = FakeWorkflow::make()
                ->fails('Something broke');

            $result = $workflow->run(['x' => 1]);

            expect($result->isFailed())->toBeTrue()
                ->and($result->error)->toBe('Something broke');
        });

        it('fails with message and specific step', function () {
            $workflow = FakeWorkflow::make()
                ->fails('Step failed', 'validation-step');

            $result = $workflow->run([]);

            expect($result->isFailed())->toBeTrue()
                ->and($result->error)->toBe('Step failed');
        });

        it('fails with default message', function () {
            $workflow = FakeWorkflow::make()->fails();

            $result = $workflow->run([]);

            expect($result->isFailed())->toBeTrue()
                ->and($result->error)->toBe('Workflow failed');
        });

        it('pauses at specified step', function () {
            $workflow = FakeWorkflow::make()
                ->pausesAt('approval-step', ['partial' => 'data']);

            $result = $workflow->run([]);

            expect($result->isPaused())->toBeTrue();
        });

        it('pauses at step with empty state', function () {
            $workflow = FakeWorkflow::make()
                ->pausesAt('waiting-step');

            $result = $workflow->run([]);

            expect($result->isPaused())->toBeTrue();
        });

        it('records input for each run', function () {
            $workflow = FakeWorkflow::make()->succeedsWith([]);

            $workflow->run(['first' => true]);
            $workflow->run(['second' => true]);

            $runs = $workflow->getRuns();
            expect($runs)->toHaveCount(2)
                ->and($runs[0]['input'])->toBe(['first' => true])
                ->and($runs[1]['input'])->toBe(['second' => true]);
        });

        it('runs with empty input by default', function () {
            $workflow = FakeWorkflow::make()->succeedsWith(['done' => true]);

            $result = $workflow->run();

            expect($result->isSuccess())->toBeTrue();
        });
    });

    describe('forTeam()', function () {
        it('creates a clone for integer team', function () {
            $workflow = FakeWorkflow::make()->named('Original');

            $scoped = $workflow->forTeam(42);

            expect($scoped)->not->toBe($workflow)
                ->and($scoped)->toBeInstanceOf(FakeWorkflow::class);
        });

        it('creates a clone for string team', function () {
            $scoped = FakeWorkflow::make()->forTeam('99');

            expect($scoped)->toBeInstanceOf(FakeWorkflow::class);
        });

        it('creates a clone for object team', function () {
            $team = new class
            {
                public function getKey(): int
                {
                    return 7;
                }
            };

            $scoped = FakeWorkflow::make()->forTeam($team);

            expect($scoped)->toBeInstanceOf(FakeWorkflow::class);
        });
    });

    describe('getLastRunInput', function () {
        it('returns null when no runs', function () {
            $workflow = FakeWorkflow::make();

            expect($workflow->getLastRunInput())->toBeNull();
        });

        it('returns last run input', function () {
            $workflow = FakeWorkflow::make()->succeedsWith([]);

            $workflow->run(['a' => 1]);
            $workflow->run(['b' => 2]);

            expect($workflow->getLastRunInput())->toBe(['b' => 2]);
        });
    });

    describe('assertions', function () {
        it('assertRan passes when run', function () {
            $workflow = FakeWorkflow::make()->succeedsWith([]);
            $workflow->run([]);

            $workflow->assertRan();
            expect(true)->toBeTrue();
        });

        it('assertRan throws when not run', function () {
            $workflow = FakeWorkflow::make();

            expect(fn () => $workflow->assertRan())->toThrow(
                AssertionFailedError::class,
                'Expected workflow to be run, but it was not.'
            );
        });

        it('assertNotRan passes when not run', function () {
            $workflow = FakeWorkflow::make();

            $workflow->assertNotRan();
            expect(true)->toBeTrue();
        });

        it('assertNotRan throws when run', function () {
            $workflow = FakeWorkflow::make()->succeedsWith([]);
            $workflow->run([]);

            expect(fn () => $workflow->assertNotRan())->toThrow(
                AssertionFailedError::class,
                'Expected workflow not to be run, but it was run 1 time(s).'
            );
        });

        it('assertRanTimes passes with correct count', function () {
            $workflow = FakeWorkflow::make()->succeedsWith([]);
            $workflow->run([]);
            $workflow->run([]);
            $workflow->run([]);

            $workflow->assertRanTimes(3);
            expect(true)->toBeTrue();
        });

        it('assertRanTimes throws with wrong count', function () {
            $workflow = FakeWorkflow::make()->succeedsWith([]);
            $workflow->run([]);

            expect(fn () => $workflow->assertRanTimes(5))->toThrow(
                AssertionFailedError::class,
                'Expected workflow to be run 5 time(s), but it was run 1 time(s).'
            );
        });

        it('assertRanWith passes for matching input', function () {
            $workflow = FakeWorkflow::make()->succeedsWith([]);
            $workflow->run(['key' => 'value']);

            $workflow->assertRanWith(['key' => 'value']);
            expect(true)->toBeTrue();
        });

        it('assertRanWith throws for no matching input', function () {
            $workflow = FakeWorkflow::make()->succeedsWith([]);
            $workflow->run(['a' => 1]);

            expect(fn () => $workflow->assertRanWith(['b' => 2]))->toThrow(
                AssertionFailedError::class,
            );
        });

        it('assertRanWithKey passes when key exists', function () {
            $workflow = FakeWorkflow::make()->succeedsWith([]);
            $workflow->run(['name' => 'test', 'type' => 'unit']);

            $workflow->assertRanWithKey('name');
            $workflow->assertRanWithKey('type');
            expect(true)->toBeTrue();
        });

        it('assertRanWithKey throws when key missing', function () {
            $workflow = FakeWorkflow::make()->succeedsWith([]);
            $workflow->run(['a' => 1]);

            expect(fn () => $workflow->assertRanWithKey('missing'))->toThrow(
                AssertionFailedError::class,
                'Expected workflow to be run with input key "missing", but it was not.'
            );
        });
    });

    it('resets runs', function () {
        $workflow = FakeWorkflow::make()->succeedsWith([]);

        $workflow->run(['x' => 1]);
        $workflow->run(['y' => 2]);
        $result = $workflow->reset();

        expect($result)->toBe($workflow) // fluent
            ->and($workflow->getRuns())->toBeEmpty();
    });
});
