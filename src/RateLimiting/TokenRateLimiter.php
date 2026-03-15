<?php

declare(strict_types=1);

namespace AgenticOrchestrator\RateLimiting;

use AgenticOrchestrator\Exceptions\RateLimitException;

/**
 * Token Rate Limiter - Rate limits based on token usage.
 *
 * Unlike request-based limiters, this tracks token consumption.
 */
class TokenRateLimiter extends RateLimiter
{
    protected string $prefix = 'rate_limit:tokens';

    /**
     * Maximum tokens allowed in the window.
     */
    protected int $maxTokens = 100000;

    /**
     * Create a new token rate limiter.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (isset($config['max_tokens'])) {
            $this->maxTokens = max(1, (int) $config['max_tokens']);
        }
    }

    /**
     * Set maximum tokens.
     */
    public function maxTokens(int $max): static
    {
        $this->maxTokens = max(1, $max);

        return $this;
    }

    /**
     * Check if token usage is allowed.
     */
    public function checkTokens(string $key, int $requestedTokens): bool
    {
        $current = $this->getCurrentCount($key);

        return ($current + $requestedTokens) <= $this->maxTokens;
    }

    /**
     * Record token usage.
     *
     * @throws RateLimitException
     */
    public function recordUsage(string $key, int $inputTokens, int $outputTokens): bool
    {
        $totalTokens = $inputTokens + $outputTokens;

        if (! $this->checkTokens($key, $totalTokens)) {
            $this->handleLimitExceeded($key);

            return false;
        }

        $this->increment($key, $totalTokens);

        return true;
    }

    /**
     * Attempt to use tokens.
     *
     * @throws RateLimitException
     */
    public function attemptTokens(string $key, int $tokens): bool
    {
        if (! $this->checkTokens($key, $tokens)) {
            $this->handleLimitExceeded($key);

            return false;
        }

        $this->increment($key, $tokens);

        return true;
    }

    /**
     * Get remaining tokens.
     */
    public function remainingTokens(string $key): int
    {
        return max(0, $this->maxTokens - $this->getCurrentCount($key));
    }

    /**
     * Get token usage status.
     *
     * @return array<string, mixed>
     */
    public function tokenStatus(string $key): array
    {
        return [
            'limit' => $this->maxTokens,
            'remaining' => $this->remainingTokens($key),
            'used' => $this->getCurrentCount($key),
            'retry_after' => $this->retryAfter($key),
            'window_seconds' => $this->windowSeconds,
        ];
    }

    /**
     * Create for a team.
     *
     * @param  array<string, mixed>  $config
     */
    public static function forTeam(int|string|object $team, array $config = []): static
    {
        $teamId = is_object($team) ? ($team->id ?? $team->getKey()) : $team;
        $limiter = new static($config);
        $limiter->prefix = "rate_limit:tokens:team:{$teamId}";

        return $limiter;
    }

    /**
     * Create for a user.
     *
     * @param  array<string, mixed>  $config
     */
    public static function forUser(int|string|object $user, array $config = []): static
    {
        $userId = is_object($user) ? ($user->id ?? $user->getKey()) : $user;
        $limiter = new static($config);
        $limiter->prefix = "rate_limit:tokens:user:{$userId}";

        return $limiter;
    }

    /**
     * Throw appropriate exception.
     *
     * @throws RateLimitException
     */
    protected function throwException(string $key, int $retryAfter): void
    {
        throw RateLimitException::forTokens($key, $retryAfter, $this->maxTokens);
    }
}
