<?php

declare(strict_types=1);

use AgenticOrchestrator\Contracts\MemoryInterface;
use AgenticOrchestrator\Conversations\Message;
use AgenticOrchestrator\Conversations\MessageRole;
use AgenticOrchestrator\Memory\Memory;
use Illuminate\Support\Collection;

function createMockMemoryDriver(): MemoryInterface
{
    return new class implements MemoryInterface
    {
        public array $stored = [];

        public array $forgotten = [];

        public array $messages = [];

        public bool $cleared = false;

        public function store(string $key, mixed $value, array $metadata = []): void
        {
            $this->stored[$key] = ['value' => $value, 'metadata' => $metadata];
        }

        public function recall(string $key): mixed
        {
            return $this->stored[$key]['value'] ?? null;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->stored);
        }

        public function search(string $query, int $limit = 5): Collection
        {
            return new Collection;
        }

        public function forget(string $key): void
        {
            $this->forgotten[] = $key;
            unset($this->stored[$key]);
        }

        public function clear(): void
        {
            $this->cleared = true;
            $this->stored = [];
        }

        public function getConversationHistory(int $limit = 50): array
        {
            return array_slice($this->messages, -$limit);
        }

        public function addMessage(Message $message): void
        {
            $this->messages[] = $message;
        }

        public function getDriver(): string
        {
            return 'mock';
        }

        public function getNamespace(): string
        {
            return 'mock-ns';
        }
    };
}

describe('Memory', function () {
    it('constructs with a driver and implements MemoryInterface', function () {
        $driver = createMockMemoryDriver();
        $memory = new Memory($driver);

        expect($memory)->toBeInstanceOf(Memory::class);
        expect($memory)->toBeInstanceOf(MemoryInterface::class);
    });

    it('has default namespace', function () {
        $driver = createMockMemoryDriver();
        $memory = new Memory($driver);

        expect($memory->getNamespace())->toBe('default');
    });

    it('creates immutable clone with new namespace', function () {
        $driver = createMockMemoryDriver();
        $memory = new Memory($driver);
        $scoped = $memory->forNamespace('agents');

        expect($memory->getNamespace())->toBe('default');
        expect($scoped->getNamespace())->toBe('agents');
        expect($scoped)->not->toBe($memory);
    });

    it('prefixes keys with namespace on store', function () {
        $driver = createMockMemoryDriver();
        $memory = new Memory($driver);

        $memory->store('test-key', 'test-value', ['extra' => 'data']);

        expect($driver->stored)->toHaveKey('default:test-key');
        expect($driver->stored['default:test-key']['value'])->toBe('test-value');
        expect($driver->stored['default:test-key']['metadata'])->toHaveKey('namespace');
        expect($driver->stored['default:test-key']['metadata']['namespace'])->toBe('default');
        expect($driver->stored['default:test-key']['metadata']['extra'])->toBe('data');
    });

    it('prefixes keys with custom namespace on store', function () {
        $driver = createMockMemoryDriver();
        $memory = (new Memory($driver))->forNamespace('custom');

        $memory->store('key', 'value');

        expect($driver->stored)->toHaveKey('custom:key');
        expect($driver->stored['custom:key']['metadata']['namespace'])->toBe('custom');
    });

    it('prefixes keys on recall', function () {
        $driver = createMockMemoryDriver();
        $driver->stored['default:my-key'] = ['value' => 'found-it', 'metadata' => []];
        $memory = new Memory($driver);

        expect($memory->recall('my-key'))->toBe('found-it');
    });

    it('prefixes keys on has', function () {
        $driver = createMockMemoryDriver();
        $driver->stored['default:exists'] = ['value' => true, 'metadata' => []];
        $memory = new Memory($driver);

        expect($memory->has('exists'))->toBeTrue();
        expect($memory->has('missing'))->toBeFalse();
    });

    it('delegates search to driver', function () {
        $driver = createMockMemoryDriver();
        $memory = new Memory($driver);

        $results = $memory->search('test query', 10);

        expect($results)->toBeInstanceOf(Collection::class);
    });

    it('prefixes keys on forget', function () {
        $driver = createMockMemoryDriver();
        $memory = new Memory($driver);

        $memory->forget('some-key');

        expect($driver->forgotten)->toContain('default:some-key');
    });

    it('delegates clear to driver', function () {
        $driver = createMockMemoryDriver();
        $memory = new Memory($driver);

        $memory->clear();

        expect($driver->cleared)->toBeTrue();
    });

    it('delegates getConversationHistory to driver', function () {
        $driver = createMockMemoryDriver();
        $message = new Message(role: MessageRole::User, content: 'Hello');
        $driver->messages[] = $message;

        $memory = new Memory($driver);
        $history = $memory->getConversationHistory(10);

        expect($history)->toHaveCount(1);
        expect($history[0])->toBe($message);
    });

    it('delegates addMessage to driver', function () {
        $driver = createMockMemoryDriver();
        $memory = new Memory($driver);
        $message = new Message(role: MessageRole::Assistant, content: 'Hi there');

        $memory->addMessage($message);

        expect($driver->messages)->toHaveCount(1);
        expect($driver->messages[0]->content)->toBe('Hi there');
    });

    it('delegates getDriver to driver', function () {
        $driver = createMockMemoryDriver();
        $memory = new Memory($driver);

        expect($memory->getDriver())->toBe('mock');
    });

    it('scopes different namespaces independently', function () {
        $driver = createMockMemoryDriver();
        $memory = new Memory($driver);

        $agentMemory = $memory->forNamespace('agent-1');
        $teamMemory = $memory->forNamespace('team-5');

        $agentMemory->store('key', 'agent-value');
        $teamMemory->store('key', 'team-value');

        expect($driver->stored)->toHaveKey('agent-1:key');
        expect($driver->stored)->toHaveKey('team-5:key');
        expect($driver->stored['agent-1:key']['value'])->toBe('agent-value');
        expect($driver->stored['team-5:key']['value'])->toBe('team-value');
    });
});
