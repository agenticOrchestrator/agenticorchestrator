<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\AgentContext;
use AgenticOrchestrator\Contracts\AgentInterface;
use AgenticOrchestrator\Conversations\Message;
use AgenticOrchestrator\Conversations\MessageRole;

describe('AgentContext', function () {

    beforeEach(function () {
        $this->agent = Mockery::mock(AgentInterface::class);
        $this->agent->shouldReceive('getId')->andReturn('test-agent');
    });

    describe('construction and basic getters', function () {

        it('stores the agent instance', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'Hello',
            );

            expect($context->getAgent())->toBe($this->agent);
        });

        it('stores the user message', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'What is the weather?',
            );

            expect($context->getMessage())->toBe('What is the weather?');
        });

        it('stores team and user scopes', function () {
            $team = (object) ['id' => 1, 'name' => 'Acme'];
            $user = (object) ['id' => 42, 'name' => 'Alice'];

            $context = new AgentContext(
                agent: $this->agent,
                message: 'Hello',
                team: $team,
                user: $user,
            );

            expect($context->getTeam())->toBe($team);
            expect($context->getUser())->toBe($user);
        });

        it('defaults team and user to null', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'Hello',
            );

            expect($context->getTeam())->toBeNull();
            expect($context->getUser())->toBeNull();
        });

        it('accepts conversation history', function () {
            $history = [
                new Message(role: MessageRole::User, content: 'Hi'),
                new Message(role: MessageRole::Assistant, content: 'Hello!'),
            ];

            $context = new AgentContext(
                agent: $this->agent,
                message: 'Follow up',
                history: $history,
            );

            expect($context->getHistory())->toHaveCount(2);
            expect($context->getHistory()[0]->content)->toBe('Hi');
        });

        it('defaults to empty history', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'Hello',
            );

            expect($context->getHistory())->toBe([]);
        });
    });

    describe('history manipulation', function () {

        it('adds a message to history and returns self', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'Hello',
            );

            $message = new Message(role: MessageRole::User, content: 'New message');
            $result = $context->addToHistory($message);

            expect($result)->toBe($context);
            expect($context->getHistory())->toHaveCount(1);
            expect($context->getHistory()[0]->content)->toBe('New message');
        });

        it('appends multiple messages in order', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'Hello',
            );

            $context->addToHistory(new Message(role: MessageRole::User, content: 'First'));
            $context->addToHistory(new Message(role: MessageRole::Assistant, content: 'Second'));

            expect($context->getHistory())->toHaveCount(2);
            expect($context->getHistory()[0]->content)->toBe('First');
            expect($context->getHistory()[1]->content)->toBe('Second');
        });
    });

    describe('additional context', function () {

        it('sets and gets a context value', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'Hello',
            );

            $result = $context->set('customer_id', 123);

            expect($result)->toBe($context);
            expect($context->get('customer_id'))->toBe(123);
        });

        it('returns default when key does not exist', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'Hello',
            );

            expect($context->get('missing', 'fallback'))->toBe('fallback');
            expect($context->get('missing'))->toBeNull();
        });

        it('checks if a key exists', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'Hello',
                additionalContext: ['existing' => 'value'],
            );

            expect($context->has('existing'))->toBeTrue();
            expect($context->has('nonexistent'))->toBeFalse();
        });

        it('returns all additional context', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'Hello',
                additionalContext: ['a' => 1, 'b' => 2],
            );

            expect($context->all())->toBe(['a' => 1, 'b' => 2]);
        });

        it('supports dot notation for nested values', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'Hello',
            );

            $context->set('user.preferences.theme', 'dark');

            expect($context->get('user.preferences.theme'))->toBe('dark');
            expect($context->has('user.preferences.theme'))->toBeTrue();
        });
    });

    describe('tool results', function () {

        it('starts with empty tool calls and results', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'Hello',
            );

            expect($context->getToolCalls())->toBe([]);
            expect($context->getToolResults())->toBe([]);
        });

        it('adds tool results and tracks tool calls', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'Hello',
            );

            $result = $context->addToolResults([
                [
                    'tool_call_id' => 'call_001',
                    'name' => 'search',
                    'arguments' => ['query' => 'test'],
                    'result' => ['found' => true],
                ],
            ]);

            expect($result)->toBe($context);

            $toolCalls = $context->getToolCalls();
            expect($toolCalls)->toHaveCount(1);
            expect($toolCalls[0]['id'])->toBe('call_001');
            expect($toolCalls[0]['name'])->toBe('search');
            expect($toolCalls[0]['arguments'])->toBe(['query' => 'test']);
            expect($toolCalls[0]['result'])->toBe(['found' => true]);
        });

        it('serializes non-string results to json in tool results', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'Hello',
            );

            $context->addToolResults([
                [
                    'tool_call_id' => 'call_002',
                    'name' => 'lookup',
                    'result' => ['status' => 'ok'],
                ],
            ]);

            $toolResults = $context->getToolResults();
            expect($toolResults)->toHaveCount(1);
            expect($toolResults[0]['tool_call_id'])->toBe('call_002');
            expect($toolResults[0]['content'])->toBe('{"status":"ok"}');
        });

        it('keeps string results as-is in tool results', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'Hello',
            );

            $context->addToolResults([
                [
                    'tool_call_id' => 'call_003',
                    'name' => 'echo',
                    'result' => 'plain text result',
                ],
            ]);

            $toolResults = $context->getToolResults();
            expect($toolResults[0]['content'])->toBe('plain text result');
        });

        it('clears tool results', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'Hello',
            );

            $context->addToolResults([
                [
                    'tool_call_id' => 'call_004',
                    'name' => 'test',
                    'result' => 'data',
                ],
            ]);

            $result = $context->clearToolResults();

            expect($result)->toBe($context);
            expect($context->getToolResults())->toBe([]);
            // Tool calls should remain after clearing results
            expect($context->getToolCalls())->toHaveCount(1);
        });
    });

    describe('iteration tracking', function () {

        it('starts at iteration zero', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'Hello',
            );

            expect($context->getIteration())->toBe(0);
        });

        it('increments iteration and returns new value', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'Hello',
            );

            expect($context->incrementIteration())->toBe(1);
            expect($context->incrementIteration())->toBe(2);
            expect($context->getIteration())->toBe(2);
        });
    });

    describe('buildMessages', function () {

        it('builds messages array with system prompt and user message', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'What is AI?',
            );

            $messages = $context->buildMessages('You are a helpful assistant.');

            expect($messages)->toHaveCount(2);
            expect($messages[0])->toBe(['role' => 'system', 'content' => 'You are a helpful assistant.']);
            expect($messages[1])->toBe(['role' => 'user', 'content' => 'What is AI?']);
        });

        it('includes conversation history between system and user messages', function () {
            $history = [
                new Message(role: MessageRole::User, content: 'First question'),
                new Message(role: MessageRole::Assistant, content: 'First answer'),
            ];

            $context = new AgentContext(
                agent: $this->agent,
                message: 'Follow up question',
                history: $history,
            );

            $messages = $context->buildMessages('System prompt');

            expect($messages)->toHaveCount(4);
            expect($messages[0]['role'])->toBe('system');
            expect($messages[1])->toBe(['role' => 'user', 'content' => 'First question']);
            expect($messages[2])->toBe(['role' => 'assistant', 'content' => 'First answer']);
            expect($messages[3])->toBe(['role' => 'user', 'content' => 'Follow up question']);
        });

        it('appends tool results at the end', function () {
            $context = new AgentContext(
                agent: $this->agent,
                message: 'Use the tool',
            );

            $context->addToolResults([
                [
                    'tool_call_id' => 'call_100',
                    'name' => 'search',
                    'result' => 'found it',
                ],
            ]);

            $messages = $context->buildMessages('System');

            expect($messages)->toHaveCount(3);
            expect($messages[2]['role'])->toBe('tool');
            expect($messages[2]['tool_call_id'])->toBe('call_100');
            expect($messages[2]['content'])->toBe('found it');
        });
    });

    describe('forDelegation', function () {

        it('creates a new context for delegation', function () {
            $delegateAgent = Mockery::mock(AgentInterface::class);
            $delegateAgent->shouldReceive('getId')->andReturn('delegate-agent');

            $team = (object) ['id' => 1];
            $user = (object) ['id' => 42];

            $context = new AgentContext(
                agent: $this->agent,
                message: 'Original message',
                team: $team,
                user: $user,
                additionalContext: ['key' => 'value'],
            );

            $delegated = $context->forDelegation($delegateAgent, 'Delegated task');

            expect($delegated)->toBeInstanceOf(AgentContext::class);
            expect($delegated)->not->toBe($context);
            expect($delegated->getAgent())->toBe($delegateAgent);
            expect($delegated->getMessage())->toBe('Delegated task');
            expect($delegated->getTeam())->toBe($team);
            expect($delegated->getUser())->toBe($user);
            expect($delegated->getHistory())->toBe([]);
            expect($delegated->get('delegated_from'))->toBe('test-agent');
            expect($delegated->get('original_context'))->toBe(['key' => 'value']);
        });
    });
});
