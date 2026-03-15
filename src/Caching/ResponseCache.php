<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Caching;

use AgenticOrchestrator\Agents\AgentResponse;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Response Cache - Caches agent responses for identical inputs.
 */
class ResponseCache
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
    protected int $defaultTtl = 3600;

    /**
     * Whether caching is enabled.
     */
    protected bool $enabled = true;

    /**
     * Agent-specific cache configurations.
     *
     * @var array<string, array{enabled?: bool, ttl?: int}>
     */
    protected array $agentConfigs = [];

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
     * Create a new response cache.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->keyGenerator = new CacheKeyGenerator;
        $this->configure($config);
    }

    /**
     * Configure the response cache.
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

        if (isset($config['agents'])) {
            $this->agentConfigs = $config['agents'];
        }

        return $this;
    }

    /**
     * Get a cached response or execute the callback.
     *
     * @param  array<string, mixed>  $context
     */
    public function remember(
        string $agentName,
        string $input,
        array $context,
        ?string $model,
        Closure $callback,
    ): AgentResponse {
        if (! $this->shouldCache($agentName)) {
            return $callback();
        }

        $key = $this->keyGenerator->forResponse($agentName, $input, $context, $model);

        $cached = $this->get($key);

        if ($cached !== null) {
            $this->stats['hits']++;
            Log::debug('Response cache hit', ['agent' => $agentName, 'key' => $key]);

            return $cached;
        }

        $this->stats['misses']++;
        $response = $callback();

        $this->put($key, $response, $this->getTtl($agentName));
        $this->stats['stores']++;

        Log::debug('Response cached', ['agent' => $agentName, 'key' => $key]);

        return $response;
    }

    /**
     * Get a cached response.
     */
    public function get(string $key): ?AgentResponse
    {
        if (! $this->enabled) {
            return null;
        }

        $data = $this->cache()->get($key);

        if ($data === null) {
            return null;
        }

        // Reconstruct AgentResponse from cached data
        return $this->deserializeResponse($data);
    }

    /**
     * Store a response.
     */
    public function put(string $key, AgentResponse $response, ?int $ttl = null): void
    {
        if (! $this->enabled) {
            return;
        }

        $data = $this->serializeResponse($response);
        $this->cache()->put($key, $data, $ttl ?? $this->defaultTtl);
    }

    /**
     * Check if a response is cached.
     */
    public function has(string $agentName, string $input, array $context = [], ?string $model = null): bool
    {
        if (! $this->enabled) {
            return false;
        }

        $key = $this->keyGenerator->forResponse($agentName, $input, $context, $model);

        return $this->cache()->has($key);
    }

    /**
     * Forget a specific cached response.
     *
     * @param  array<string, mixed>  $context
     */
    public function forget(string $agentName, string $input, array $context = [], ?string $model = null): bool
    {
        $key = $this->keyGenerator->forResponse($agentName, $input, $context, $model);

        return $this->cache()->forget($key);
    }

    /**
     * Flush all cached responses for an agent.
     */
    public function flushAgent(string $agentName): void
    {
        // Note: This requires cache tags or pattern deletion support
        // For now, we log the intent
        Log::info('Cache flush requested for agent', ['agent' => $agentName]);
    }

    /**
     * Flush all cached responses.
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
     * Set agent-specific configuration.
     *
     * @param  array{enabled?: bool, ttl?: int}  $config
     */
    public function configureAgent(string $agentName, array $config): static
    {
        $this->agentConfigs[$agentName] = $config;

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
     * Check if caching should be applied for an agent.
     */
    protected function shouldCache(string $agentName): bool
    {
        if (! $this->enabled) {
            return false;
        }

        if (isset($this->agentConfigs[$agentName]['enabled'])) {
            return $this->agentConfigs[$agentName]['enabled'];
        }

        return true;
    }

    /**
     * Get TTL for an agent.
     */
    protected function getTtl(string $agentName): int
    {
        if (isset($this->agentConfigs[$agentName]['ttl'])) {
            return $this->agentConfigs[$agentName]['ttl'];
        }

        return $this->defaultTtl;
    }

    /**
     * Serialize an AgentResponse for caching.
     *
     * @return array<string, mixed>
     */
    protected function serializeResponse(AgentResponse $response): array
    {
        return [
            'content' => $response->content,
            'metadata' => $response->metadata ?? [],
            'tool_calls' => $response->toolCalls ?? [],
            'usage' => $response->usage ?? [],
            'cached' => true,
            'cached_at' => time(),
        ];
    }

    /**
     * Deserialize an AgentResponse from cache.
     *
     * @param  array<string, mixed>  $data
     */
    protected function deserializeResponse(array $data): AgentResponse
    {
        return new AgentResponse(
            content: $data['content'] ?? '',
            metadata: array_merge($data['metadata'] ?? [], [
                'from_cache' => true,
                'cached_at' => $data['cached_at'] ?? null,
            ]),
            toolCalls: $data['tool_calls'] ?? [],
            usage: $data['usage'] ?? [],
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
