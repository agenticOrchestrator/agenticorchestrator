<?php

declare(strict_types=1);

namespace AgenticOrchestrator\RateLimiting;

use AgenticOrchestrator\Exceptions\RateLimitException;

/**
 * Team Rate Limiter - Rate limits requests per team.
 */
class TeamRateLimiter extends RateLimiter
{
    protected string $prefix = 'rate_limit:team';

    /**
     * Create for a team.
     *
     * @param  array<string, mixed>  $config
     */
    public static function for(int|string $teamId, array $config = []): static
    {
        return new static($config);
    }

    /**
     * Check if a team request is allowed.
     */
    public function checkTeam(int|string|object $team): bool
    {
        return $this->check($this->resolveTeamId($team));
    }

    /**
     * Attempt a team request.
     *
     *
     * @throws RateLimitException
     */
    public function attemptTeam(int|string|object $team): bool
    {
        return $this->attempt($this->resolveTeamId($team));
    }

    /**
     * Get remaining requests for a team.
     */
    public function remainingForTeam(int|string|object $team): int
    {
        return $this->remaining($this->resolveTeamId($team));
    }

    /**
     * Get status for a team.
     *
     *
     * @return array<string, mixed>
     */
    public function teamStatus(int|string|object $team): array
    {
        return $this->status($this->resolveTeamId($team));
    }

    /**
     * Resolve team ID from various inputs.
     */
    protected function resolveTeamId(int|string|object $team): string
    {
        if (is_object($team)) {
            return (string) ($team->id ?? $team->getKey());
        }

        return (string) $team;
    }

    /**
     * Throw appropriate exception.
     *
     * @throws RateLimitException
     */
    protected function throwException(string $key, int $retryAfter): void
    {
        throw RateLimitException::forTeam($key, $retryAfter, $this->maxRequests);
    }
}
