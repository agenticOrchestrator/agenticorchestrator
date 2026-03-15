<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Caching;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Embedding Cache - Caches embeddings to reduce API calls and costs.
 */
class EmbeddingCache
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
     * Default TTL in seconds (24 hours).
     */
    protected int $defaultTtl = 86400;

    /**
     * Whether caching is enabled.
     */
    protected bool $enabled = true;

    /**
     * Statistics tracking.
     *
     * @var array{hits: int, misses: int, stores: int, tokens_saved: int}
     */
    protected array $stats = [
        'hits' => 0,
        'misses' => 0,
        'stores' => 0,
        'tokens_saved' => 0,
    ];

    /**
     * Create a new embedding cache.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->keyGenerator = new CacheKeyGenerator;
        $this->configure($config);
    }

    /**
     * Configure the embedding cache.
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

        return $this;
    }

    /**
     * Get a cached embedding or execute the callback.
     *
     * @param  Closure(): array<float>  $callback
     * @return array<float>
     */
    public function remember(
        string $text,
        ?string $model,
        ?int $dimensions,
        Closure $callback,
    ): array {
        if (! $this->enabled) {
            return $callback();
        }

        $key = $this->keyGenerator->forEmbedding($text, $model, $dimensions);

        $cached = $this->get($key);

        if ($cached !== null) {
            $this->stats['hits']++;
            $this->stats['tokens_saved'] += $this->estimateTokens($text);
            Log::debug('Embedding cache hit', ['key' => $key]);

            return $cached;
        }

        $this->stats['misses']++;
        $embedding = $callback();

        $this->put($key, $embedding, $text);
        $this->stats['stores']++;

        return $embedding;
    }

    /**
     * Get multiple cached embeddings at once.
     *
     * @param  array<string>  $texts
     * @param  Closure(array<string>): array<string, array<float>>  $callback
     * @return array<string, array<float>>
     */
    public function rememberMany(
        array $texts,
        ?string $model,
        ?int $dimensions,
        Closure $callback,
    ): array {
        if (! $this->enabled) {
            return $callback($texts);
        }

        $results = [];
        $missing = [];

        foreach ($texts as $text) {
            $key = $this->keyGenerator->forEmbedding($text, $model, $dimensions);
            $cached = $this->get($key);

            if ($cached !== null) {
                $results[$text] = $cached;
                $this->stats['hits']++;
                $this->stats['tokens_saved'] += $this->estimateTokens($text);
            } else {
                $missing[] = $text;
                $this->stats['misses']++;
            }
        }

        if (! empty($missing)) {
            $newEmbeddings = $callback($missing);

            foreach ($newEmbeddings as $text => $embedding) {
                $key = $this->keyGenerator->forEmbedding($text, $model, $dimensions);
                $this->put($key, $embedding, $text);
                $this->stats['stores']++;
                $results[$text] = $embedding;
            }
        }

        return $results;
    }

    /**
     * Get a cached embedding.
     *
     * @return array<float>|null
     */
    public function get(string $key): ?array
    {
        if (! $this->enabled) {
            return null;
        }

        return $this->cache()->get($key);
    }

    /**
     * Store an embedding.
     *
     * @param  array<float>  $embedding
     */
    public function put(string $key, array $embedding, ?string $originalText = null): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->cache()->put($key, $embedding, $this->defaultTtl);
    }

    /**
     * Check if an embedding is cached.
     */
    public function has(string $text, ?string $model = null, ?int $dimensions = null): bool
    {
        if (! $this->enabled) {
            return false;
        }

        $key = $this->keyGenerator->forEmbedding($text, $model, $dimensions);

        return $this->cache()->has($key);
    }

    /**
     * Forget a specific cached embedding.
     */
    public function forget(string $text, ?string $model = null, ?int $dimensions = null): bool
    {
        $key = $this->keyGenerator->forEmbedding($text, $model, $dimensions);

        return $this->cache()->forget($key);
    }

    /**
     * Flush all cached embeddings.
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
            'tokens_saved' => $this->stats['tokens_saved'],
        ];
    }

    /**
     * Reset statistics.
     */
    public function resetStats(): void
    {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'stores' => 0,
            'tokens_saved' => 0,
        ];
    }

    /**
     * Estimate token count for text.
     */
    protected function estimateTokens(string $text): int
    {
        // Rough estimate: ~4 chars per token for English
        return (int) ceil(mb_strlen($text) / 4);
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
