<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Memory\Drivers;

use AgenticOrchestrator\Contracts\MemoryInterface;
use AgenticOrchestrator\Conversations\Message;
use AgenticOrchestrator\Conversations\MessageRole;
use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\MultiTenancy\TenantManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Database Memory Driver - Persists agent memory to database.
 *
 * Provides full multi-tenancy support with tenant-scoped memory storage.
 */
class DatabaseDriver implements MemoryInterface
{
    /**
     * Namespace for scoping memories.
     */
    protected string $namespace = 'default';

    /**
     * Configuration options.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * Tenant manager for scoping.
     */
    protected ?TenantManager $tenantManager = null;

    /**
     * Current tenant for scoping.
     */
    protected ?TenantInterface $tenant = null;

    /**
     * Create a new database memory driver.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'connection' => null,
            'table' => 'agent_memories',
        ], $config);
    }

    /**
     * Set the tenant manager for automatic scoping.
     */
    public function setTenantManager(TenantManager $tenantManager): static
    {
        $this->tenantManager = $tenantManager;

        return $this;
    }

    /**
     * Set the namespace for this memory instance.
     */
    public function setNamespace(string $namespace): static
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * Get the current namespace.
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * Scope memory to a specific tenant.
     */
    public function forTenant(TenantInterface $tenant): static
    {
        $clone = clone $this;
        $clone->tenant = $tenant;

        return $clone;
    }

    /**
     * Store a value in memory.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function store(string $key, mixed $value, array $metadata = []): void
    {
        $now = now();

        $data = [
            'namespace' => $this->namespace,
            'key' => $key,
            'content' => is_string($value) ? $value : json_encode($value),
            'type' => $metadata['type'] ?? 'general',
            'metadata' => json_encode($metadata),
            'importance' => $metadata['importance'] ?? 0.5,
            'updated_at' => $now,
        ];

        // Add tenant scoping
        $tenant = $this->getCurrentTenant();
        if ($tenant) {
            $data['tenant_type'] = get_class($tenant->getModel());
            $data['tenant_id'] = $tenant->getTenantKey();
        }

        // Add optional scoping
        if (isset($metadata['agent_name'])) {
            $data['agent_name'] = $metadata['agent_name'];
        }
        if (isset($metadata['user_id'])) {
            $data['user_id'] = $metadata['user_id'];
        }
        if (isset($metadata['session_id'])) {
            $data['session_id'] = $metadata['session_id'];
        }
        if (isset($metadata['expires_at'])) {
            $data['expires_at'] = $metadata['expires_at'];
        }

        $existing = $this->query()
            ->where('namespace', $this->namespace)
            ->where('key', $key)
            ->first();

        if ($existing) {
            $this->query()
                ->where('id', $existing->id)
                ->update($data);
        } else {
            $data['created_at'] = $now;
            $this->query()->insert($data);
        }
    }

    /**
     * Recall a value from memory.
     */
    public function recall(string $key): mixed
    {
        $record = $this->query()
            ->where('namespace', $this->namespace)
            ->where('key', $key)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $record) {
            return null;
        }

        // Update access tracking
        $this->query()
            ->where('id', $record->id)
            ->update([
                'access_count' => DB::raw('access_count + 1'),
                'last_accessed_at' => now(),
            ]);

        // Try to decode as JSON, otherwise return as string
        $decoded = json_decode($record->content, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $record->content;
    }

    /**
     * Search memories by pattern or semantically.
     *
     * @return Collection<int, array{key: string, content: mixed, score: float, metadata: array<string, mixed>}>
     */
    public function search(string $query, int $limit = 10): Collection
    {
        $results = $this->query()
            ->where('namespace', $this->namespace)
            ->where(function ($q) use ($query) {
                $q->where('key', 'like', "%{$query}%")
                    ->orWhere('content', 'like', "%{$query}%");
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('importance')
            ->orderByDesc('last_accessed_at')
            ->limit($limit)
            ->get();

        return $results->map(function ($record) {
            $content = json_decode($record->content, true);

            return [
                'key' => $record->key,
                'content' => json_last_error() === JSON_ERROR_NONE ? $content : $record->content,
                'score' => $record->importance,
                'metadata' => json_decode($record->metadata ?? '{}', true),
            ];
        });
    }

    /**
     * Get conversation history.
     *
     * @return array<int, Message>
     */
    public function getConversationHistory(int $limit = 50): array
    {
        $records = $this->query()
            ->where('namespace', $this->namespace)
            ->where('type', 'message')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        return $records->map(function ($record) {
            $metadata = json_decode($record->metadata ?? '{}', true);
            $role = $metadata['role'] ?? 'user';

            return new Message(
                role: MessageRole::from($role),
                content: $record->content,
            );
        })->all();
    }

    /**
     * Add a message to conversation history.
     */
    public function addMessage(Message $message): void
    {
        $this->store(
            'msg_'.uniqid(),
            $message->content,
            [
                'type' => 'message',
                'role' => $message->role->value,
            ]
        );
    }

    /**
     * Check if a key exists in memory.
     */
    public function has(string $key): bool
    {
        return $this->query()
            ->where('namespace', $this->namespace)
            ->where('key', $key)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Forget a specific key from memory.
     */
    public function forget(string $key): void
    {
        $this->query()
            ->where('namespace', $this->namespace)
            ->where('key', $key)
            ->delete();
    }

    /**
     * Clear all memories in the current namespace.
     */
    public function clear(): void
    {
        $this->query()
            ->where('namespace', $this->namespace)
            ->delete();
    }

    /**
     * Get the memory driver name.
     */
    public function getDriver(): string
    {
        return 'database';
    }

    /**
     * Get all keys in the current namespace.
     *
     * @return array<string>
     */
    public function keys(): array
    {
        return $this->query()
            ->where('namespace', $this->namespace)
            ->pluck('key')
            ->toArray();
    }

    /**
     * Get all memories in the current namespace.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $records = $this->query()
            ->where('namespace', $this->namespace)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        $result = [];
        foreach ($records as $record) {
            $content = json_decode($record->content, true);
            $result[$record->key] = json_last_error() === JSON_ERROR_NONE ? $content : $record->content;
        }

        return $result;
    }

    /**
     * Clean up expired memories.
     */
    public function cleanup(): int
    {
        return $this->table()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();
    }

    /**
     * Get the current tenant.
     */
    protected function getCurrentTenant(): ?TenantInterface
    {
        if ($this->tenant) {
            return $this->tenant;
        }

        if ($this->tenantManager) {
            return $this->tenantManager->current();
        }

        return null;
    }

    /**
     * Get the base query builder with tenant scoping.
     */
    protected function query(): Builder
    {
        $query = $this->table();

        $tenant = $this->getCurrentTenant();
        if ($tenant) {
            $query->where('tenant_type', get_class($tenant->getModel()))
                ->where('tenant_id', $tenant->getTenantKey());
        }

        return $query;
    }

    /**
     * Get the table query builder.
     */
    protected function table(): Builder
    {
        $connection = $this->config['connection'];
        $table = $this->config['table'];

        return $connection
            ? DB::connection($connection)->table($table)
            : DB::table($table);
    }
}
