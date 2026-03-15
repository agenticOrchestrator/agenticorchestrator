<?php

declare(strict_types=1);

use AgenticOrchestrator\Conversations\Message;
use AgenticOrchestrator\Testing\FakeAgent;
use AgenticOrchestrator\Testing\FakeMemory;
use AgenticOrchestrator\Testing\FakeResponse;
use AgenticOrchestrator\Testing\FakeTool;
use AgenticOrchestrator\Testing\FakeWorkflow;
use PHPUnit\Framework\AssertionFailedError;

describe('FakeResponse', function () {
    it('creates simple text response', function () {
        $response = FakeResponse::text('Hello world');

        expect($response->content)->toBe('Hello world')
            ->and($response->hasToolCalls())->toBeFalse();
    });

    it('builds response with tokens', function () {
        $response = FakeResponse::make()
            ->content('Test')
            ->tokens(50, 100)
            ->build();

        expect($response->content)->toBe('Test')
            ->and($response->getPromptTokens())->toBe(50)
            ->and($response->getCompletionTokens())->toBe(100)
            ->and($response->getTotalTokens())->toBe(150);
    });

    it('builds response with tool calls', function () {
        $response = FakeResponse::make()
            ->content('Using tool')
            ->finishReason('tool_calls')
            ->withToolCall('call-1', 'my_tool', ['input' => 'test'])
            ->build();

        expect($response->hasToolCalls())->toBeTrue()
            ->and($response->getToolCalls())->toHaveCount(1);
    });

    it('creates response with tools helper', function () {
        $response = FakeResponse::withTools('Result', [
            ['id' => 'call-1', 'name' => 'tool_a', 'arguments' => ['x' => 1]],
            ['id' => 'call-2', 'name' => 'tool_b'],
        ]);

        expect($response->hasToolCalls())->toBeTrue()
            ->and($response->getToolCalls())->toHaveCount(2);
    });

    it('creates error response', function () {
        $response = FakeResponse::error('Something went wrong');

        expect($response->content)->toBe('Something went wrong')
            ->and($response->finishReason)->toBe('error');
    });

    it('creates truncated response', function () {
        $response = FakeResponse::truncated('Partial content...');

        expect($response->content)->toBe('Partial content...')
            ->and($response->wasTruncated())->toBeTrue();
    });
});

describe('FakeAgent', function () {
    it('responds with configured response', function () {
        $fake = FakeAgent::make()->respondWith('Hello!');

        $response = $fake->respond('Hi');

        expect($response->content)->toBe('Hello!');
    });

    it('responds with sequence of responses', function () {
        $fake = FakeAgent::make()->respondWith(['First', 'Second', 'Third']);

        expect($fake->respond('1')->content)->toBe('First')
            ->and($fake->respond('2')->content)->toBe('Second')
            ->and($fake->respond('3')->content)->toBe('Third')
            ->and($fake->respond('4')->content)->toBe('Third'); // Repeats last
    });

    it('responds with closure', function () {
        $fake = FakeAgent::make()->respondWith(
            fn ($msg) => "You said: {$msg}"
        );

        $response = $fake->respond('Hello');

        expect($response->content)->toBe('You said: Hello');
    });

    it('tracks calls', function () {
        $fake = FakeAgent::make()->respondWith('OK');

        $fake->respond('First message');
        $fake->respond('Second message');

        expect($fake->getCalls())->toHaveCount(2)
            ->and($fake->getLastCall()['message'])->toBe('Second message');
    });

    it('asserts called', function () {
        $fake = FakeAgent::make()->respondWith('OK');

        expect(fn () => $fake->assertCalled())->toThrow(
            AssertionFailedError::class
        );

        $fake->respond('Hi');
        $fake->assertCalled(); // Should not throw
    });

    it('asserts called times', function () {
        $fake = FakeAgent::make()->respondWith('OK');
        $fake->respond('1');
        $fake->respond('2');

        $fake->assertCalledTimes(2);

        expect(fn () => $fake->assertCalledTimes(3))->toThrow(
            AssertionFailedError::class
        );
    });

    it('asserts received message', function () {
        $fake = FakeAgent::make()->respondWith('OK');
        $fake->respond('Hello world');

        $fake->assertReceivedMessage('Hello world');
        $fake->assertReceivedMessageContaining('world');

        expect(true)->toBeTrue(); // Assertion methods throw on failure
    });

    it('resets state', function () {
        $fake = FakeAgent::make()->respondWith(['First', 'Second']);
        $fake->respond('1');
        $fake->respond('2');

        $fake->reset();

        expect($fake->getCalls())->toBeEmpty()
            ->and($fake->respond('New')->content)->toBe('First');
    });
});

describe('FakeTool', function () {
    it('returns configured result', function () {
        $fake = FakeTool::make('my_tool')
            ->returns(['status' => 'success']);

        $result = $fake->execute(['input' => 'test']);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->result)->toBe(['status' => 'success']);
    });

    it('returns sequence of results', function () {
        $fake = FakeTool::make('counter')
            ->returns([
                ['count' => 1],
                ['count' => 2],
                ['count' => 3],
            ]);

        expect($fake->execute([])->result['count'])->toBe(1)
            ->and($fake->execute([])->result['count'])->toBe(2)
            ->and($fake->execute([])->result['count'])->toBe(3);
    });

    it('returns from closure', function () {
        $fake = FakeTool::make('echo')
            ->returns(fn ($args) => ['echo' => $args['text']]);

        $result = $fake->execute(['text' => 'Hello']);

        expect($result->result['echo'])->toBe('Hello');
    });

    it('can fail', function () {
        $fake = FakeTool::make('failing_tool')
            ->shouldFail('Something went wrong');

        $result = $fake->execute([]);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->error)->toBe('Something went wrong');
    });

    it('tracks calls', function () {
        $fake = FakeTool::make('tracker')->returns([]);

        $fake->execute(['a' => 1]);
        $fake->execute(['b' => 2]);

        expect($fake->getCalls())->toHaveCount(2)
            ->and($fake->getLastCallArguments())->toBe(['b' => 2]);
    });

    it('asserts called with arguments', function () {
        $fake = FakeTool::make('tool')->returns([]);
        $fake->execute(['key' => 'value']);

        $fake->assertCalledWith(['key' => 'value']);
        $fake->assertCalledWithKey('key');

        expect(true)->toBeTrue(); // Assertion methods throw on failure
    });
});

describe('FakeMemory', function () {
    it('stores and recalls values', function () {
        $memory = FakeMemory::make();

        $memory->store('user_name', 'John');
        $memory->store('user_age', 30);

        expect($memory->recall('user_name'))->toBe('John')
            ->and($memory->recall('user_age'))->toBe(30)
            ->and($memory->recall('nonexistent'))->toBeNull();
    });

    it('checks if key exists', function () {
        $memory = FakeMemory::make();
        $memory->store('exists', 'value');

        expect($memory->has('exists'))->toBeTrue()
            ->and($memory->has('missing'))->toBeFalse();
    });

    it('forgets keys', function () {
        $memory = FakeMemory::make();
        $memory->store('key', 'value');
        $memory->forget('key');

        expect($memory->has('key'))->toBeFalse();
    });

    it('clears all storage', function () {
        $memory = FakeMemory::make();
        $memory->store('key1', 'value1');
        $memory->store('key2', 'value2');

        $memory->clear();

        expect($memory->getKeys())->toBeEmpty();
    });

    it('searches values', function () {
        $memory = FakeMemory::make();
        $memory->store('greeting', 'Hello world');
        $memory->store('farewell', 'Goodbye');

        $results = $memory->search('world');

        expect($results)->toHaveCount(1)
            ->and($results->first()['key'])->toBe('greeting');
    });

    it('manages conversation history', function () {
        $memory = FakeMemory::make();

        $memory->addMessage(Message::user('Hello'));
        $memory->addMessage(Message::assistant('Hi there'));

        $history = $memory->getConversationHistory();

        expect($history)->toHaveCount(2);
    });

    it('asserts has key', function () {
        $memory = FakeMemory::make();
        $memory->store('exists', 'value');

        $memory->assertHas('exists');

        expect(fn () => $memory->assertHas('missing'))->toThrow(
            AssertionFailedError::class
        );
    });

    it('asserts stored value', function () {
        $memory = FakeMemory::make();
        $memory->store('key', 'expected');

        $memory->assertStored('key', 'expected');

        expect(fn () => $memory->assertStored('key', 'wrong'))->toThrow(
            AssertionFailedError::class
        );
    });

    it('asserts missing key', function () {
        $memory = FakeMemory::make();

        $memory->assertMissing('nonexistent');

        $memory->store('exists', 'value');
        expect(fn () => $memory->assertMissing('exists'))->toThrow(
            AssertionFailedError::class
        );
    });

    it('asserts count', function () {
        $memory = FakeMemory::make();
        $memory->store('a', 1);
        $memory->store('b', 2);

        $memory->assertCount(2);

        expect(true)->toBeTrue(); // Assertion methods throw on failure
    });

    it('asserts empty', function () {
        $memory = FakeMemory::make();

        $memory->assertEmpty();

        $memory->store('key', 'value');
        expect(fn () => $memory->assertEmpty())->toThrow(
            AssertionFailedError::class
        );
    });

    it('asserts message count', function () {
        $memory = FakeMemory::make();
        $memory->addMessage(Message::user('Test'));

        $memory->assertMessageCount(1);

        expect(true)->toBeTrue(); // Assertion methods throw on failure
    });

    it('asserts search finds results', function () {
        $memory = FakeMemory::make();
        $memory->store('doc', 'Contains searchable content');

        $memory->assertSearchFinds('searchable');

        expect(true)->toBeTrue(); // Assertion methods throw on failure
    });

    it('seeds with data', function () {
        $memory = FakeMemory::make()->seed([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        expect($memory->recall('key1'))->toBe('value1')
            ->and($memory->recall('key2'))->toBe('value2');
    });

    it('gets all stored values', function () {
        $memory = FakeMemory::make();
        $memory->store('a', 1);
        $memory->store('b', 2);

        $all = $memory->getAll();

        expect($all)->toBe(['a' => 1, 'b' => 2]);
    });
});

describe('FakeWorkflow', function () {
    it('succeeds with output', function () {
        $fake = FakeWorkflow::make()
            ->succeedsWith(['result' => 'done']);

        $result = $fake->run(['input' => 'test']);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getOutput())->toBe(['result' => 'done']);
    });

    it('fails with message', function () {
        $fake = FakeWorkflow::make()
            ->fails('Something went wrong', 'step-2');

        $result = $fake->run([]);

        expect($result->isFailed())->toBeTrue()
            ->and($result->error)->toBe('Something went wrong');
    });

    it('pauses at step', function () {
        $fake = FakeWorkflow::make()
            ->pausesAt('approval-step');

        $result = $fake->run([]);

        expect($result->isPaused())->toBeTrue();
    });

    it('tracks runs', function () {
        $fake = FakeWorkflow::make()->succeedsWith([]);

        $fake->run(['a' => 1]);
        $fake->run(['b' => 2]);

        expect($fake->getRuns())->toHaveCount(2)
            ->and($fake->getLastRunInput())->toBe(['b' => 2]);
    });

    it('asserts ran with input', function () {
        $fake = FakeWorkflow::make()->succeedsWith([]);
        $fake->run(['key' => 'value']);

        $fake->assertRan();
        $fake->assertRanWith(['key' => 'value']);
        $fake->assertRanWithKey('key');

        expect(true)->toBeTrue(); // Assertion methods throw on failure
    });

    it('returns result from closure', function () {
        $fake = FakeWorkflow::make()
            ->succeedsWith(fn ($input) => ['processed' => $input['data']]);

        $result = $fake->run(['data' => 'test']);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getOutput())->toBe(['processed' => 'test']);
    });
});
