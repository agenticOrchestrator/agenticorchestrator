<?php

declare(strict_types=1);

use AgenticOrchestrator\Contracts\AgentInterface;
use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\Patterns\SequentialPattern;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use AgenticOrchestrator\Workflows\WorkflowDefinition;

describe('WorkflowDefinition - Extended Coverage', function () {

    describe('agent', function () {
        it('adds an agent step with a string agent name and string message', function () {
            $definition = WorkflowDefinition::create()
                ->agent('writer', 'App\\Agents\\WriterAgent', 'Write a summary');

            expect($definition->hasStep('writer'))->toBeTrue()
                ->and($definition->count())->toBe(1)
                ->and($definition->getStep('writer'))->not->toBeNull()
                ->and($definition->getStep('writer')->getName())->toBe('writer');
        });

        it('adds an agent step with an AgentInterface instance', function () {
            $agent = Mockery::mock(AgentInterface::class);

            $definition = WorkflowDefinition::create()
                ->agent('reviewer', $agent, 'Review this document');

            expect($definition->hasStep('reviewer'))->toBeTrue()
                ->and($definition->count())->toBe(1);
        });

        it('adds an agent step with a closure message', function () {
            $definition = WorkflowDefinition::create()
                ->agent('analyzer', 'App\\Agents\\Analyzer', function (WorkflowContext $ctx) {
                    return 'Analyze: '.$ctx->get('input');
                });

            expect($definition->hasStep('analyzer'))->toBeTrue();
        });
    });

    describe('approval', function () {
        it('adds a human approval step with a string prompt', function () {
            $definition = WorkflowDefinition::create()
                ->approval('review', 'Please approve this content');

            expect($definition->hasStep('review'))->toBeTrue()
                ->and($definition->count())->toBe(1)
                ->and($definition->getStep('review'))->not->toBeNull();
        });

        it('adds a human approval step with a closure prompt', function () {
            $definition = WorkflowDefinition::create()
                ->approval('approve-plan', function (WorkflowContext $ctx) {
                    return 'Approve plan for: '.$ctx->get('project');
                });

            expect($definition->hasStep('approve-plan'))->toBeTrue();
        });
    });

    describe('when (conditional)', function () {
        it('adds a conditional step with only then branch', function () {
            $thenStep = Mockery::mock(StepInterface::class);
            $thenStep->shouldReceive('getName')->andReturn('then-step');
            $thenStep->shouldReceive('as')->andReturn($thenStep);
            $thenStep->shouldReceive('execute')->andReturn(StepResult::success());

            $definition = WorkflowDefinition::create()
                ->when(
                    'check-quality',
                    fn (WorkflowContext $ctx) => $ctx->get('score') > 80,
                    $thenStep
                );

            expect($definition->hasStep('check-quality'))->toBeTrue()
                ->and($definition->count())->toBe(1);
        });

        it('adds a conditional step with else branch', function () {
            $thenStep = Mockery::mock(StepInterface::class);
            $thenStep->shouldReceive('getName')->andReturn('then-step');
            $thenStep->shouldReceive('as')->andReturn($thenStep);

            $elseStep = Mockery::mock(StepInterface::class);
            $elseStep->shouldReceive('getName')->andReturn('else-step');

            $definition = WorkflowDefinition::create()
                ->when(
                    'branch',
                    fn (WorkflowContext $ctx) => true,
                    $thenStep,
                    $elseStep
                );

            expect($definition->hasStep('branch'))->toBeTrue()
                ->and($definition->count())->toBe(1);
        });
    });

    describe('parallel', function () {
        it('adds a parallel pattern step', function () {
            $step1 = Mockery::mock(StepInterface::class);
            $step1->shouldReceive('getName')->andReturn('step1');
            $step2 = Mockery::mock(StepInterface::class);
            $step2->shouldReceive('getName')->andReturn('step2');

            $definition = WorkflowDefinition::create()
                ->parallel('parallel-group', [$step1, $step2]);

            expect($definition->hasStep('parallel-group'))->toBeTrue()
                ->and($definition->count())->toBe(1);
        });
    });

    describe('sequential', function () {
        it('adds a sequential pattern step', function () {
            $step1 = Mockery::mock(StepInterface::class);
            $step1->shouldReceive('getName')->andReturn('step1');
            $step2 = Mockery::mock(StepInterface::class);
            $step2->shouldReceive('getName')->andReturn('step2');

            $definition = WorkflowDefinition::create()
                ->sequential('seq-group', [$step1, $step2]);

            expect($definition->hasStep('seq-group'))->toBeTrue()
                ->and($definition->count())->toBe(1);
        });
    });

    describe('supervisor', function () {
        it('adds a supervisor pattern step with string agent', function () {
            $definition = WorkflowDefinition::create()
                ->supervisor('supervised', 'App\\Agents\\Supervisor', [
                    'writer' => 'App\\Agents\\Writer',
                    'reviewer' => 'App\\Agents\\Reviewer',
                ]);

            expect($definition->hasStep('supervised'))->toBeTrue()
                ->and($definition->count())->toBe(1);
        });

        it('adds a supervisor pattern step with AgentInterface', function () {
            $supervisor = Mockery::mock(AgentInterface::class);

            $definition = WorkflowDefinition::create()
                ->supervisor('supervised', $supervisor, [
                    'worker1' => 'App\\Agents\\Worker',
                ]);

            expect($definition->hasStep('supervised'))->toBeTrue();
        });
    });

    describe('output', function () {
        it('sets output key on an existing step that has outputAs method', function () {
            $definition = WorkflowDefinition::create()
                ->callback('transform', function (WorkflowContext $ctx) {
                    return ['result' => 'data'];
                })
                ->output('transform', 'transform_result');

            $step = $definition->getStep('transform');
            expect($step->getOutputKey())->toBe('transform_result');
        });

        it('does nothing for non-existent step name', function () {
            $definition = WorkflowDefinition::create()
                ->output('nonexistent', 'some_key');

            expect($definition->count())->toBe(0);
        });

        it('does nothing when step does not have outputAs method', function () {
            $step = Mockery::mock(StepInterface::class);
            $step->shouldReceive('getName')->andReturn('mock-step');
            $step->shouldReceive('execute')->andReturn(StepResult::success());
            // No outputAs method

            $definition = WorkflowDefinition::create()
                ->addStep('mock-step', $step)
                ->output('mock-step', 'some_key');

            // Should not throw - just does nothing
            expect($definition->hasStep('mock-step'))->toBeTrue();
        });
    });

    describe('build', function () {
        it('builds a SequentialPattern from all steps', function () {
            $step1 = Mockery::mock(StepInterface::class);
            $step1->shouldReceive('getName')->andReturn('step1');
            $step2 = Mockery::mock(StepInterface::class);
            $step2->shouldReceive('getName')->andReturn('step2');

            $definition = WorkflowDefinition::create()
                ->addStep($step1)
                ->addStep($step2);

            $pattern = $definition->build();

            expect($pattern)->toBeInstanceOf(SequentialPattern::class);
        });

        it('builds an empty SequentialPattern for empty definition', function () {
            $pattern = WorkflowDefinition::create()->build();

            expect($pattern)->toBeInstanceOf(SequentialPattern::class);
        });
    });

    describe('metadata merging', function () {
        it('merges metadata cumulatively', function () {
            $definition = WorkflowDefinition::create()
                ->metadata(['version' => '1.0'])
                ->metadata(['author' => 'test'])
                ->metadata(['version' => '2.0']);

            $meta = $definition->getMetadata();

            expect($meta['version'])->toBe('2.0')
                ->and($meta['author'])->toBe('test');
        });

        it('name and description integrate with metadata', function () {
            $definition = WorkflowDefinition::create()
                ->name('My Workflow')
                ->description('A description')
                ->metadata(['extra' => 'data']);

            $meta = $definition->getMetadata();

            expect($meta['name'])->toBe('My Workflow')
                ->and($meta['description'])->toBe('A description')
                ->and($meta['extra'])->toBe('data');
        });
    });

    describe('complex workflow construction', function () {
        it('builds a multi-step workflow with various step types', function () {
            $agent = Mockery::mock(AgentInterface::class);
            $thenStep = Mockery::mock(StepInterface::class);
            $thenStep->shouldReceive('getName')->andReturn('then');
            $thenStep->shouldReceive('as')->andReturn($thenStep);
            $parallelStep = Mockery::mock(StepInterface::class);
            $parallelStep->shouldReceive('getName')->andReturn('p1');

            $definition = WorkflowDefinition::create()
                ->name('Complex Workflow')
                ->agent('analyze', $agent, 'Analyze the data')
                ->callback('transform', fn (WorkflowContext $ctx) => 'transformed')
                ->when('check', fn (WorkflowContext $ctx) => true, $thenStep)
                ->parallel('parallel-ops', [$parallelStep])
                ->approval('final-review', 'Approve final output')
                ->after('transform', ['analyze'])
                ->after('check', ['transform']);

            expect($definition->count())->toBe(5)
                ->and($definition->hasStep('analyze'))->toBeTrue()
                ->and($definition->hasStep('transform'))->toBeTrue()
                ->and($definition->hasStep('check'))->toBeTrue()
                ->and($definition->hasStep('parallel-ops'))->toBeTrue()
                ->and($definition->hasStep('final-review'))->toBeTrue();

            $deps = $definition->getDependencies();
            expect($deps['transform'])->toBe(['analyze'])
                ->and($deps['check'])->toBe(['transform']);
        });
    });
});
