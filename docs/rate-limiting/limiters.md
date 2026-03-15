# Rate Limiters

The Agent Orchestrator provides four specialized rate limiters, each designed for different scoping requirements. All limiters extend a common base class and share a consistent API.

## Base Rate Limiter

All rate limiters inherit from `RateLimiter`, which provides the core functionality:

### Common Methods

| Method | Description |
|--------|-------------|
| `configure(array $config)` | Configure the limiter with options |
| `maxRequests(int $max)` | Set maximum requests allowed |
| `windowSeconds(int $seconds)` | Set time window duration |
| `perMinute(int $requests)` | Shorthand for requests per minute |
| `perHour(int $requests)` | Shorthand for requests per hour |
| `perDay(int $requests)` | Shorthand for requests per day |
| `check(string $key)` | Check if request is allowed without incrementing |
| `attempt(string $key)` | Check and increment if allowed |
| `execute(string $key, Closure $callback)` | Execute callback if allowed |
| `remaining(string $key)` | Get remaining requests |
| `retryAfter(string $key)` | Get seconds until limit resets |
| `reset(string $key)` | Reset the counter for a key |
| `status(string $key)` | Get complete status information |

### Configuration Options

```php
$limiter = new UserRateLimiter([
    'max_requests' => 100,      // Maximum requests in window
    'window_seconds' => 60,     // Window duration in seconds
    'cache_store' => 'redis',   // Laravel cache store to use
    'prefix' => 'rate_limit',   // Cache key prefix
]);
```

---

## AgentRateLimiter

Limits requests on a per-agent basis. Use this limiter to prevent a single agent from consuming disproportionate resources.

### Namespace

```php
use AgenticOrchestrator\RateLimiting\AgentRateLimiter;
```

### Creating an Agent Limiter

```php
// Using the static factory
$limiter = AgentRateLimiter::for('customer-support-agent', [
    'max_requests' => 500,
    'window_seconds' => 60,
]);

// Using the constructor with fluent configuration
$limiter = (new AgentRateLimiter())
    ->perMinute(500);
```

### Agent-Specific Methods

| Method | Description |
|--------|-------------|
| `checkAgent(string $agentName)` | Check if agent request is allowed |
| `attemptAgent(string $agentName)` | Attempt an agent request |
| `remainingForAgent(string $agentName)` | Get remaining requests for agent |
| `agentStatus(string $agentName)` | Get status for agent |

### Example Usage

```php
use AgenticOrchestrator\RateLimiting\AgentRateLimiter;

class AgentController extends Controller
{
    public function invoke(Request $request, string $agentName)
    {
        $limiter = AgentRateLimiter::for($agentName)
            ->perMinute(500);

        // Check remaining capacity before expensive operations
        $remaining = $limiter->remainingForAgent($agentName);

        if ($remaining < 10) {
            Log::warning("Agent {$agentName} approaching rate limit");
        }

        // Attempt the request
        $limiter->attemptAgent($agentName);

        return $this->runAgent($agentName, $request->input('prompt'));
    }
}
```

### Use Cases

- Prevent runaway agents from exhausting API quotas
- Implement different limits for different agent types
- Throttle experimental or development agents

---

## TeamRateLimiter

Limits requests on a per-team or per-tenant basis. Essential for multi-tenant applications with subscription tiers.

### Namespace

```php
use AgenticOrchestrator\RateLimiting\TeamRateLimiter;
```

### Creating a Team Limiter

```php
// Using the static factory
$limiter = TeamRateLimiter::for($team->id, [
    'max_requests' => 1000,
    'window_seconds' => 60,
]);

// Using an Eloquent model directly
$limiter = (new TeamRateLimiter())
    ->perHour(10000);

$limiter->attemptTeam($team);
```

### Team-Specific Methods

| Method | Description |
|--------|-------------|
| `checkTeam(int\|string\|object $team)` | Check if team request is allowed |
| `attemptTeam(int\|string\|object $team)` | Attempt a team request |
| `remainingForTeam(int\|string\|object $team)` | Get remaining requests for team |
| `teamStatus(int\|string\|object $team)` | Get status for team |

### Flexible Team Resolution

The TeamRateLimiter accepts various input types for team identification:

```php
// Using team ID directly
$limiter->attemptTeam(123);
$limiter->attemptTeam('team-abc');

// Using an Eloquent model
$limiter->attemptTeam($team);  // Uses $team->id or $team->getKey()

// Using any object with id property
$limiter->attemptTeam((object) ['id' => 456]);
```

### Subscription Tier Example

```php
use AgenticOrchestrator\RateLimiting\TeamRateLimiter;

class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $team = $request->user()->currentTeam;

        // Configure limits based on subscription tier
        $limits = match($team->subscription_tier) {
            'free' => ['requests' => 100, 'period' => 3600],
            'pro' => ['requests' => 1000, 'period' => 3600],
            'enterprise' => ['requests' => 10000, 'period' => 3600],
            default => ['requests' => 50, 'period' => 3600],
        };

        $limiter = (new TeamRateLimiter())
            ->maxRequests($limits['requests'])
            ->windowSeconds($limits['period']);

        $limiter->attemptTeam($team);

        return $next($request);
    }
}
```

---

## UserRateLimiter

Limits requests on a per-user basis. Protects against individual user abuse while allowing team capacity to be shared.

### Namespace

```php
use AgenticOrchestrator\RateLimiting\UserRateLimiter;
```

### Creating a User Limiter

```php
// Using the static factory
$limiter = UserRateLimiter::for($user->id, [
    'max_requests' => 100,
    'window_seconds' => 60,
]);

// Using an Eloquent model directly
$limiter = (new UserRateLimiter())
    ->perMinute(100);

$limiter->attemptUser(auth()->user());
```

### User-Specific Methods

| Method | Description |
|--------|-------------|
| `checkUser(int\|string\|object $user)` | Check if user request is allowed |
| `attemptUser(int\|string\|object $user)` | Attempt a user request |
| `remainingForUser(int\|string\|object $user)` | Get remaining requests for user |
| `userStatus(int\|string\|object $user)` | Get status for user |

### Flexible User Resolution

```php
// Using user ID directly
$limiter->attemptUser(42);
$limiter->attemptUser('user-uuid-here');

// Using Laravel's authenticated user
$limiter->attemptUser(auth()->user());

// Using any object with id property
$limiter->attemptUser($request->user());
```

### Combining User and Team Limits

```php
use AgenticOrchestrator\RateLimiting\UserRateLimiter;
use AgenticOrchestrator\RateLimiting\TeamRateLimiter;

class AgentInvocationService
{
    public function invoke(User $user, string $prompt): AgentResponse
    {
        // Check user limit first (lower limit, faster to hit)
        $userLimiter = (new UserRateLimiter())->perMinute(100);
        $userLimiter->attemptUser($user);

        // Then check team limit (shared pool)
        $teamLimiter = (new TeamRateLimiter())->perMinute(1000);
        $teamLimiter->attemptTeam($user->currentTeam);

        // Both passed, proceed with the request
        return $this->agent->run($prompt);
    }
}
```

---

## TokenRateLimiter

Limits based on actual token consumption rather than request count. This is essential for cost control since LLM pricing is token-based.

### Namespace

```php
use AgenticOrchestrator\RateLimiting\TokenRateLimiter;
```

### Creating a Token Limiter

```php
// Generic token limiter
$limiter = new TokenRateLimiter([
    'max_tokens' => 100000,
    'window_seconds' => 3600,
]);

// Scoped to a specific team
$limiter = TokenRateLimiter::forTeam($team, [
    'max_tokens' => 500000,
    'window_seconds' => 86400,  // Per day
]);

// Scoped to a specific user
$limiter = TokenRateLimiter::forUser($user, [
    'max_tokens' => 50000,
    'window_seconds' => 3600,
]);
```

### Token-Specific Methods

| Method | Description |
|--------|-------------|
| `maxTokens(int $max)` | Set maximum tokens allowed |
| `checkTokens(string $key, int $requested)` | Check if token usage is allowed |
| `recordUsage(string $key, int $input, int $output)` | Record token consumption |
| `attemptTokens(string $key, int $tokens)` | Attempt to use tokens |
| `remainingTokens(string $key)` | Get remaining tokens |
| `tokenStatus(string $key)` | Get token usage status |

### Recording Token Usage

```php
use AgenticOrchestrator\RateLimiting\TokenRateLimiter;

class TokenTrackingService
{
    private TokenRateLimiter $limiter;

    public function __construct()
    {
        $this->limiter = new TokenRateLimiter([
            'max_tokens' => 100000,
            'window_seconds' => 3600,
        ]);
    }

    public function runAgent(Team $team, string $prompt): AgentResponse
    {
        $key = "team:{$team->id}";

        // Estimate tokens before request (optional pre-check)
        $estimatedTokens = $this->estimateTokens($prompt);

        if (!$this->limiter->checkTokens($key, $estimatedTokens)) {
            throw new TokenLimitExceededException();
        }

        // Run the agent
        $response = $this->agent->run($prompt);

        // Record actual usage
        $this->limiter->recordUsage(
            $key,
            $response->usage->inputTokens,
            $response->usage->outputTokens
        );

        return $response;
    }

    public function getUsageStatus(Team $team): array
    {
        return $this->limiter->tokenStatus("team:{$team->id}");
    }
}
```

### Scoped Factory Methods

```php
// Per-team token limiting
$teamLimiter = TokenRateLimiter::forTeam($team, [
    'max_tokens' => 1000000,
    'window_seconds' => 86400,
]);

// Per-user token limiting
$userLimiter = TokenRateLimiter::forUser($user, [
    'max_tokens' => 100000,
    'window_seconds' => 86400,
]);

// These create properly namespaced cache keys:
// rate_limit:tokens:team:123
// rate_limit:tokens:user:456
```

### Token Status Response

The `tokenStatus()` method returns a detailed array:

```php
$status = $limiter->tokenStatus($key);

// Returns:
[
    'limit' => 100000,           // Maximum tokens allowed
    'remaining' => 75432,        // Tokens remaining in window
    'used' => 24568,             // Tokens used in window
    'retry_after' => 1847,       // Seconds until window resets
    'window_seconds' => 3600,    // Window duration
]
```

---

## Choosing the Right Limiter

| Scenario | Recommended Limiter |
|----------|---------------------|
| Prevent agent abuse | AgentRateLimiter |
| Subscription tier enforcement | TeamRateLimiter |
| Per-user fairness | UserRateLimiter |
| Cost control | TokenRateLimiter |
| Multi-tenant SaaS | TeamRateLimiter + UserRateLimiter |
| Complex billing | TokenRateLimiter + TeamRateLimiter |

### Combining Multiple Limiters

For production applications, combine multiple limiters for comprehensive protection:

```php
class ComprehensiveRateLimitService
{
    public function checkAllLimits(User $user, Team $team, string $agent): void
    {
        // User limit: 100 requests per minute
        (new UserRateLimiter())->perMinute(100)->attemptUser($user);

        // Team limit: 1000 requests per minute
        (new TeamRateLimiter())->perMinute(1000)->attemptTeam($team);

        // Agent limit: 500 requests per minute
        (new AgentRateLimiter())->perMinute(500)->attemptAgent($agent);

        // Token limit: 100k tokens per hour per team
        TokenRateLimiter::forTeam($team)
            ->maxTokens(100000)
            ->windowSeconds(3600);
    }
}
```
