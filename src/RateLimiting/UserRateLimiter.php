<?php

declare(strict_types=1);

namespace AgenticOrchestrator\RateLimiting;

use AgenticOrchestrator\Exceptions\RateLimitException;

/**
 * User Rate Limiter - Rate limits requests per user.
 */
class UserRateLimiter extends RateLimiter
{
    protected string $prefix = 'rate_limit:user';

    /**
     * Create for a user.
     *
     * @param  array<string, mixed>  $config
     */
    public static function for(int|string $userId, array $config = []): static
    {
        return new static($config);
    }

    /**
     * Check if a user request is allowed.
     */
    public function checkUser(int|string|object $user): bool
    {
        return $this->check($this->resolveUserId($user));
    }

    /**
     * Attempt a user request.
     *
     *
     * @throws RateLimitException
     */
    public function attemptUser(int|string|object $user): bool
    {
        return $this->attempt($this->resolveUserId($user));
    }

    /**
     * Get remaining requests for a user.
     */
    public function remainingForUser(int|string|object $user): int
    {
        return $this->remaining($this->resolveUserId($user));
    }

    /**
     * Get status for a user.
     *
     *
     * @return array<string, mixed>
     */
    public function userStatus(int|string|object $user): array
    {
        return $this->status($this->resolveUserId($user));
    }

    /**
     * Resolve user ID from various inputs.
     */
    protected function resolveUserId(int|string|object $user): string
    {
        if (is_object($user)) {
            return (string) ($user->id ?? $user->getKey());
        }

        return (string) $user;
    }

    /**
     * Throw appropriate exception.
     *
     * @throws RateLimitException
     */
    protected function throwException(string $key, int $retryAfter): void
    {
        throw RateLimitException::forUser($key, $retryAfter, $this->maxRequests);
    }
}
