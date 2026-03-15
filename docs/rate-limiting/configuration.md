# Rate Limiting Configuration

This guide covers all configuration options for the rate limiting system, including global settings, per-entity limits, and advanced customization.

## Global Configuration

Rate limiting is configured in `config/agent-orchestrator.php` under the `rate_limiting` key:

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

## Configuration Reference

### Global Settings

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `true` | Enable or disable rate limiting globally |

### Per-User Limits

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `per_user.requests` | int | `100` | Maximum requests per user in the time window |
| `per_user.period` | int | `60` | Time window in seconds |

### Per-Team Limits

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `per_team.requests` | int | `1000` | Maximum requests per team in the time window |
| `per_team.period` | int | `60` | Time window in seconds |

### Per-Agent Limits

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `per_agent.requests` | int | `500` | Maximum requests per agent in the time window |
| `per_agent.period` | int | `60` | Time window in seconds |

### Token Limits

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `tokens.enabled` | bool | `false` | Enable token-based rate limiting |
| `tokens.per_user` | int | `100000` | Maximum tokens per user per hour |
| `tokens.per_team` | int | `1000000` | Maximum tokens per team per hour |

## Environment Variables

Add these to your `.env` file to customize rate limits without modifying configuration:

```bash
# Enable/disable rate limiting
AGENT_RATE_LIMITING=true

# User limits
AGENT_RATE_LIMIT_USER_REQUESTS=100
AGENT_RATE_LIMIT_USER_PERIOD=60

# Team limits
AGENT_RATE_LIMIT_TEAM_REQUESTS=1000
AGENT_RATE_LIMIT_TEAM_PERIOD=60

# Agent limits
AGENT_RATE_LIMIT_AGENT_REQUESTS=500
AGENT_RATE_LIMIT_AGENT_PERIOD=60

# Token limits
AGENT_TOKEN_RATE_LIMITING=false
AGENT_TOKEN_LIMIT_USER=100000
AGENT_TOKEN_LIMIT_TEAM=1000000
```

## Instance Configuration

Each rate limiter can be configured at instantiation time with an options array:

```php
$limiter = new UserRateLimiter([
    'max_requests' => 100,
    'window_seconds' => 60,
    'cache_store' => 'redis',
    'prefix' => 'my_app:rate_limit',
]);
```

### Instance Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `max_requests` | int | `60` | Maximum requests in window |
| `window_seconds` | int | `60` | Window duration in seconds |
| `cache_store` | string | `null` | Laravel cache store (null uses default) |
| `prefix` | string | varies | Cache key prefix |

### Default Prefixes by Limiter

| Limiter | Default Prefix |
|---------|----------------|
| `AgentRateLimiter` | `rate_limit:agent` |
| `TeamRateLimiter` | `rate_limit:team` |
| `UserRateLimiter` | `rate_limit:user` |
| `TokenRateLimiter` | `rate_limit:tokens` |

## Fluent Configuration

All limiters support a fluent API for configuration:

```php
$limiter = (new TeamRateLimiter())
    ->maxRequests(1000)
    ->windowSeconds(60);

// Or use convenient shortcuts
$limiter = (new TeamRateLimiter())->perMinute(1000);
$limiter = (new TeamRateLimiter())->perHour(10000);
$limiter = (new TeamRateLimiter())->perDay(100000);
```

### Fluent Methods

| Method | Description |
|--------|-------------|
| `configure(array $config)` | Apply multiple options at once |
| `maxRequests(int $max)` | Set maximum requests |
| `windowSeconds(int $seconds)` | Set window duration |
| `perMinute(int $requests)` | Set requests per minute |
| `perHour(int $requests)` | Set requests per hour |
| `perDay(int $requests)` | Set requests per day |
| `onLimitExceeded(Closure $callback)` | Set callback for limit exceeded |

## Subscription Tier Configuration

A common pattern is to configure different limits based on subscription tiers:

```php
// config/agent-orchestrator.php

'rate_limiting' => [
    'enabled' => true,

    'tiers' => [
        'free' => [
            'requests_per_minute' => 10,
            'requests_per_hour' => 100,
            'tokens_per_day' => 50000,
        ],
        'pro' => [
            'requests_per_minute' => 100,
            'requests_per_hour' => 1000,
            'tokens_per_day' => 500000,
        ],
        'enterprise' => [
            'requests_per_minute' => 1000,
            'requests_per_hour' => 10000,
            'tokens_per_day' => 5000000,
        ],
    ],
],
```

### Using Tier Configuration

```php
class TieredRateLimitService
{
    public function getLimiterForTeam(Team $team): TeamRateLimiter
    {
        $tier = $team->subscription_tier ?? 'free';
        $config = config("agent-orchestrator.rate_limiting.tiers.{$tier}");

        return (new TeamRateLimiter())
            ->perMinute($config['requests_per_minute']);
    }

    public function getTokenLimiterForTeam(Team $team): TokenRateLimiter
    {
        $tier = $team->subscription_tier ?? 'free';
        $config = config("agent-orchestrator.rate_limiting.tiers.{$tier}");

        return TokenRateLimiter::forTeam($team, [
            'max_tokens' => $config['tokens_per_day'],
            'window_seconds' => 86400,
        ]);
    }
}
```

## Cache Store Configuration

Rate limiters use Laravel's cache system. Configure the cache store for optimal performance:

```php
// config/cache.php

'stores' => [
    'rate_limiting' => [
        'driver' => 'redis',
        'connection' => 'rate_limiting',
        'prefix' => 'rate_limit',
    ],
],
```

```php
// config/database.php

'redis' => [
    'rate_limiting' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_RATE_LIMIT_DB', 2),
    ],
],
```

### Using a Dedicated Cache Store

```php
$limiter = new UserRateLimiter([
    'cache_store' => 'rate_limiting',
    'max_requests' => 100,
    'window_seconds' => 60,
]);
```

## Per-Agent Configuration

Configure different limits for different agents:

```php
// config/agent-orchestrator.php

'agents' => [
    'customer-support' => [
        'rate_limit' => [
            'requests' => 1000,
            'period' => 60,
        ],
    ],
    'code-assistant' => [
        'rate_limit' => [
            'requests' => 100,
            'period' => 60,
        ],
    ],
    'data-analyzer' => [
        'rate_limit' => [
            'requests' => 50,
            'period' => 60,
        ],
    ],
],
```

### Dynamic Agent Configuration

```php
class AgentRateLimitFactory
{
    public function createForAgent(string $agentName): AgentRateLimiter
    {
        $config = config("agent-orchestrator.agents.{$agentName}.rate_limit", [
            'requests' => config('agent-orchestrator.rate_limiting.per_agent.requests'),
            'period' => config('agent-orchestrator.rate_limiting.per_agent.period'),
        ]);

        return (new AgentRateLimiter())
            ->maxRequests($config['requests'])
            ->windowSeconds($config['period']);
    }
}
```

## Callback Configuration

Register callbacks to execute when limits are exceeded:

```php
$limiter = (new TeamRateLimiter())
    ->perMinute(1000)
    ->onLimitExceeded(function (string $key, int $retryAfter) {
        // Log the event
        Log::warning("Rate limit exceeded for team: {$key}", [
            'retry_after' => $retryAfter,
        ]);

        // Send notification
        Notification::send(
            Team::find($key)->owner,
            new RateLimitExceededNotification($retryAfter)
        );

        // Record metric
        Metrics::increment('rate_limit.exceeded', [
            'limiter' => 'team',
            'key' => $key,
        ]);
    });
```

## Disabling Rate Limiting

### Globally

```bash
# .env
AGENT_RATE_LIMITING=false
```

### Per Request

```php
class AdminController extends Controller
{
    public function unlimitedAction(Request $request)
    {
        // Skip rate limiting for admin actions
        if (config('agent-orchestrator.rate_limiting.enabled') === false) {
            return $this->performAction();
        }

        // Normal rate-limited flow
        $limiter->attemptUser($request->user());
        return $this->performAction();
    }
}
```

### For Testing

```php
// tests/Feature/AgentTest.php

public function test_agent_can_run_without_rate_limits()
{
    config(['agent-orchestrator.rate_limiting.enabled' => false]);

    // Test without rate limiting
    $response = $this->postJson('/api/agents/invoke', [
        'prompt' => 'Test prompt',
    ]);

    $response->assertOk();
}
```

## Configuration Best Practices

1. **Use Environment Variables**: Keep rate limits configurable per environment.

2. **Start Conservative**: Begin with lower limits and increase based on usage patterns.

3. **Use Redis**: For production, use Redis as the cache store for atomic operations.

4. **Separate Token and Request Limits**: Track both to prevent abuse and control costs.

5. **Configure Per Tier**: Different subscription tiers should have different limits.

6. **Monitor and Adjust**: Use the monitoring capabilities to tune limits over time.
