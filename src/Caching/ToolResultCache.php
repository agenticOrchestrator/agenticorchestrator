<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Caching;

use AgenticOrchestrator\Tools\ToolResult;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tool Result Cache - Caches tool execution results.
 */
class ToolResultCache
{
    /**
     * Cache key generator.
     */
    protected CacheKeyGenerator $keyGenerator;

    /**
     * Cache store name.
     */
    protected ?string $cacheStore = null;

    /**
     * Default TTL in seconds.
     */
    protected int $defaultTtl = 300;

    /**
     * Whether caching is enabled.
     */
    protected bool $enabled = true;

    /**
     * Tool-specific cache configurations.
     *
     * @var array<string, array{enabled?: bool, ttl?: int}>
     */
    protected array $toolConfigs = [];

    /**
     * Statistics tracking.
     *
     * @var array{hits: int, misses: int, stores: int}
     */
    protected array $stats = [
        'hits' => 0,
        'misses' => 0,
        'stores' => 0,
    ];

    /**
     * Create a new tool result cache.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->keyGenerator = new CacheKeyGenerator;
        $this->configure($config);
    }

    /**
     * Configure the tool result cache.
     *
     * @param  array<string, mixed>  $config
     */
    public function configure(array $config): static
    {
        if (isset($config['enabled'])) {
            $this->enabled = (bool) $config['enabled'];
        }

        if (isset($config['ttl'])) {
            $this->defaultTtl = max(1, (int) $config['ttl']);
        }

        if (isset($config['cache_store'])) {
            $this->cacheStore = $config['cache_store'];
        }

        if (isset($config['prefix'])) {
            $this->keyGenerator->setPrefix($config['prefix']);
        }

        if (isset($config['tools'])) {
            $this->toolConfigs = $config['tools'];
        }

        return $this;
    }

    /**
     * Get a cached tool result or execute the callback.
     *
     * @param  array<string, mixed>  $arguments
     * @param  Closure(): ToolResult  $callback
     */
    public function remember(
        string $toolName,
        array $arguments,
        ?int $teamId,
        Closure $callback,
    ): ToolResult {
        if (! $this->shouldCache($toolName)) {
            return $callback();
        }

        $key = $this->keyGenerator->forToolResult($toolName, $arguments, $teamId);

        $cached = $this->get($key);

        if ($cached !== null) {
            $this->stats['hits']++;
            Log::debug('Tool result cache hit', ['tool' => $toolName, 'key' => $key]);

            return $cached;
        }

        $this->stats['misses']++;
        $result = $callback();

        // Only cache successful results
        if ($result->success) {
            $this->put($key, $result, $this->getTtl($toolName));
            $this->stats['stores']++;
            Log::debug('Tool result cached', ['tool' => $toolName, 'key' => $key]);
        }

        return $result;
    }

    /**
     * Get a cached tool result.
     */
    public function get(string $key): ?ToolResult
    {
        if (! $this->enabled) {
            return null;
        }

        $data = $this->cache()->get($key);

        if ($data === null) {
            return null;
        }

        return $this->deserializeResult($data);
    }

    /**
     * Store a tool result.
     */
    public function put(string $key, ToolResult $result, ?int $ttl = null): void
    {
        if (! $this->enabled) {
            return;
        }

        $data = $this->serializeResult($result);
        $this->cache()->put($key, $data, $ttl ?? $this->defaultTtl);
    }

    /**
     * Check if a tool result is cached.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function has(string $toolName, array $arguments, ?int $teamId = null): bool
    {
        if (! $this->enabled) {
            return false;
        }

        $key = $this->keyGenerator->forToolResult($toolName, $arguments, $teamId);

        return $this->cache()->has($key);
    }

    /**
     * Forget a specific cached tool result.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function forget(string $toolName, array $arguments, ?int $teamId = null): bool
    {
        $key = $this->keyGenerator->forToolResult($toolName, $arguments, $teamId);

        return $this->cache()->forget($key);
    }

    /**
     * Invalidate all cached results for a tool.
     */
    public function invalidateTool(string $toolName): void
    {
        // Note: This requires cache tags or pattern deletion support
        Log::info('Cache invalidation requested for tool', ['tool' => $toolName]);
    }

    /**
     * Flush all cached tool results.
     */
    public function flush(): void
    {
        $this->cache()->flush();
        $this->resetStats();
    }

    /**
     * Enable caching.
     */
    public function enable(): static
    {
        $this->enabled = true;

        return $this;
    }

    /**
     * Disable caching.
     */
    public function disable(): static
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * Check if caching is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Set tool-specific configuration.
     *
     * @param  array{enabled?: bool, ttl?: int}  $config
     */
    public function configureTool(string $toolName, array $config): static
    {
        $this->toolConfigs[$toolName] = $config;

        return $this;
    }

    /**
     * Get cache statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $total > 0 ? round(($this->stats['hits'] / $total) * 100, 2) : 0;

        return [
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'stores' => $this->stats['stores'],
            'total_requests' => $total,
            'hit_rate' => $hitRate,
        ];
    }

    /**
     * Reset statistics.
     */
    public function resetStats(): void
    {
        $this->stats = ['hits' => 0, 'misses' => 0, 'stores' => 0];
    }

    /**
     * Check if caching should be applied for a tool.
     */
    protected function shouldCache(string $toolName): bool
    {
        if (! $this->enabled) {
            return false;
        }

        if (isset($this->toolConfigs[$toolName]['enabled'])) {
            return $this->toolConfigs[$toolName]['enabled'];
        }

        return true;
    }

    /**
     * Get TTL for a tool.
     */
    protected function getTtl(string $toolName): int
    {
        if (isset($this->toolConfigs[$toolName]['ttl'])) {
            return $this->toolConfigs[$toolName]['ttl'];
        }

        return $this->defaultTtl;
    }

    /**
     * Serialize a ToolResult for caching.
     *
     * @return array<string, mixed>
     */
    protected function serializeResult(ToolResult $result): array
    {
        return [
            'tool_call_id' => $result->toolCallId,
            'name' => $result->name,
            'arguments' => $result->arguments,
            'result' => $result->result,
            'success' => $result->success,
            'error' => $result->error,
            'duration' => $result->duration,
            'cached' => true,
            'cached_at' => time(),
        ];
    }

    /**
     * Deserialize a ToolResult from cache.
     *
     * @param  array<string, mixed>  $data
     */
    protected function deserializeResult(array $data): ToolResult
    {
        if ($data['success']) {
            return ToolResult::success(
                toolCallId: $data['tool_call_id'],
                name: $data['name'],
                arguments: $data['arguments'],
                result: $data['result'],
                duration: $data['duration'],
                cached: true,
            );
        }

        return ToolResult::failure(
            toolCallId: $data['tool_call_id'],
            name: $data['name'],
            arguments: $data['arguments'],
            error: $data['error'] ?? 'Unknown error',
            duration: $data['duration'],
        );
    }

    /**
     * Get the cache repository.
     */
    protected function cache(): CacheRepository
    {
        return $this->cacheStore !== null
            ? Cache::store($this->cacheStore)
            : Cache::store();
    }
}
