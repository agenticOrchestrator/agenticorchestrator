<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\AgentResponse;
use AgenticOrchestrator\Contracts\AgentInterface;
use AgenticOrchestrator\Events\AgentEvents\AgentDelegated;
use AgenticOrchestrator\Events\AgentEvents\AgentFailed;
use AgenticOrchestrator\Events\AgentEvents\AgentResponded;
use AgenticOrchestrator\Events\AgentEvents\AgentStarted;

describe('AgentEvents', function () {
    describe('AgentStarted', function () {
        it('stores the agent and message', function () {
            $agent = Mockery::mock(AgentInterface::class);
            $event = new AgentStarted(
                agent: $agent,
                message: 'Hello, agent!',
            );

            expect($event->agent)->toBe($agent)
                ->and($event->message)->toBe('Hello, agent!');
        });

        it('holds a readonly agent reference', function () {
            $agent = Mockery::mock(AgentInterface::class);
            $event = new AgentStarted($agent, 'test message');

            expect($event->agent)->toBeInstanceOf(AgentInterface::class);
        });
    });

    describe('AgentResponded', function () {
        it('stores the agent and response', function () {
            $agent = Mockery::mock(AgentInterface::class);
            $response = new AgentResponse(content: 'Generated answer');

            $event = new AgentResponded(
                agent: $agent,
                response: $response,
            );

            expect($event->agent)->toBe($agent)
                ->and($event->response)->toBe($response)
                ->and($event->response->content)->toBe('Generated answer');
        });

        it('preserves the full response object', function () {
            $agent = Mockery::mock(AgentInterface::class);
            $response = new AgentResponse(
                content: 'Answer',
                toolCalls: [['id' => 'tc1', 'name' => 'search', 'arguments' => [], 'result' => null]],
                usage: ['prompt_tokens' => 100, 'completion_tokens' => 50, 'total_tokens' => 150],
                latency: 1234.5,
                finishReason: 'stop',
            );

            $event = new AgentResponded($agent, $response);

            expect($event->response->content)->toBe('Answer')
                ->and($event->response->getTotalTokens())->toBe(150)
                ->and($event->response->hasToolCalls())->toBeTrue()
                ->and($event->response->getLatency())->toBe(1234.5);
        });
    });

    describe('AgentFailed', function () {
        it('stores the agent and exception', function () {
            $agent = Mockery::mock(AgentInterface::class);
            $exception = new RuntimeException('Something went wrong');

            $event = new AgentFailed(
                agent: $agent,
                exception: $exception,
            );

            expect($event->agent)->toBe($agent)
                ->and($event->exception)->toBe($exception)
                ->and($event->exception->getMessage())->toBe('Something went wrong');
        });

        it('accepts any throwable', function () {
            $agent = Mockery::mock(AgentInterface::class);
            $error = new Error('Fatal error');

            $event = new AgentFailed($agent, $error);

            expect($event->exception)->toBeInstanceOf(Throwable::class)
                ->and($event->exception->getMessage())->toBe('Fatal error');
        });
    });

    describe('AgentDelegated', function () {
        it('stores from agent, to agent, and task', function () {
            $fromAgent = Mockery::mock(AgentInterface::class);
            $toAgent = Mockery::mock(AgentInterface::class);

            $event = new AgentDelegated(
                fromAgent: $fromAgent,
                toAgent: $toAgent,
                task: 'Analyze the dataset',
            );

            expect($event->fromAgent)->toBe($fromAgent)
                ->and($event->toAgent)->toBe($toAgent)
                ->and($event->task)->toBe('Analyze the dataset');
        });

        it('holds distinct agent references', function () {
            $fromAgent = Mockery::mock(AgentInterface::class);
            $toAgent = Mockery::mock(AgentInterface::class);

            $event = new AgentDelegated($fromAgent, $toAgent, 'Do work');

            expect($event->fromAgent)->not->toBe($event->toAgent);
        });
    });
});
