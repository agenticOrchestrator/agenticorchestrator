<?php

declare(strict_types=1);

namespace AgenticOrchestrator\RateLimiting;

use AgenticOrchestrator\Exceptions\RateLimitException;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Base Rate Limiter - Abstract class for rate limiting implementations.
 */
abstract class RateLimiter
{
    /**
     * Cache store name.
     */
    protected ?string $cacheStore = null;

    /**
     * Maximum number of requests allowed.
     */
    protected int $maxRequests = 60;

    /**
     * Time window in seconds.
     */
    protected int $windowSeconds = 60;

    /**
     * Cache key prefix.
     */
    protected string $prefix = 'rate_limit';

    /**
     * Callback to execute when rate limit is hit.
     */
    protected ?Closure $onLimitExceeded = null;

    /**
     * Create a new rate limiter.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->configure($config);
    }

    /**
     * Configure the rate limiter.
     *
     * @param  array<string, mixed>  $config
     */
    public function configure(array $config): static
    {
        if (isset($config['max_requests'])) {
            $this->maxRequests = max(1, (int) $config['max_requests']);
        }

        if (isset($config['window_seconds'])) {
            $this->windowSeconds = max(1, (int) $config['window_seconds']);
        }

        if (isset($config['cache_store'])) {
            $this->cacheStore = $config['cache_store'];
        }

        if (isset($config['prefix'])) {
            $this->prefix = $config['prefix'];
        }

        return $this;
    }

    /**
     * Set maximum requests.
     */
    public function maxRequests(int $max): static
    {
        $this->maxRequests = max(1, $max);

        return $this;
    }

    /**
     * Set time window in seconds.
     */
    public function windowSeconds(int $seconds): static
    {
        $this->windowSeconds = max(1, $seconds);

        return $this;
    }

    /**
     * Set requests per minute.
     */
    public function perMinute(int $requests): static
    {
        $this->maxRequests = $requests;
        $this->windowSeconds = 60;

        return $this;
    }

    /**
     * Set requests per hour.
     */
    public function perHour(int $requests): static
    {
        $this->maxRequests = $requests;
        $this->windowSeconds = 3600;

        return $this;
    }

    /**
     * Set requests per day.
     */
    public function perDay(int $requests): static
    {
        $this->maxRequests = $requests;
        $this->windowSeconds = 86400;

        return $this;
    }

    /**
     * Set callback when limit is exceeded.
     */
    public function onLimitExceeded(Closure $callback): static
    {
        $this->onLimitExceeded = $callback;

        return $this;
    }

    /**
     * Check if a request is allowed without incrementing.
     */
    public function check(string $key): bool
    {
        $current = $this->getCurrentCount($key);

        return $current < $this->maxRequests;
    }

    /**
     * Attempt to execute a request, incrementing the counter.
     *
     * @throws RateLimitException
     */
    public function attempt(string $key): bool
    {
        if (! $this->check($key)) {
            $this->handleLimitExceeded($key);

            return false;
        }

        $this->increment($key);

        return true;
    }

    /**
     * Execute a callback if rate limit allows.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     *
     * @throws RateLimitException
     */
    public function execute(string $key, Closure $callback): mixed
    {
        if (! $this->check($key)) {
            $this->handleLimitExceeded($key);
        }

        $this->increment($key);

        return $callback();
    }

    /**
     * Get current request count.
     */
    public function getCurrentCount(string $key): int
    {
        $cacheKey = $this->getCacheKey($key);

        return (int) $this->cache()->get($cacheKey, 0);
    }

    /**
     * Get remaining requests.
     */
    public function remaining(string $key): int
    {
        return max(0, $this->maxRequests - $this->getCurrentCount($key));
    }

    /**
     * Get seconds until the rate limit resets.
     */
    public function retryAfter(string $key): int
    {
        $ttlKey = $this->getCacheKey($key).':ttl';
        $expiresAt = $this->cache()->get($ttlKey);

        if ($expiresAt === null) {
            return 0;
        }

        return max(0, $expiresAt - time());
    }

    /**
     * Increment the request counter.
     */
    public function increment(string $key, int $by = 1): int
    {
        $cacheKey = $this->getCacheKey($key);
        $ttlKey = $cacheKey.':ttl';

        $current = $this->getCurrentCount($key);
        $newCount = $current + $by;

        // Set or update the count
        if ($current === 0) {
            // New window
            $expiresAt = time() + $this->windowSeconds;
            $this->cache()->put($cacheKey, $newCount, $this->windowSeconds);
            $this->cache()->put($ttlKey, $expiresAt, $this->windowSeconds);
        } else {
            // Existing window - preserve TTL
            $remainingTtl = $this->retryAfter($key);

            if ($remainingTtl > 0) {
                $this->cache()->put($cacheKey, $newCount, $remainingTtl);
            }
        }

        return $newCount;
    }

    /**
     * Decrement the request counter.
     */
    public function decrement(string $key, int $by = 1): int
    {
        $cacheKey = $this->getCacheKey($key);
        $current = $this->getCurrentCount($key);
        $newCount = max(0, $current - $by);

        $remainingTtl = $this->retryAfter($key);

        if ($remainingTtl > 0) {
            $this->cache()->put($cacheKey, $newCount, $remainingTtl);
        }

        return $newCount;
    }

    /**
     * Reset the rate limit for a key.
     */
    public function reset(string $key): void
    {
        $cacheKey = $this->getCacheKey($key);
        $ttlKey = $cacheKey.':ttl';

        $this->cache()->forget($cacheKey);
        $this->cache()->forget($ttlKey);
    }

    /**
     * Get rate limit status.
     *
     * @return array<string, mixed>
     */
    public function status(string $key): array
    {
        return [
            'limit' => $this->maxRequests,
            'remaining' => $this->remaining($key),
            'current' => $this->getCurrentCount($key),
            'retry_after' => $this->retryAfter($key),
            'window_seconds' => $this->windowSeconds,
        ];
    }

    /**
     * Handle rate limit exceeded.
     *
     * @throws RateLimitException
     */
    protected function handleLimitExceeded(string $key): void
    {
        $retryAfter = $this->retryAfter($key);

        if ($this->onLimitExceeded !== null) {
            ($this->onLimitExceeded)($key, $retryAfter);
        }

        $this->throwException($key, $retryAfter);
    }

    /**
     * Throw appropriate rate limit exception.
     *
     * @throws RateLimitException
     */
    abstract protected function throwException(string $key, int $retryAfter): void;

    /**
     * Get cache key for the rate limiter.
     */
    protected function getCacheKey(string $key): string
    {
        return "{$this->prefix}:{$key}";
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
