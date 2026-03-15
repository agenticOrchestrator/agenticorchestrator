# Rate Limiting

Rate limiting is a critical component for managing AI agent operations at scale. It protects your application from runaway costs, ensures fair resource distribution among users and teams, and prevents abuse of your LLM provider APIs.

## Why Rate Limiting Matters for AI Agents

AI agent operations differ from traditional API requests in several important ways:

1. **Cost Implications**: Each agent request consumes tokens from paid LLM providers. Without rate limiting, a single user or runaway process could generate substantial costs in minutes.

2. **Provider Limits**: LLM providers enforce their own rate limits. If your application exceeds these limits, all users experience service degradation.

3. **Resource Fairness**: In multi-tenant applications, rate limiting ensures that one team's heavy usage does not impact other teams.

4. **Token Consumption**: Unlike simple request counting, AI workloads vary dramatically in size. A single complex prompt might consume as many tokens as hundreds of simple requests.

## Rate Limiting Architecture

The Agent Orchestrator provides a hierarchical rate limiting system with four specialized limiters:

```
                    +-------------------+
                    |  Token Limiter    |  (Tracks actual token consumption)
                    +-------------------+
                            |
            +---------------+---------------+
            |               |               |
    +-------+-------+  +----+----+  +-------+-------+
    | Agent Limiter |  | Team    |  | User Limiter  |
    | (per agent)   |  | Limiter |  | (per user)    |
    +---------------+  +---------+  +---------------+
```

### Limiter Types

| Limiter | Purpose | Use Case |
|---------|---------|----------|
| `AgentRateLimiter` | Limits requests per agent | Prevent a single agent from monopolizing resources |
| `TeamRateLimiter` | Limits requests per team/tenant | Enforce subscription tier limits |
| `UserRateLimiter` | Limits requests per user | Protect against individual user abuse |
| `TokenRateLimiter` | Limits token consumption | Control actual costs regardless of request count |

## Quick Start

### Basic Rate Limiting

```php
use AgenticOrchestrator\RateLimiting\UserRateLimiter;

$limiter = new UserRateLimiter([
    'max_requests' => 100,
    'window_seconds' => 60,
]);

// Check if request is allowed
if ($limiter->checkUser($user)) {
    // Proceed with agent operation
    $limiter->attemptUser($user);
    $response = $agent->run($prompt);
}
```

### Using the Fluent API

```php
use AgenticOrchestrator\RateLimiting\TeamRateLimiter;

$limiter = (new TeamRateLimiter())
    ->perMinute(1000)
    ->onLimitExceeded(function ($key, $retryAfter) {
        Log::warning("Team {$key} rate limited, retry after {$retryAfter}s");
    });

$limiter->attemptTeam($team);
```

### Token-Based Limiting

```php
use AgenticOrchestrator\RateLimiting\TokenRateLimiter;

$limiter = TokenRateLimiter::forTeam($team, [
    'max_tokens' => 100000,
    'window_seconds' => 3600,
]);

// After receiving LLM response
$limiter->recordUsage('agent-key', $inputTokens, $outputTokens);
```

## Configuration

Rate limiting is configured in `config/agent-orchestrator.php`:

```php
'rate_limiting' => [
    'enabled' => env('AGENT_RATE_LIMITING', true),

    'per_user' => [
        'requests' => env('AGENT_RATE_LIMIT_USER_REQUESTS', 100),
        'period' => env('AGENT_RATE_LIMIT_USER_PERIOD', 60),
    ],

    'per_team' => [
        'requests' => env('AGENT_RATE_LIMIT_TEAM_REQUESTS', 1000),
        'period' => env('AGENT_RATE_LIMIT_TEAM_PERIOD', 60),
    ],

    'per_agent' => [
        'requests' => env('AGENT_RATE_LIMIT_AGENT_REQUESTS', 500),
        'period' => env('AGENT_RATE_LIMIT_AGENT_PERIOD', 60),
    ],

    'tokens' => [
        'enabled' => env('AGENT_TOKEN_RATE_LIMITING', false),
        'per_user' => env('AGENT_TOKEN_LIMIT_USER', 100000),
        'per_team' => env('AGENT_TOKEN_LIMIT_TEAM', 1000000),
    ],
],
```

## Exception Handling

When rate limits are exceeded, a `RateLimitException` is thrown with detailed information:

```php
use AgenticOrchestrator\Exceptions\RateLimitException;

try {
    $limiter->attemptUser($user);
    $response = $agent->run($prompt);
} catch (RateLimitException $e) {
    $retryAfter = $e->getRetryAfter();

    return response()->json([
        'error' => 'Rate limit exceeded',
        'retry_after' => $retryAfter,
    ], 429)->header('Retry-After', $retryAfter);
}
```

## Next Steps

- [Available Limiters](limiters.md) - Detailed documentation for each limiter type
- [Configuration](configuration.md) - Advanced configuration options
- [Handling Limits](handling-limits.md) - Graceful degradation strategies
- [Monitoring](monitoring.md) - Tracking rate limit usage and metrics
