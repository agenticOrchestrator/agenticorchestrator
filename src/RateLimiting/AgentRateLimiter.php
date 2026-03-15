<?php

declare(strict_types=1);

namespace AgenticOrchestrator\RateLimiting;

use AgenticOrchestrator\Exceptions\RateLimitException;

/**
 * Agent Rate Limiter - Rate limits requests per agent.
 */
class AgentRateLimiter extends RateLimiter
{
    protected string $prefix = 'rate_limit:agent';

    /**
     * Create for an agent.
     *
     * @param  array<string, mixed>  $config
     */
    public static function for(string $agentName, array $config = []): static
    {
        return new static($config);
    }

    /**
     * Check if an agent request is allowed.
     */
    public function checkAgent(string $agentName): bool
    {
        return $this->check($agentName);
    }

    /**
     * Attempt an agent request.
     *
     * @throws RateLimitException
     */
    public function attemptAgent(string $agentName): bool
    {
        return $this->attempt($agentName);
    }

    /**
     * Get remaining requests for an agent.
     */
    public function remainingForAgent(string $agentName): int
    {
        return $this->remaining($agentName);
    }

    /**
     * Get status for an agent.
     *
     * @return array<string, mixed>
     */
    public function agentStatus(string $agentName): array
    {
        return $this->status($agentName);
    }

    /**
     * Throw appropriate exception.
     *
     * @throws RateLimitException
     */
    protected function throwException(string $key, int $retryAfter): void
    {
        throw RateLimitException::forAgent($key, $retryAfter, $this->maxRequests);
    }
}
