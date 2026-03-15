<?php

declare(strict_types=1);

use AgenticOrchestrator\Conversations\Message;
use AgenticOrchestrator\Conversations\MessageRole;
use AgenticOrchestrator\Memory\Drivers\DatabaseDriver;
use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\MultiTenancy\TenantManager;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

describe('DatabaseDriver', function () {
    describe('constructor', function () {
        it('creates with default config', function () {
            $driver = new DatabaseDriver;

            expect($driver->getNamespace())->toBe('default')
                ->and($driver->getDriver())->toBe('database');
        });

        it('creates with custom config', function () {
            $driver = new DatabaseDriver([
                'connection' => 'mysql',
                'table' => 'custom_memories',
            ]);

            expect($driver)->toBeInstanceOf(DatabaseDriver::class);
        });
    });

    describe('namespace management', function () {
        it('sets and gets namespace', function () {
            $driver = new DatabaseDriver;

            $result = $driver->setNamespace('custom');

            expect($result)->toBe($driver)
                ->and($driver->getNamespace())->toBe('custom');
        });

        it('defaults to "default" namespace', function () {
            $driver = new DatabaseDriver;

            expect($driver->getNamespace())->toBe('default');
        });
    });

    describe('tenant management', function () {
        it('sets tenant manager fluently', function () {
            $driver = new DatabaseDriver;
            $manager = Mockery::mock(TenantManager::class);

            $result = $driver->setTenantManager($manager);

            expect($result)->toBe($driver);
        });

        it('creates clone with forTenant', function () {
            $driver = new DatabaseDriver;
            $tenant = Mockery::mock(TenantInterface::class);

            $cloned = $driver->forTenant($tenant);

            expect($cloned)->toBeInstanceOf(DatabaseDriver::class)
                ->and($cloned)->not->toBe($driver);
        });
    });

    describe('getDriver', function () {
        it('returns "database"', function () {
            $driver = new DatabaseDriver;

            expect($driver->getDriver())->toBe('database');
        });
    });

    describe('store', function () {
        it('inserts new record when key does not exist', function () {
            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('first')->andReturnNull();
            $builder->shouldReceive('insert')->once();

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = new DatabaseDriver;
            $driver->store('test_key', 'test_value');
        });

        it('updates existing record when key exists', function () {
            $existing = (object) ['id' => 1];
            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('first')->andReturn($existing);
            $builder->shouldReceive('update')->once();

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = new DatabaseDriver;
            $driver->store('test_key', 'updated_value');
        });

        it('serializes array values to JSON', function () {
            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('first')->andReturnNull();
            $builder->shouldReceive('insert')->once()->withArgs(function ($data) {
                return $data['content'] === json_encode(['foo' => 'bar']);
            });

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = new DatabaseDriver;
            $driver->store('test_key', ['foo' => 'bar']);
        });

        it('stores string values directly', function () {
            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('first')->andReturnNull();
            $builder->shouldReceive('insert')->once()->withArgs(function ($data) {
                return $data['content'] === 'hello world';
            });

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = new DatabaseDriver;
            $driver->store('test_key', 'hello world');
        });

        it('includes metadata fields in stored data', function () {
            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('first')->andReturnNull();
            $builder->shouldReceive('insert')->once()->withArgs(function ($data) {
                return $data['agent_name'] === 'test-agent'
                    && $data['user_id'] === 'user-1'
                    && $data['session_id'] === 'sess-1'
                    && $data['type'] === 'conversation'
                    && $data['importance'] === 0.9;
            });

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = new DatabaseDriver;
            $driver->store('test_key', 'value', [
                'type' => 'conversation',
                'importance' => 0.9,
                'agent_name' => 'test-agent',
                'user_id' => 'user-1',
                'session_id' => 'sess-1',
            ]);
        });

        it('includes expires_at when provided', function () {
            $expiresAt = now()->addHour();
            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('first')->andReturnNull();
            $builder->shouldReceive('insert')->once()->withArgs(function ($data) use ($expiresAt) {
                return $data['expires_at'] === $expiresAt;
            });

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = new DatabaseDriver;
            $driver->store('test_key', 'value', ['expires_at' => $expiresAt]);
        });

        it('includes tenant information when tenant is set', function () {
            $tenant = Mockery::mock(TenantInterface::class);
            $model = new stdClass;
            $tenant->shouldReceive('getModel')->andReturn($model);
            $tenant->shouldReceive('getTenantKey')->andReturn('tenant-123');

            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('first')->andReturnNull();
            $builder->shouldReceive('insert')->once()->withArgs(function ($data) {
                return $data['tenant_type'] === 'stdClass'
                    && $data['tenant_id'] === 'tenant-123';
            });

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = (new DatabaseDriver)->forTenant($tenant);
            $driver->store('test_key', 'value');
        });
    });

    describe('recall', function () {
        it('returns null when key not found', function () {
            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('first')->andReturnNull();

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = new DatabaseDriver;
            $result = $driver->recall('missing_key');

            expect($result)->toBeNull();
        });

        it('returns decoded JSON when content is valid JSON', function () {
            $record = (object) [
                'id' => 1,
                'content' => json_encode(['foo' => 'bar']),
            ];

            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('first')->andReturn($record);
            $builder->shouldReceive('update')->once();

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);
            DB::shouldReceive('raw')->once()->andReturn('access_count + 1');

            $driver = new DatabaseDriver;
            $result = $driver->recall('test_key');

            expect($result)->toBe(['foo' => 'bar']);
        });

        it('returns string when content is not valid JSON', function () {
            $record = (object) [
                'id' => 1,
                'content' => 'plain text value',
            ];

            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('first')->andReturn($record);
            $builder->shouldReceive('update')->once();

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);
            DB::shouldReceive('raw')->once()->andReturn('access_count + 1');

            $driver = new DatabaseDriver;
            $result = $driver->recall('test_key');

            expect($result)->toBe('plain text value');
        });
    });

    describe('has', function () {
        it('returns true when key exists', function () {
            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('exists')->andReturn(true);

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = new DatabaseDriver;

            expect($driver->has('existing_key'))->toBeTrue();
        });

        it('returns false when key does not exist', function () {
            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('exists')->andReturn(false);

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = new DatabaseDriver;

            expect($driver->has('missing_key'))->toBeFalse();
        });
    });

    describe('forget', function () {
        it('deletes a specific key', function () {
            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('delete')->once();

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = new DatabaseDriver;
            $driver->forget('test_key');
        });
    });

    describe('clear', function () {
        it('deletes all records in namespace', function () {
            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('delete')->once();

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = new DatabaseDriver;
            $driver->clear();
        });
    });

    describe('keys', function () {
        it('returns array of keys in namespace', function () {
            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('pluck')->with('key')->andReturn(collect(['key1', 'key2']));

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = new DatabaseDriver;
            $result = $driver->keys();

            expect($result)->toBe(['key1', 'key2']);
        });
    });

    describe('all', function () {
        it('returns all memories in namespace', function () {
            $records = collect([
                (object) ['key' => 'k1', 'content' => json_encode(['a' => 1])],
                (object) ['key' => 'k2', 'content' => 'plain text'],
            ]);

            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('get')->andReturn($records);

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = new DatabaseDriver;
            $result = $driver->all();

            expect($result)->toHaveKey('k1')
                ->and($result['k1'])->toBe(['a' => 1])
                ->and($result)->toHaveKey('k2')
                ->and($result['k2'])->toBe('plain text');
        });
    });

    describe('cleanup', function () {
        it('deletes expired records and returns count', function () {
            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('whereNotNull')->with('expires_at')->andReturnSelf();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('delete')->once()->andReturn(5);

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = new DatabaseDriver;
            $result = $driver->cleanup();

            expect($result)->toBe(5);
        });
    });

    describe('search', function () {
        it('searches memories by pattern', function () {
            $records = collect([
                (object) ['key' => 'k1', 'content' => json_encode(['foo' => 'bar']), 'importance' => 0.9, 'metadata' => json_encode(['type' => 'general'])],
            ]);

            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('orderByDesc')->andReturnSelf();
            $builder->shouldReceive('limit')->andReturnSelf();
            $builder->shouldReceive('get')->andReturn($records);

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = new DatabaseDriver;
            $result = $driver->search('foo');

            expect($result)->toHaveCount(1)
                ->and($result->first()['key'])->toBe('k1');
        });
    });

    describe('getConversationHistory', function () {
        it('returns messages from history', function () {
            $records = collect([
                (object) ['content' => 'Hello', 'metadata' => json_encode(['role' => 'user'])],
                (object) ['content' => 'Hi there', 'metadata' => json_encode(['role' => 'assistant'])],
            ]);

            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('orderBy')->andReturnSelf();
            $builder->shouldReceive('limit')->andReturnSelf();
            $builder->shouldReceive('get')->andReturn($records);

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = new DatabaseDriver;
            $result = $driver->getConversationHistory();

            expect($result)->toHaveCount(2)
                ->and($result[0])->toBeInstanceOf(Message::class)
                ->and($result[0]->content)->toBe('Hello')
                ->and($result[1]->content)->toBe('Hi there');
        });
    });

    describe('addMessage', function () {
        it('stores a message via store method', function () {
            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('first')->andReturnNull();
            $builder->shouldReceive('insert')->once()->withArgs(function ($data) {
                return $data['type'] === 'message'
                    && $data['content'] === 'Hello world';
            });

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $message = new Message(
                role: MessageRole::User,
                content: 'Hello world',
            );

            $driver = new DatabaseDriver;
            $driver->addMessage($message);
        });
    });

    describe('custom connection', function () {
        it('uses custom database connection when configured', function () {
            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('pluck')->with('key')->andReturn(collect(['k1']));

            $connection = Mockery::mock(ConnectionInterface::class);
            $connection->shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            DB::shouldReceive('connection')
                ->with('custom')
                ->andReturn($connection);

            $driver = new DatabaseDriver(['connection' => 'custom']);
            $result = $driver->keys();

            expect($result)->toBe(['k1']);
        });
    });

    describe('getCurrentTenant', function () {
        it('returns explicit tenant when set via forTenant', function () {
            $tenant = Mockery::mock(TenantInterface::class);
            $model = new stdClass;
            $tenant->shouldReceive('getModel')->andReturn($model);
            $tenant->shouldReceive('getTenantKey')->andReturn('t-1');

            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('first')->andReturnNull();
            $builder->shouldReceive('insert')->once()->withArgs(function ($data) {
                return $data['tenant_id'] === 't-1';
            });

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = (new DatabaseDriver)->forTenant($tenant);
            $driver->store('key', 'value');
        });

        it('returns tenant from manager when no explicit tenant', function () {
            $tenant = Mockery::mock(TenantInterface::class);
            $model = new stdClass;
            $tenant->shouldReceive('getModel')->andReturn($model);
            $tenant->shouldReceive('getTenantKey')->andReturn('mgr-t-1');

            $manager = Mockery::mock(TenantManager::class);
            $manager->shouldReceive('current')->andReturn($tenant);

            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('first')->andReturnNull();
            $builder->shouldReceive('insert')->once()->withArgs(function ($data) {
                return $data['tenant_id'] === 'mgr-t-1';
            });

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = new DatabaseDriver;
            $driver->setTenantManager($manager);
            $driver->store('key', 'value');
        });

        it('returns null when no tenant or manager', function () {
            $builder = Mockery::mock(Builder::class)->shouldIgnoreMissing();
            $builder->shouldReceive('where')->andReturnSelf();
            $builder->shouldReceive('first')->andReturnNull();
            $builder->shouldReceive('insert')->once()->withArgs(function ($data) {
                return ! isset($data['tenant_type']) && ! isset($data['tenant_id']);
            });

            DB::shouldReceive('table')
                ->with('agent_memories')
                ->andReturn($builder);

            $driver = new DatabaseDriver;
            $driver->store('key', 'value');
        });
    });
});
