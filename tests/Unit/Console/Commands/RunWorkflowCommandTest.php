<?php

declare(strict_types=1);

use AgenticOrchestrator\Console\Commands\RunWorkflowCommand;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use AgenticOrchestrator\Workflows\WorkflowResult;
use AgenticOrchestrator\Workflows\WorkflowRunner;

describe('RunWorkflowCommand', function () {
    beforeEach(function () {
        $this->context = new WorkflowContext;
        // Use a real class that exists to pass class_exists check in resolveWorkflowClass
        $this->workflowClass = WorkflowContext::class;
    });

    it('returns failure when workflow class is not found', function () {
        $this->artisan('workflow:run', ['workflow' => 'NonExistentWorkflow'])
            ->expectsOutputToContain('Workflow class not found')
            ->assertFailed();
    });

    it('runs a workflow successfully', function () {
        $this->context->markStepCompleted('step-1');

        $result = new WorkflowResult(
            executionId: 'exec-123',
            status: StepResult::STATUS_SUCCESS,
            output: 'Workflow completed',
            context: $this->context,
            duration: 150.5,
        );

        $runner = Mockery::mock(WorkflowRunner::class);
        $runner->shouldReceive('run')
            ->once()
            ->andReturn($result);

        $this->app->instance(WorkflowRunner::class, $runner);

        $this->artisan('workflow:run', ['workflow' => $this->workflowClass])
            ->expectsOutputToContain('Workflow Status: SUCCESS')
            ->expectsOutputToContain('Execution ID: exec-123')
            ->expectsOutputToContain('step-1')
            ->assertSuccessful();
    });

    it('outputs JSON when json option is provided', function () {
        $result = new WorkflowResult(
            executionId: 'exec-456',
            status: StepResult::STATUS_SUCCESS,
            output: ['key' => 'value'],
            context: $this->context,
            duration: 100.0,
        );

        $runner = Mockery::mock(WorkflowRunner::class);
        $runner->shouldReceive('run')
            ->once()
            ->andReturn($result);

        $this->app->instance(WorkflowRunner::class, $runner);

        $this->artisan('workflow:run', ['workflow' => $this->workflowClass, '--json' => true])
            ->expectsOutputToContain('exec-456')
            ->assertSuccessful();
    });

    it('returns failure when workflow execution throws', function () {
        $runner = Mockery::mock(WorkflowRunner::class);
        $runner->shouldReceive('run')
            ->once()
            ->andThrow(new RuntimeException('Step failed'));

        $this->app->instance(WorkflowRunner::class, $runner);

        $this->artisan('workflow:run', ['workflow' => $this->workflowClass])
            ->expectsOutputToContain('Workflow execution failed: Step failed')
            ->assertFailed();
    });

    it('displays failed workflow result with error details', function () {
        $this->context->markStepFailed('broken-step', 'Something went wrong');

        $result = new WorkflowResult(
            executionId: 'exec-789',
            status: StepResult::STATUS_FAILED,
            output: null,
            context: $this->context,
            duration: 50.0,
            error: 'Workflow failed at broken-step',
        );

        $runner = Mockery::mock(WorkflowRunner::class);
        $runner->shouldReceive('run')
            ->once()
            ->andReturn($result);

        $this->app->instance(WorkflowRunner::class, $runner);

        $this->artisan('workflow:run', ['workflow' => $this->workflowClass])
            ->expectsOutputToContain('FAILED')
            ->expectsOutputToContain('broken-step')
            ->assertFailed();
    });

    it('parses input parameter values correctly', function () {
        $command = new RunWorkflowCommand;
        $reflection = new ReflectionMethod($command, 'parseValue');

        // Boolean true
        expect($reflection->invoke($command, 'true'))->toBeTrue()
            ->and($reflection->invoke($command, 'TRUE'))->toBeTrue();

        // Boolean false
        expect($reflection->invoke($command, 'false'))->toBeFalse()
            ->and($reflection->invoke($command, 'FALSE'))->toBeFalse();

        // Integer
        expect($reflection->invoke($command, '42'))->toBe(42);

        // Float
        expect($reflection->invoke($command, '3.14'))->toBe(3.14);

        // JSON
        expect($reflection->invoke($command, '{"key":"value"}'))->toBe(['key' => 'value']);

        // Plain string
        expect($reflection->invoke($command, 'hello'))->toBe('hello');
    });

    it('displays paused workflow with resume instructions', function () {
        $result = new WorkflowResult(
            executionId: 'exec-paused',
            status: StepResult::STATUS_WAITING,
            output: null,
            context: $this->context,
            duration: 30.0,
        );

        $runner = Mockery::mock(WorkflowRunner::class);
        $runner->shouldReceive('run')
            ->once()
            ->andReturn($result);

        $this->app->instance(WorkflowRunner::class, $runner);

        $this->artisan('workflow:run', ['workflow' => $this->workflowClass])
            ->expectsOutputToContain('Workflow is paused')
            ->expectsOutputToContain('--resume=exec-paused')
            ->assertFailed();
    });

    it('displays pending workflow as paused', function () {
        $result = new WorkflowResult(
            executionId: 'exec-pending',
            status: StepResult::STATUS_PENDING,
            output: null,
            context: $this->context,
            duration: 10.0,
        );

        $runner = Mockery::mock(WorkflowRunner::class);
        $runner->shouldReceive('run')
            ->once()
            ->andReturn($result);

        $this->app->instance(WorkflowRunner::class, $runner);

        $this->artisan('workflow:run', ['workflow' => $this->workflowClass])
            ->expectsOutputToContain('Workflow is paused')
            ->assertFailed();
    });

    it('displays array output as formatted JSON', function () {
        $result = new WorkflowResult(
            executionId: 'exec-arr',
            status: StepResult::STATUS_SUCCESS,
            output: ['result' => 'data', 'count' => 5],
            context: $this->context,
            duration: 10.0,
        );

        $runner = Mockery::mock(WorkflowRunner::class);
        $runner->shouldReceive('run')
            ->once()
            ->andReturn($result);

        $this->app->instance(WorkflowRunner::class, $runner);

        $this->artisan('workflow:run', ['workflow' => $this->workflowClass])
            ->expectsOutputToContain('Output:')
            ->expectsOutputToContain('"result"')
            ->assertSuccessful();
    });

    it('displays string output directly', function () {
        $result = new WorkflowResult(
            executionId: 'exec-str',
            status: StepResult::STATUS_SUCCESS,
            output: 'Simple string output',
            context: $this->context,
            duration: 10.0,
        );

        $runner = Mockery::mock(WorkflowRunner::class);
        $runner->shouldReceive('run')
            ->once()
            ->andReturn($result);

        $this->app->instance(WorkflowRunner::class, $runner);

        $this->artisan('workflow:run', ['workflow' => $this->workflowClass])
            ->expectsOutputToContain('Simple string output')
            ->assertSuccessful();
    });

    it('resumes a workflow with valid state', function () {
        $result = new WorkflowResult(
            executionId: 'exec-resumed',
            status: StepResult::STATUS_SUCCESS,
            output: 'Resumed successfully',
            context: $this->context,
            duration: 20.0,
        );

        $runner = Mockery::mock(WorkflowRunner::class);
        $runner->shouldReceive('resume')
            ->once()
            ->andReturn($result);

        $this->app->instance(WorkflowRunner::class, $runner);

        $this->artisan('workflow:run', [
            'workflow' => $this->workflowClass,
            '--resume' => 'exec-original',
            '--state' => '{"step":"2"}',
        ])->assertSuccessful();
    });

    it('fails to resume without state parameter', function () {
        $runner = Mockery::mock(WorkflowRunner::class);
        $this->app->instance(WorkflowRunner::class, $runner);

        $this->artisan('workflow:run', [
            'workflow' => $this->workflowClass,
            '--resume' => 'exec-original',
        ])
            ->expectsOutputToContain('State is required for resumption')
            ->assertFailed();
    });

    it('fails to resume with invalid JSON state', function () {
        $runner = Mockery::mock(WorkflowRunner::class);
        $this->app->instance(WorkflowRunner::class, $runner);

        $this->artisan('workflow:run', [
            'workflow' => $this->workflowClass,
            '--resume' => 'exec-original',
            '--state' => '{invalid-json}',
        ])
            ->expectsOutputToContain('Invalid JSON state')
            ->assertFailed();
    });

    it('resolves workflow class name variants', function () {
        $command = new RunWorkflowCommand;
        $reflection = new ReflectionMethod($command, 'resolveWorkflowClass');

        // Fully qualified class name that exists
        $result = $reflection->invoke($command, WorkflowContext::class);
        expect($result)->toBe(WorkflowContext::class);

        // Non-existent class returns null
        $result = $reflection->invoke($command, 'TotallyNonExistentClass');
        expect($result)->toBeNull();
    });
});
