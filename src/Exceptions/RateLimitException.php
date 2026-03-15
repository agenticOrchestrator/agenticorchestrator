<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Exceptions;

use Throwable;

/**
 * Thrown when a rate limit is exceeded.
 */
class RateLimitException extends AgentException
{
    protected string $limiterType = '';

    protected ?string $identifier = null;

    protected ?int $retryAfter = null;

    protected int $limit = 0;

    protected int $remaining = 0;

    /**
     * Create a new rate limit exception.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $limiterType,
        ?string $identifier = null,
        ?int $retryAfter = null,
        int $limit = 0,
        int $remaining = 0,
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
    ) {
        $this->limiterType = $limiterType;
        $this->identifier = $identifier;
        $this->retryAfter = $retryAfter;
        $this->limit = $limit;
        $this->remaining = $remaining;

        $message = "Rate limit exceeded for {$limiterType}";
        if ($identifier) {
            $message .= " ({$identifier})";
        }
        if ($retryAfter !== null) {
            $message .= ", retry after {$retryAfter} seconds";
        }

        parent::__construct($message, $code, $previous, array_merge($context, [
            'limiter_type' => $limiterType,
            'identifier' => $identifier,
            'retry_after' => $retryAfter,
            'limit' => $limit,
            'remaining' => $remaining,
        ]));

        $this->recoverable = true;
    }

    /**
     * Create for agent rate limit.
     */
    public static function forAgent(
        string $agentName,
        ?int $retryAfter = null,
        int $limit = 0,
    ): static {
        return new static('agent', $agentName, $retryAfter, $limit);
    }

    /**
     * Create for team rate limit.
     */
    public static function forTeam(
        int|string $teamId,
        ?int $retryAfter = null,
        int $limit = 0,
    ): static {
        return new static('team', (string) $teamId, $retryAfter, $limit);
    }

    /**
     * Create for user rate limit.
     */
    public static function forUser(
        int|string $userId,
        ?int $retryAfter = null,
        int $limit = 0,
    ): static {
        return new static('user', (string) $userId, $retryAfter, $limit);
    }

    /**
     * Create for token rate limit.
     */
    public static function forTokens(
        ?string $identifier = null,
        ?int $retryAfter = null,
        int $limit = 0,
    ): static {
        return new static('tokens', $identifier, $retryAfter, $limit);
    }

    /**
     * Get the limiter type.
     */
    public function getLimiterType(): string
    {
        return $this->limiterType;
    }

    /**
     * Get the rate limited identifier.
     */
    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    /**
     * Get the retry after seconds.
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * Get the rate limit.
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Get the remaining requests.
     */
    public function getRemaining(): int
    {
        return $this->remaining;
    }
}
