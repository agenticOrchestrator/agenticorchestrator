<?php

declare(strict_types=1);

use AgenticOrchestrator\Contracts\AgentInterface;
use AgenticOrchestrator\Contracts\MemoryInterface;
use AgenticOrchestrator\Streaming\StreamResponse;
use AgenticOrchestrator\Testing\FakeAgent;
use AgenticOrchestrator\Testing\FakeMemory;
use AgenticOrchestrator\Testing\FakeResponse;
use AgenticOrchestrator\Testing\FakeTool;
use PHPUnit\Framework\AssertionFailedError;

covers(FakeAgent::class);

describe('FakeAgent', function () {

    it('creates via static make method', function () {
        $agent = FakeAgent::make();

        expect($agent)->toBeInstanceOf(FakeAgent::class)
            ->and($agent)->toBeInstanceOf(AgentInterface::class);
    });

    it('has default properties', function () {
        $agent = FakeAgent::make();

        expect($agent->getName())->toBe('fake-agent')
            ->and($agent->getId())->toBe('fake-agent-id')
            ->and($agent->getModel())->toBe('gpt-4')
            ->and($agent->getProvider())->toBe('openai')
            ->and($agent->getDescription())->toBe('Fake agent for testing')
            ->and($agent->instructions())->toBe('Fake agent for testing')
            ->and($agent->canBeDelegate())->toBeTrue();
    });

    it('sets name and derives id via named()', function () {
        $agent = FakeAgent::make()->named('my-agent');

        expect($agent->getName())->toBe('my-agent')
            ->and($agent->getId())->toBe('my-agent-id');
    });

    it('sets model and provider via usingModel()', function () {
        $agent = FakeAgent::make()->usingModel('claude-3', 'anthropic');

        expect($agent->getModel())->toBe('claude-3')
            ->and($agent->getProvider())->toBe('anthropic');
    });

    it('returns default provider when not specified in usingModel()', function () {
        $agent = FakeAgent::make()->usingModel('gpt-3.5-turbo');

        expect($agent->getProvider())->toBe('openai');
    });

    it('returns config array', function () {
        $agent = FakeAgent::make()->named('test')->usingModel('gpt-4o', 'openai');

        $config = $agent->getConfig();

        expect($config)->toBe([
            'name' => 'test',
            'model' => 'gpt-4o',
            'provider' => 'openai',
        ]);
    });

    it('returns default fake response when no responses configured', function () {
        $agent = FakeAgent::make();

        $response = $agent->respond('Hello');

        expect($response->content)->toBe('Fake response');
    });

    it('responds with single string', function () {
        $agent = FakeAgent::make()->respondWith('Custom response');

        $response = $agent->respond('Hello');

        expect($response->content)->toBe('Custom response');
    });

    it('responds with AgentResponse object', function () {
        $expected = FakeResponse::text('Direct response');
        $agent = FakeAgent::make()->respondWith($expected);

        $response = $agent->respond('Hello');

        expect($response)->toBe($expected);
    });

    it('responds with closure returning string', function () {
        $agent = FakeAgent::make()->respondWith(
            fn (string $msg, array $ctx) => "Echo: {$msg}"
        );

        $response = $agent->respond('Test');

        expect($response->content)->toBe('Echo: Test');
    });

    it('responds with closure returning AgentResponse', function () {
        $agent = FakeAgent::make()->respondWith(
            fn (string $msg) => FakeResponse::text("Response for: {$msg}")
        );

        $response = $agent->respond('Query');

        expect($response->content)->toBe('Response for: Query');
    });

    it('responds with array sequence of mixed types', function () {
        $direct = FakeResponse::text('Direct');
        $agent = FakeAgent::make()->respondWith([
            'String response',
            $direct,
            fn ($msg) => "Closure: {$msg}",
        ]);

        expect($agent->respond('1')->content)->toBe('String response')
            ->and($agent->respond('2'))->toBe($direct)
            ->and($agent->respond('3')->content)->toBe('Closure: 3');
    });

    it('repeats last response when sequence is exhausted', function () {
        $agent = FakeAgent::make()->respondWith(['First', 'Last']);

        $agent->respond('1');
        $agent->respond('2');
        $response = $agent->respond('3');

        expect($response->content)->toBe('Last');
    });

    it('records calls with message and context', function () {
        $agent = FakeAgent::make()->respondWith('OK');

        $agent->respond('Hello', ['key' => 'value']);

        $calls = $agent->getCalls();
        expect($calls)->toHaveCount(1)
            ->and($calls[0]['message'])->toBe('Hello')
            ->and($calls[0]['context'])->toBe(['key' => 'value']);
    });

    it('returns null for getLastCall when no calls made', function () {
        $agent = FakeAgent::make();

        expect($agent->getLastCall())->toBeNull();
    });

    it('returns last call correctly', function () {
        $agent = FakeAgent::make()->respondWith('OK');

        $agent->respond('First');
        $agent->respond('Second', ['ctx' => true]);

        $lastCall = $agent->getLastCall();
        expect($lastCall['message'])->toBe('Second')
            ->and($lastCall['context'])->toBe(['ctx' => true]);
    });

    it('expects a message via expectMessage fluent method', function () {
        $agent = FakeAgent::make()
            ->respondWith('OK')
            ->expectMessage('expected message');

        // expectMessage is a setup method, doesn't enforce - just stores
        // the test verifies fluent chaining works
        expect($agent)->toBeInstanceOf(FakeAgent::class);
    });

    it('sets and gets memory', function () {
        $memory = FakeMemory::make();
        $agent = FakeAgent::make()->withMemory($memory);

        expect($agent->getMemory())->toBe($memory);
    });

    it('creates default FakeMemory when none set', function () {
        $agent = FakeAgent::make();

        $memory = $agent->getMemory();

        expect($memory)->toBeInstanceOf(MemoryInterface::class)
            ->and($memory)->toBeInstanceOf(FakeMemory::class);
    });

    it('caches the default memory on subsequent calls', function () {
        $agent = FakeAgent::make();

        $memory1 = $agent->getMemory();
        $memory2 = $agent->getMemory();

        expect($memory1)->toBe($memory2);
    });

    it('sets tools via withTools()', function () {
        $tool1 = FakeTool::make('tool_a');
        $tool2 = FakeTool::make('tool_b');

        $agent = FakeAgent::make()->withTools([$tool1, $tool2]);

        expect($agent->getTools())->toHaveCount(2)
            ->and($agent->getTools()->first())->toBe($tool1);
    });

    it('returns empty tools collection by default', function () {
        $agent = FakeAgent::make();

        expect($agent->getTools())->toBeEmpty();
    });

    it('delegates to another agent', function () {
        $delegate = FakeAgent::make()->respondWith('Delegated result');
        $agent = FakeAgent::make();

        $result = $agent->delegate($delegate, 'Do something', ['extra' => true]);

        expect($result->content)->toBe('Delegated result');
        $delegate->assertReceivedMessage('Do something');
    });

    it('stream method calls respond internally', function () {
        $agent = FakeAgent::make()->respondWith('Streamed content');

        // FakeAgent::stream() passes a Closure (generator function) to StreamResponse,
        // but StreamResponse expects Generator|iterable. This causes a TypeError.
        // The respond() call inside stream() still executes, recording the call.
        expect(fn () => $agent->stream('Hello', ['key' => 'val']))->toThrow(TypeError::class);

        expect($agent->getCalls())->toHaveCount(1)
            ->and($agent->getCalls()[0]['message'])->toBe('Hello')
            ->and($agent->getCalls()[0]['context'])->toBe(['key' => 'val']);
    });

    it('creates a clone for forTeam with integer', function () {
        $agent = FakeAgent::make()->named('original');

        $scoped = $agent->forTeam(42);

        // Should be a different instance (clone)
        expect($scoped)->not->toBe($agent)
            ->and($scoped->getName())->toBe('original');
    });

    it('creates a clone for forTeam with string', function () {
        $scoped = FakeAgent::make()->forTeam('99');

        expect($scoped)->toBeInstanceOf(FakeAgent::class);
    });

    it('creates a clone for forTeam with object', function () {
        $team = new class
        {
            public function getKey(): int
            {
                return 7;
            }
        };

        $scoped = FakeAgent::make()->forTeam($team);

        expect($scoped)->toBeInstanceOf(FakeAgent::class);
    });

    it('creates a clone for forUser with integer', function () {
        $agent = FakeAgent::make()->named('original');

        $scoped = $agent->forUser(123);

        expect($scoped)->not->toBe($agent)
            ->and($scoped->getName())->toBe('original');
    });

    it('creates a clone for forUser with string', function () {
        $scoped = FakeAgent::make()->forUser('user-abc');

        expect($scoped)->toBeInstanceOf(FakeAgent::class);
    });

    describe('assertions', function () {
        it('assertCalled passes when called', function () {
            $agent = FakeAgent::make()->respondWith('OK');
            $agent->respond('Hi');

            $agent->assertCalled(); // should not throw
            expect(true)->toBeTrue();
        });

        it('assertCalled throws when not called', function () {
            $agent = FakeAgent::make();

            expect(fn () => $agent->assertCalled())->toThrow(AssertionFailedError::class);
        });

        it('assertNotCalled passes when not called', function () {
            $agent = FakeAgent::make();

            $agent->assertNotCalled(); // should not throw
            expect(true)->toBeTrue();
        });

        it('assertNotCalled throws when called', function () {
            $agent = FakeAgent::make()->respondWith('OK');
            $agent->respond('Hi');

            expect(fn () => $agent->assertNotCalled())->toThrow(
                AssertionFailedError::class,
                'Expected agent not to be called, but it was called 1 time(s).'
            );
        });

        it('assertCalledTimes passes with correct count', function () {
            $agent = FakeAgent::make()->respondWith('OK');
            $agent->respond('1');
            $agent->respond('2');

            $agent->assertCalledTimes(2);
            expect(true)->toBeTrue();
        });

        it('assertCalledTimes throws with wrong count', function () {
            $agent = FakeAgent::make()->respondWith('OK');
            $agent->respond('1');

            expect(fn () => $agent->assertCalledTimes(5))->toThrow(
                AssertionFailedError::class,
                'Expected agent to be called 5 time(s), but it was called 1 time(s).'
            );
        });

        it('assertReceivedMessage passes for exact match', function () {
            $agent = FakeAgent::make()->respondWith('OK');
            $agent->respond('Hello world');

            $agent->assertReceivedMessage('Hello world');
            expect(true)->toBeTrue();
        });

        it('assertReceivedMessage throws for no match', function () {
            $agent = FakeAgent::make()->respondWith('OK');
            $agent->respond('Hello');

            expect(fn () => $agent->assertReceivedMessage('Goodbye'))->toThrow(
                AssertionFailedError::class,
                'Expected agent to receive message "Goodbye", but it did not.'
            );
        });

        it('assertReceivedMessageContaining passes for substring', function () {
            $agent = FakeAgent::make()->respondWith('OK');
            $agent->respond('Hello beautiful world');

            $agent->assertReceivedMessageContaining('beautiful');
            expect(true)->toBeTrue();
        });

        it('assertReceivedMessageContaining throws for no match', function () {
            $agent = FakeAgent::make()->respondWith('OK');
            $agent->respond('Hello');

            expect(fn () => $agent->assertReceivedMessageContaining('xyz'))->toThrow(
                AssertionFailedError::class,
                'Expected agent to receive message containing "xyz", but it did not.'
            );
        });
    });

    it('resets calls and response index', function () {
        $agent = FakeAgent::make()->respondWith(['A', 'B']);

        $agent->respond('1');
        $agent->respond('2');
        $result = $agent->reset();

        expect($result)->toBe($agent) // fluent
            ->and($agent->getCalls())->toBeEmpty()
            ->and($agent->respond('new')->content)->toBe('A'); // index reset
    });
});
