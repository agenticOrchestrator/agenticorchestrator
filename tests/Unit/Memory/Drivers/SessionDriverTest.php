<?php

declare(strict_types=1);

use AgenticOrchestrator\Contracts\MemoryInterface;
use AgenticOrchestrator\Conversations\Message;
use AgenticOrchestrator\Memory\Drivers\SessionDriver;

covers(SessionDriver::class);

describe('SessionDriver', function () {

    it('implements MemoryInterface', function () {
        $driver = new SessionDriver;

        expect($driver)->toBeInstanceOf(MemoryInterface::class);
    });

    it('returns session as driver name', function () {
        expect((new SessionDriver)->getDriver())->toBe('session');
    });

    it('returns session as namespace', function () {
        expect((new SessionDriver)->getNamespace())->toBe('session');
    });

    describe('store and recall', function () {
        it('stores and recalls a string value', function () {
            $driver = new SessionDriver;

            $driver->store('key', 'value');

            expect($driver->recall('key'))->toBe('value');
        });

        it('stores and recalls an integer value', function () {
            $driver = new SessionDriver;

            $driver->store('count', 42);

            expect($driver->recall('count'))->toBe(42);
        });

        it('stores and recalls an array value', function () {
            $driver = new SessionDriver;

            $driver->store('data', ['nested' => true]);

            expect($driver->recall('data'))->toBe(['nested' => true]);
        });

        it('stores with metadata', function () {
            $driver = new SessionDriver;

            $driver->store('key', 'value', ['source' => 'test']);

            expect($driver->recall('key'))->toBe('value');
        });

        it('returns null for nonexistent key', function () {
            $driver = new SessionDriver;

            expect($driver->recall('missing'))->toBeNull();
        });

        it('overwrites existing key', function () {
            $driver = new SessionDriver;

            $driver->store('key', 'first');
            $driver->store('key', 'second');

            expect($driver->recall('key'))->toBe('second');
        });
    });

    describe('has', function () {
        it('returns true for existing key', function () {
            $driver = new SessionDriver;
            $driver->store('exists', 'val');

            expect($driver->has('exists'))->toBeTrue();
        });

        it('returns false for missing key', function () {
            expect((new SessionDriver)->has('missing'))->toBeFalse();
        });
    });

    describe('forget', function () {
        it('removes a stored key', function () {
            $driver = new SessionDriver;
            $driver->store('key', 'value');

            $driver->forget('key');

            expect($driver->has('key'))->toBeFalse()
                ->and($driver->recall('key'))->toBeNull();
        });
    });

    describe('clear', function () {
        it('removes all storage and history', function () {
            $driver = new SessionDriver;
            $driver->store('a', 1);
            $driver->store('b', 2);
            $driver->addMessage(Message::user('Hello'));

            $driver->clear();

            expect($driver->has('a'))->toBeFalse()
                ->and($driver->has('b'))->toBeFalse()
                ->and($driver->getConversationHistory())->toBeEmpty();
        });
    });

    describe('search', function () {
        it('finds string values matching query', function () {
            $driver = new SessionDriver;
            $driver->store('doc1', 'Hello world');
            $driver->store('doc2', 'Goodbye world');
            $driver->store('doc3', 'No match');

            $results = $driver->search('world');

            expect($results)->toHaveCount(2);
        });

        it('searches case-insensitively', function () {
            $driver = new SessionDriver;
            $driver->store('doc', 'HELLO WORLD');

            $results = $driver->search('hello');

            expect($results)->toHaveCount(1)
                ->and($results->first()['key'])->toBe('doc');
        });

        it('searches non-string values via json encoding', function () {
            $driver = new SessionDriver;
            $driver->store('data', ['name' => 'findme']);

            $results = $driver->search('findme');

            expect($results)->toHaveCount(1)
                ->and($results->first()['key'])->toBe('data');
        });

        it('returns results with correct structure', function () {
            $driver = new SessionDriver;
            $driver->store('key', 'matching text', ['meta' => 'info']);

            $results = $driver->search('matching');
            $result = $results->first();

            expect($result['key'])->toBe('key')
                ->and($result['content'])->toBe('matching text')
                ->and($result['score'])->toBe(1.0)
                ->and($result['metadata'])->toBe(['meta' => 'info']);
        });

        it('limits results', function () {
            $driver = new SessionDriver;
            for ($i = 0; $i < 10; $i++) {
                $driver->store("doc{$i}", "common text {$i}");
            }

            $results = $driver->search('common', 3);

            expect($results)->toHaveCount(3);
        });

        it('returns empty collection when nothing matches', function () {
            $driver = new SessionDriver;
            $driver->store('doc', 'Hello');

            $results = $driver->search('xyz');

            expect($results)->toBeEmpty();
        });
    });

    describe('conversation history', function () {
        it('adds and retrieves messages', function () {
            $driver = new SessionDriver;

            $driver->addMessage(Message::user('Hello'));
            $driver->addMessage(Message::assistant('Hi'));

            $history = $driver->getConversationHistory();

            expect($history)->toHaveCount(2);
        });

        it('limits conversation history to requested amount', function () {
            $driver = new SessionDriver;
            for ($i = 0; $i < 10; $i++) {
                $driver->addMessage(Message::user("Message {$i}"));
            }

            $history = $driver->getConversationHistory(3);

            expect($history)->toHaveCount(3);
        });

        it('returns most recent messages when limited', function () {
            $driver = new SessionDriver;
            $driver->addMessage(Message::user('Old'));
            $driver->addMessage(Message::user('Middle'));
            $driver->addMessage(Message::user('New'));

            $history = $driver->getConversationHistory(2);

            expect($history)->toHaveCount(2)
                ->and($history[0]->content)->toBe('Middle')
                ->and($history[1]->content)->toBe('New');
        });
    });
});
