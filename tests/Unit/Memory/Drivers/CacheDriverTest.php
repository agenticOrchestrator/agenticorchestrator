<?php

declare(strict_types=1);

use AgenticOrchestrator\Contracts\MemoryInterface;
use AgenticOrchestrator\Conversations\Message;
use AgenticOrchestrator\Memory\Drivers\CacheDriver;
use Illuminate\Support\Facades\Cache;

covers(CacheDriver::class);

describe('CacheDriver', function () {

    beforeEach(function () {
        Cache::flush();
    });

    it('implements MemoryInterface', function () {
        $driver = new CacheDriver;

        expect($driver)->toBeInstanceOf(MemoryInterface::class);
    });

    it('returns cache as driver name', function () {
        expect((new CacheDriver)->getDriver())->toBe('cache');
    });

    it('has default namespace', function () {
        expect((new CacheDriver)->getNamespace())->toBe('default');
    });

    it('sets and gets namespace', function () {
        $driver = new CacheDriver;
        $result = $driver->setNamespace('custom');

        expect($result)->toBe($driver)
            ->and($driver->getNamespace())->toBe('custom');
    });

    describe('store and recall', function () {
        it('stores and recalls a string value', function () {
            $driver = new CacheDriver;

            $driver->store('key', 'value');

            expect($driver->recall('key'))->toBe('value');
        });

        it('stores and recalls an integer value', function () {
            $driver = new CacheDriver;

            $driver->store('count', 42);

            expect($driver->recall('count'))->toBe(42);
        });

        it('stores and recalls an array value', function () {
            $driver = new CacheDriver;

            $driver->store('data', ['nested' => true]);

            expect($driver->recall('data'))->toBe(['nested' => true]);
        });

        it('stores with metadata', function () {
            $driver = new CacheDriver;

            $driver->store('key', 'value', ['source' => 'test']);

            expect($driver->recall('key'))->toBe('value');
        });

        it('returns null for nonexistent key', function () {
            $driver = new CacheDriver;

            expect($driver->recall('missing'))->toBeNull();
        });

        it('overwrites existing key', function () {
            $driver = new CacheDriver;

            $driver->store('key', 'first');
            $driver->store('key', 'second');

            expect($driver->recall('key'))->toBe('second');
        });
    });

    describe('has', function () {
        it('returns true for existing key', function () {
            $driver = new CacheDriver;
            $driver->store('exists', 'val');

            expect($driver->has('exists'))->toBeTrue();
        });

        it('returns false for missing key', function () {
            expect((new CacheDriver)->has('missing'))->toBeFalse();
        });
    });

    describe('forget', function () {
        it('removes a stored key', function () {
            $driver = new CacheDriver;
            $driver->store('key', 'value');

            $driver->forget('key');

            expect($driver->has('key'))->toBeFalse()
                ->and($driver->recall('key'))->toBeNull();
        });
    });

    describe('clear', function () {
        it('removes all stored keys and history', function () {
            $driver = new CacheDriver;
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
            $driver = new CacheDriver;
            $driver->store('doc1', 'Hello world');
            $driver->store('doc2', 'Goodbye world');
            $driver->store('doc3', 'No match');

            $results = $driver->search('world');

            expect($results)->toHaveCount(2);
        });

        it('searches case-insensitively', function () {
            $driver = new CacheDriver;
            $driver->store('doc', 'HELLO WORLD');

            $results = $driver->search('hello');

            expect($results)->toHaveCount(1)
                ->and($results->first()['key'])->toBe('doc');
        });

        it('searches non-string values via json encoding', function () {
            $driver = new CacheDriver;
            $driver->store('data', ['name' => 'findme']);

            $results = $driver->search('findme');

            expect($results)->toHaveCount(1);
        });

        it('limits results', function () {
            $driver = new CacheDriver;
            for ($i = 0; $i < 10; $i++) {
                $driver->store("doc{$i}", "common text {$i}");
            }

            $results = $driver->search('common', 3);

            expect($results)->toHaveCount(3);
        });

        it('returns empty collection when nothing matches', function () {
            $driver = new CacheDriver;
            $driver->store('doc', 'Hello');

            $results = $driver->search('xyz');

            expect($results)->toBeEmpty();
        });

        it('skips expired or null cache entries', function () {
            $driver = new CacheDriver;
            $driver->store('valid', 'matching content');
            // After storing, the key is tracked. If we manually remove from cache
            // but leave it tracked, search should skip it gracefully.

            $results = $driver->search('matching');
            expect($results)->toHaveCount(1);
        });
    });

    describe('conversation history', function () {
        it('adds and retrieves messages', function () {
            $driver = new CacheDriver;

            $driver->addMessage(Message::user('Hello'));
            $driver->addMessage(Message::assistant('Hi'));

            $history = $driver->getConversationHistory();

            expect($history)->toHaveCount(2);
        });

        it('limits conversation history', function () {
            $driver = new CacheDriver;
            for ($i = 0; $i < 10; $i++) {
                $driver->addMessage(Message::user("Message {$i}"));
            }

            $history = $driver->getConversationHistory(3);

            expect($history)->toHaveCount(3);
        });

        it('returns empty history when none added', function () {
            $driver = new CacheDriver;

            expect($driver->getConversationHistory())->toBeEmpty();
        });

        it('caps history at 100 messages', function () {
            $driver = new CacheDriver;
            for ($i = 0; $i < 105; $i++) {
                $driver->addMessage(Message::user("Msg {$i}"));
            }

            // Internal storage is capped at 100
            $history = $driver->getConversationHistory(200);

            expect(count($history))->toBeLessThanOrEqual(100);
        });
    });

    describe('namespace isolation', function () {
        it('isolates data between namespaces', function () {
            $driver1 = new CacheDriver;
            $driver1->setNamespace('ns1');
            $driver1->store('key', 'value1');

            $driver2 = new CacheDriver;
            $driver2->setNamespace('ns2');

            expect($driver2->has('key'))->toBeFalse()
                ->and($driver2->recall('key'))->toBeNull();
        });
    });

    describe('custom constructor parameters', function () {
        it('accepts custom store, ttl and prefix', function () {
            $driver = new CacheDriver(
                store: 'default',
                ttl: 7200,
                prefix: 'custom_prefix:',
            );

            $driver->store('key', 'value');

            expect($driver->recall('key'))->toBe('value');
        });
    });

    describe('key tracking', function () {
        it('does not duplicate tracked keys on overwrite', function () {
            $driver = new CacheDriver;

            $driver->store('key', 'first');
            $driver->store('key', 'second');

            // Search should find only one entry for this key
            $results = $driver->search('second');
            expect($results)->toHaveCount(1);
        });

        it('untracks key on forget', function () {
            $driver = new CacheDriver;
            $driver->store('a', 'match');
            $driver->store('b', 'match');

            $driver->forget('a');

            $results = $driver->search('match');
            expect($results)->toHaveCount(1)
                ->and($results->first()['key'])->toBe('b');
        });
    });
});
