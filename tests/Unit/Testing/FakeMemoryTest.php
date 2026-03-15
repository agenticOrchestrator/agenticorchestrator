<?php

declare(strict_types=1);

use AgenticOrchestrator\Contracts\MemoryInterface;
use AgenticOrchestrator\Conversations\Message;
use AgenticOrchestrator\Testing\FakeMemory;
use PHPUnit\Framework\AssertionFailedError;

covers(FakeMemory::class);

describe('FakeMemory', function () {

    it('creates via static make method', function () {
        $memory = FakeMemory::make();

        expect($memory)->toBeInstanceOf(FakeMemory::class)
            ->and($memory)->toBeInstanceOf(MemoryInterface::class);
    });

    it('returns fake driver name', function () {
        expect(FakeMemory::make()->getDriver())->toBe('fake');
    });

    it('has default namespace', function () {
        expect(FakeMemory::make()->getNamespace())->toBe('fake');
    });

    it('sets and gets namespace', function () {
        $memory = FakeMemory::make();
        $result = $memory->setNamespace('custom');

        expect($result)->toBe($memory) // fluent
            ->and($memory->getNamespace())->toBe('custom');
    });

    describe('store and recall', function () {
        it('stores and recalls a string value', function () {
            $memory = FakeMemory::make();

            $memory->store('key', 'value');

            expect($memory->recall('key'))->toBe('value');
        });

        it('stores and recalls an integer value', function () {
            $memory = FakeMemory::make();

            $memory->store('count', 42);

            expect($memory->recall('count'))->toBe(42);
        });

        it('stores and recalls an array value', function () {
            $memory = FakeMemory::make();

            $memory->store('data', ['nested' => ['deep' => true]]);

            expect($memory->recall('data'))->toBe(['nested' => ['deep' => true]]);
        });

        it('stores with metadata', function () {
            $memory = FakeMemory::make();

            $memory->store('key', 'value', ['source' => 'test', 'score' => 0.95]);

            expect($memory->recall('key'))->toBe('value');
        });

        it('returns null for nonexistent key', function () {
            $memory = FakeMemory::make();

            expect($memory->recall('nonexistent'))->toBeNull();
        });

        it('overwrites existing key', function () {
            $memory = FakeMemory::make();

            $memory->store('key', 'first');
            $memory->store('key', 'second');

            expect($memory->recall('key'))->toBe('second');
        });
    });

    describe('has', function () {
        it('returns true for existing key', function () {
            $memory = FakeMemory::make();
            $memory->store('exists', 'val');

            expect($memory->has('exists'))->toBeTrue();
        });

        it('returns false for missing key', function () {
            expect(FakeMemory::make()->has('missing'))->toBeFalse();
        });
    });

    describe('forget', function () {
        it('removes a key', function () {
            $memory = FakeMemory::make();
            $memory->store('key', 'value');

            $memory->forget('key');

            expect($memory->has('key'))->toBeFalse()
                ->and($memory->recall('key'))->toBeNull();
        });

        it('does not throw when forgetting nonexistent key', function () {
            $memory = FakeMemory::make();

            $memory->forget('nonexistent');

            expect(true)->toBeTrue();
        });
    });

    describe('clear', function () {
        it('removes all storage and messages', function () {
            $memory = FakeMemory::make();
            $memory->store('a', 1);
            $memory->store('b', 2);
            $memory->addMessage(Message::user('Hello'));

            $memory->clear();

            expect($memory->getKeys())->toBeEmpty()
                ->and($memory->getMessages())->toBeEmpty();
        });
    });

    describe('search', function () {
        it('finds string values matching query', function () {
            $memory = FakeMemory::make();
            $memory->store('doc1', 'Hello world');
            $memory->store('doc2', 'Goodbye world');
            $memory->store('doc3', 'No match');

            $results = $memory->search('world');

            expect($results)->toHaveCount(2);
        });

        it('searches case-insensitively', function () {
            $memory = FakeMemory::make();
            $memory->store('doc', 'HELLO WORLD');

            $results = $memory->search('hello');

            expect($results)->toHaveCount(1)
                ->and($results->first()['key'])->toBe('doc');
        });

        it('searches non-string values via json encoding', function () {
            $memory = FakeMemory::make();
            $memory->store('data', ['name' => 'searchterm']);

            $results = $memory->search('searchterm');

            expect($results)->toHaveCount(1)
                ->and($results->first()['key'])->toBe('data');
        });

        it('returns results with correct structure', function () {
            $memory = FakeMemory::make();
            $memory->store('key', 'matching content', ['meta' => 'info']);

            $results = $memory->search('matching');

            $result = $results->first();
            expect($result['key'])->toBe('key')
                ->and($result['content'])->toBe('matching content')
                ->and($result['score'])->toBe(1.0)
                ->and($result['metadata'])->toBe(['meta' => 'info']);
        });

        it('limits results', function () {
            $memory = FakeMemory::make();
            for ($i = 0; $i < 10; $i++) {
                $memory->store("doc{$i}", "common content {$i}");
            }

            $results = $memory->search('common', 3);

            expect($results)->toHaveCount(3);
        });

        it('returns empty collection when nothing matches', function () {
            $memory = FakeMemory::make();
            $memory->store('doc', 'Hello');

            $results = $memory->search('xyz');

            expect($results)->toBeEmpty();
        });
    });

    describe('conversation history', function () {
        it('adds and retrieves messages', function () {
            $memory = FakeMemory::make();

            $memory->addMessage(Message::user('Hello'));
            $memory->addMessage(Message::assistant('Hi there'));

            $history = $memory->getConversationHistory();

            expect($history)->toHaveCount(2);
        });

        it('limits conversation history', function () {
            $memory = FakeMemory::make();
            for ($i = 0; $i < 10; $i++) {
                $memory->addMessage(Message::user("Message {$i}"));
            }

            $history = $memory->getConversationHistory(3);

            expect($history)->toHaveCount(3);
        });

        it('returns all messages via getMessages()', function () {
            $memory = FakeMemory::make();
            $memory->addMessage(Message::user('A'));
            $memory->addMessage(Message::user('B'));

            expect($memory->getMessages())->toHaveCount(2);
        });
    });

    describe('getKeys and getAll', function () {
        it('returns all stored keys', function () {
            $memory = FakeMemory::make();
            $memory->store('alpha', 'a');
            $memory->store('beta', 'b');

            expect($memory->getKeys())->toBe(['alpha', 'beta']);
        });

        it('returns empty keys for empty memory', function () {
            expect(FakeMemory::make()->getKeys())->toBeEmpty();
        });

        it('returns all values keyed by name', function () {
            $memory = FakeMemory::make();
            $memory->store('x', 10);
            $memory->store('y', 20);

            expect($memory->getAll())->toBe(['x' => 10, 'y' => 20]);
        });
    });

    describe('seed', function () {
        it('seeds multiple values at once', function () {
            $memory = FakeMemory::make()->seed([
                'name' => 'John',
                'age' => 30,
                'active' => true,
            ]);

            expect($memory)->toBeInstanceOf(FakeMemory::class) // fluent
                ->and($memory->recall('name'))->toBe('John')
                ->and($memory->recall('age'))->toBe(30)
                ->and($memory->recall('active'))->toBeTrue();
        });

        it('seeds empty data without error', function () {
            $memory = FakeMemory::make()->seed([]);

            expect($memory->getKeys())->toBeEmpty();
        });
    });

    describe('reset', function () {
        it('clears all storage and messages', function () {
            $memory = FakeMemory::make();
            $memory->store('key', 'value');
            $memory->addMessage(Message::user('Test'));

            $result = $memory->reset();

            expect($result)->toBe($memory) // fluent
                ->and($memory->getKeys())->toBeEmpty()
                ->and($memory->getMessages())->toBeEmpty();
        });
    });

    describe('assertions', function () {
        it('assertHas passes for existing key', function () {
            $memory = FakeMemory::make();
            $memory->store('exists', 'val');

            $memory->assertHas('exists');
            expect(true)->toBeTrue();
        });

        it('assertHas throws for missing key', function () {
            $memory = FakeMemory::make();

            expect(fn () => $memory->assertHas('missing'))->toThrow(
                AssertionFailedError::class,
                'Expected memory to have key "missing", but it did not.'
            );
        });

        it('assertStored passes for correct value', function () {
            $memory = FakeMemory::make();
            $memory->store('key', 'expected');

            $memory->assertStored('key', 'expected');
            expect(true)->toBeTrue();
        });

        it('assertStored throws for wrong value', function () {
            $memory = FakeMemory::make();
            $memory->store('key', 'actual');

            expect(fn () => $memory->assertStored('key', 'wrong'))->toThrow(
                AssertionFailedError::class,
            );
        });

        it('assertStored throws for missing key', function () {
            $memory = FakeMemory::make();

            expect(fn () => $memory->assertStored('missing', 'val'))->toThrow(
                AssertionFailedError::class,
            );
        });

        it('assertMissing passes for nonexistent key', function () {
            $memory = FakeMemory::make();

            $memory->assertMissing('nonexistent');
            expect(true)->toBeTrue();
        });

        it('assertMissing throws for existing key', function () {
            $memory = FakeMemory::make();
            $memory->store('exists', 'val');

            expect(fn () => $memory->assertMissing('exists'))->toThrow(
                AssertionFailedError::class,
                'Expected memory to not have key "exists", but it did.'
            );
        });

        it('assertCount passes for correct count', function () {
            $memory = FakeMemory::make();
            $memory->store('a', 1);
            $memory->store('b', 2);

            $memory->assertCount(2);
            expect(true)->toBeTrue();
        });

        it('assertCount throws for wrong count', function () {
            $memory = FakeMemory::make();
            $memory->store('a', 1);

            expect(fn () => $memory->assertCount(5))->toThrow(
                AssertionFailedError::class,
                'Expected memory to have 5 item(s), but it has 1.'
            );
        });

        it('assertEmpty passes for empty memory', function () {
            $memory = FakeMemory::make();

            $memory->assertEmpty();
            expect(true)->toBeTrue();
        });

        it('assertEmpty throws for non-empty memory', function () {
            $memory = FakeMemory::make();
            $memory->store('key', 'val');

            expect(fn () => $memory->assertEmpty())->toThrow(
                AssertionFailedError::class,
                'Expected memory to be empty, but it has 1 item(s).'
            );
        });

        it('assertMessageCount passes for correct count', function () {
            $memory = FakeMemory::make();
            $memory->addMessage(Message::user('A'));
            $memory->addMessage(Message::user('B'));

            $memory->assertMessageCount(2);
            expect(true)->toBeTrue();
        });

        it('assertMessageCount throws for wrong count', function () {
            $memory = FakeMemory::make();

            expect(fn () => $memory->assertMessageCount(3))->toThrow(
                AssertionFailedError::class,
                'Expected 3 message(s), but have 0.'
            );
        });

        it('assertSearchFinds passes when results found', function () {
            $memory = FakeMemory::make();
            $memory->store('doc', 'searchable content');

            $memory->assertSearchFinds('searchable');
            expect(true)->toBeTrue();
        });

        it('assertSearchFinds with minimum results', function () {
            $memory = FakeMemory::make();
            $memory->store('doc1', 'matching text');
            $memory->store('doc2', 'matching text too');

            $memory->assertSearchFinds('matching', 2);
            expect(true)->toBeTrue();
        });

        it('assertSearchFinds throws when insufficient results', function () {
            $memory = FakeMemory::make();
            $memory->store('doc', 'content');

            expect(fn () => $memory->assertSearchFinds('content', 5))->toThrow(
                AssertionFailedError::class,
            );
        });

        it('assertSearchFinds throws when no results', function () {
            $memory = FakeMemory::make();

            expect(fn () => $memory->assertSearchFinds('nothing'))->toThrow(
                AssertionFailedError::class,
            );
        });
    });
});
