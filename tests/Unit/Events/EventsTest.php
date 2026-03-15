<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\Events\AgentCompleted;
use AgenticOrchestrator\Agents\Events\AgentFailed;
use AgenticOrchestrator\Agents\Events\AgentStarted;
use AgenticOrchestrator\Agents\Events\ToolExecuted;
use AgenticOrchestrator\Workflows\Events\AgentDelegated;
use AgenticOrchestrator\Workflows\Events\StepCompleted;
use AgenticOrchestrator\Workflows\Events\StepFailed;
use AgenticOrchestrator\Workflows\Events\StepStarted;
use AgenticOrchestrator\Workflows\Events\WorkflowCompleted;
use AgenticOrchestrator\Workflows\Events\WorkflowFailed;
use AgenticOrchestrator\Workflows\Events\WorkflowPaused;
use AgenticOrchestrator\Workflows\Events\WorkflowStarted;

describe('Agent Events', function () {
    describe('AgentStarted', function () {
        it('stores agent information', function () {
            $event = new AgentStarted(
                agentName: 'test-agent',
                conversationId: 'conv-123',
                message: 'Hello world',
            );

            expect($event->agentName)->toBe('test-agent')
                ->and($event->conversationId)->toBe('conv-123')
                ->and($event->message)->toBe('Hello world')
                ->and($event->tenant)->toBeNull();
        });

        it('returns null tenant key when no tenant', function () {
            $event = new AgentStarted('agent', 'conv', 'msg');

            expect($event->getTenantKey())->toBeNull();
        });
    });

    describe('AgentCompleted', function () {
        it('stores completion information', function () {
            $event = new AgentCompleted(
                agentName: 'test-agent',
                conversationId: 'conv-123',
                response: 'Hi there!',
                inputTokens: 100,
                outputTokens: 50,
                duration: 1500.5,
            );

            expect($event->agentName)->toBe('test-agent')
                ->and($event->conversationId)->toBe('conv-123')
                ->and($event->response)->toBe('Hi there!')
                ->and($event->inputTokens)->toBe(100)
                ->and($event->outputTokens)->toBe(50)
                ->and($event->duration)->toBe(1500.5);
        });

        it('calculates total tokens', function () {
            $event = new AgentCompleted(
                'agent', 'conv', 'response', 100, 50, 1000.0
            );

            expect($event->getTotalTokens())->toBe(150);
        });
    });

    describe('AgentFailed', function () {
        it('stores failure information', function () {
            $exception = new RuntimeException('Test error');
            $event = new AgentFailed(
                agentName: 'test-agent',
                conversationId: 'conv-123',
                exception: $exception,
                message: 'Original message',
            );

            expect($event->agentName)->toBe('test-agent')
                ->and($event->conversationId)->toBe('conv-123')
                ->and($event->exception)->toBe($exception)
                ->and($event->message)->toBe('Original message');
        });

        it('gets error message', function () {
            $exception = new RuntimeException('Test error');
            $event = new AgentFailed('agent', 'conv', $exception, 'msg');

            expect($event->getErrorMessage())->toBe('Test error');
        });

        it('gets exception type', function () {
            $exception = new InvalidArgumentException('Bad arg');
            $event = new AgentFailed('agent', 'conv', $exception, 'msg');

            expect($event->getExceptionType())->toBe(InvalidArgumentException::class);
        });
    });

    describe('ToolExecuted', function () {
        it('stores tool execution information', function () {
            $event = new ToolExecuted(
                agentName: 'test-agent',
                toolName: 'my_tool',
                arguments: ['input' => 'test'],
                result: ['output' => 'result'],
                success: true,
                duration: 250.0,
            );

            expect($event->agentName)->toBe('test-agent')
                ->and($event->toolName)->toBe('my_tool')
                ->and($event->arguments)->toBe(['input' => 'test'])
                ->and($event->result)->toBe(['output' => 'result'])
                ->and($event->success)->toBeTrue()
                ->and($event->duration)->toBe(250.0);
        });

        it('checks if failed', function () {
            $eventSuccess = new ToolExecuted(
                'agent', 'tool', [], null, true, 100.0
            );
            $eventFailed = new ToolExecuted(
                'agent', 'tool', [], null, false, 100.0
            );

            expect($eventSuccess->failed())->toBeFalse()
                ->and($eventFailed->failed())->toBeTrue();
        });
    });
});

describe('Workflow Events', function () {
    describe('WorkflowStarted', function () {
        it('stores workflow start information', function () {
            $event = new WorkflowStarted(
                executionId: 'exec-123',
                workflowName: 'TestWorkflow',
                input: ['key' => 'value'],
            );

            expect($event->executionId)->toBe('exec-123')
                ->and($event->workflowName)->toBe('TestWorkflow')
                ->and($event->input)->toBe(['key' => 'value']);
        });
    });

    describe('WorkflowCompleted', function () {
        it('stores workflow completion information', function () {
            $event = new WorkflowCompleted(
                executionId: 'exec-123',
                output: ['result' => 'success'],
                duration: 5000.0,
            );

            expect($event->executionId)->toBe('exec-123')
                ->and($event->output)->toBe(['result' => 'success'])
                ->and($event->duration)->toBe(5000.0);
        });

        it('calculates duration in seconds', function () {
            $event = new WorkflowCompleted('exec', [], 5000.0);

            expect($event->getDurationInSeconds())->toBe(5.0);
        });
    });

    describe('WorkflowFailed', function () {
        it('stores workflow failure information', function () {
            $exception = new RuntimeException('Workflow error');
            $event = new WorkflowFailed(
                executionId: 'exec-123',
                error: 'Something failed',
                exception: $exception,
            );

            expect($event->executionId)->toBe('exec-123')
                ->and($event->error)->toBe('Something failed')
                ->and($event->exception)->toBe($exception);
        });

        it('gets exception class', function () {
            $exception = new RuntimeException('Error');
            $event = new WorkflowFailed('exec', 'error', $exception);

            expect($event->getExceptionClass())->toBe(RuntimeException::class);
        });

        it('returns null exception class when no exception', function () {
            $event = new WorkflowFailed('exec', 'error');

            expect($event->getExceptionClass())->toBeNull();
        });
    });

    describe('WorkflowPaused', function () {
        it('stores workflow pause information', function () {
            $event = new WorkflowPaused(
                executionId: 'exec-123',
                pausedAt: 'approval-step',
                reason: 'Awaiting approval',
                state: ['key' => 'value'],
            );

            expect($event->executionId)->toBe('exec-123')
                ->and($event->pausedAt)->toBe('approval-step')
                ->and($event->reason)->toBe('Awaiting approval')
                ->and($event->state)->toBe(['key' => 'value']);
        });

        it('checks if has state', function () {
            $eventWithState = new WorkflowPaused('exec', state: ['key' => 'value']);
            $eventWithoutState = new WorkflowPaused('exec');

            expect($eventWithState->hasState())->toBeTrue()
                ->and($eventWithoutState->hasState())->toBeFalse();
        });
    });

    describe('StepStarted', function () {
        it('stores step start information', function () {
            $event = new StepStarted(
                executionId: 'exec-123',
                stepName: 'analyze',
            );

            expect($event->executionId)->toBe('exec-123')
                ->and($event->stepName)->toBe('analyze');
        });
    });

    describe('StepCompleted', function () {
        it('stores step completion information', function () {
            $event = new StepCompleted(
                executionId: 'exec-123',
                stepName: 'analyze',
                output: ['analysis' => 'done'],
                duration: 1500.0,
            );

            expect($event->executionId)->toBe('exec-123')
                ->and($event->stepName)->toBe('analyze')
                ->and($event->output)->toBe(['analysis' => 'done'])
                ->and($event->duration)->toBe(1500.0);
        });
    });

    describe('StepFailed', function () {
        it('stores step failure information', function () {
            $exception = new RuntimeException('Step error');
            $event = new StepFailed(
                executionId: 'exec-123',
                stepName: 'analyze',
                error: 'Analysis failed',
                exception: $exception,
            );

            expect($event->executionId)->toBe('exec-123')
                ->and($event->stepName)->toBe('analyze')
                ->and($event->error)->toBe('Analysis failed')
                ->and($event->exception)->toBe($exception);
        });
    });

    describe('AgentDelegated', function () {
        it('stores delegation information', function () {
            $event = new AgentDelegated(
                fromAgent: 'supervisor',
                toAgent: 'worker',
                message: 'Analyze this data',
                depth: 2,
            );

            expect($event->fromAgent)->toBe('supervisor')
                ->and($event->toAgent)->toBe('worker')
                ->and($event->message)->toBe('Analyze this data')
                ->and($event->depth)->toBe(2);
        });

        it('defaults depth to 1', function () {
            $event = new AgentDelegated('from', 'to', 'msg');

            expect($event->depth)->toBe(1);
        });
    });
});
