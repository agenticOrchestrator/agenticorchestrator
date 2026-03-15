# ResponseCache

The `ResponseCache` class caches complete agent responses for identical inputs. This is particularly effective for agents that handle repetitive queries with predictable answers, such as FAQ bots, customer support agents, or information retrieval systems.

## Overview

`ResponseCache` stores serialized `AgentResponse` objects and returns them when the same input, context, model, and agent combination is requested again.

```php
use AgenticOrchestrator\Caching\ResponseCache;
use AgenticOrchestrator\Agents\AgentResponse;

$cache = new ResponseCache([
    'enabled' => true,
    'ttl' => 3600,
    'cache_store' => 'redis',
]);
```

## Basic Usage

### The remember() Method

The primary method for caching responses is `remember()`. It returns a cached response if available, or executes the callback and caches the result:

```php
$response = $cache->remember(
    agentName: 'support-agent',
    input: 'What are your business hours?',
    context: ['locale' => 'en_US'],
    model: 'gpt-4o',
    callback: function () use ($agent) {
        return $agent->run('What are your business hours?');
    },
);

// $response is an AgentResponse object
echo $response->content;
```

### Direct Cache Access

You can also interact with the cache directly:

```php
use AgenticOrchestrator\Caching\CacheKeyGenerator;

$keyGenerator = new CacheKeyGenerator();
$key = $keyGenerator->forResponse('support-agent', 'input', $context, 'gpt-4o');

// Check if cached
if ($cache->has('support-agent', 'What are your hours?', $context, 'gpt-4o')) {
    $response = $cache->get($key);
}

// Store manually
$cache->put($key, $response, ttl: 7200);

// Remove from cache
$cache->forget('support-agent', 'What are your hours?', $context, 'gpt-4o');
```

## Configuration

### Constructor Options

```php
$cache = new ResponseCache([
    // Enable or disable caching globally
    'enabled' => true,

    // Default TTL in seconds
    'ttl' => 3600,

    // Laravel cache store to use
    'cache_store' => 'redis',

    // Custom key prefix
    'prefix' => 'my_app_responses',

    // Per-agent configuration
    'agents' => [
        'faq-agent' => [
            'enabled' => true,
            'ttl' => 86400, // 24 hours for FAQ responses
        ],
        'realtime-agent' => [
            'enabled' => false, // Disable caching for real-time data
        ],
    ],
]);
```

### Runtime Configuration

Configure the cache at runtime using the fluent interface:

```php
$cache
    ->configure(['ttl' => 7200])
    ->configureAgent('faq-agent', [
        'enabled' => true,
        'ttl' => 86400,
    ])
    ->configureAgent('analytics-agent', [
        'enabled' => false,
    ]);
```

## Agent-Specific Configuration

Different agents may have different caching requirements. Configure caching per agent:

```php
// FAQ agent - long cache, responses rarely change
$cache->configureAgent('faq-agent', [
    'enabled' => true,
    'ttl' => 86400 * 7, // 7 days
]);

// Weather agent - short cache, data changes frequently
$cache->configureAgent('weather-agent', [
    'enabled' => true,
    'ttl' => 300, // 5 minutes
]);

// Trading agent - no cache, real-time data required
$cache->configureAgent('trading-agent', [
    'enabled' => false,
]);
```

## Cache Key Components

Response cache keys are generated from four components:

| Component | Description | Impact on Caching |
|-----------|-------------|-------------------|
| `agentName` | The agent identifier | Different agents have separate caches |
| `input` | The user's input/query | Different inputs produce different keys |
| `context` | Request context array | Context differences create new entries |
| `model` | The LLM model used | Model changes invalidate cache |

### Context Normalization

The cache key generator automatically normalizes context by:

1. Removing volatile keys (`timestamp`, `request_id`, `session_id`)
2. Sorting keys alphabetically for consistent ordering

```php
// These produce the same cache key:
$context1 = ['user_id' => 1, 'locale' => 'en'];
$context2 = ['locale' => 'en', 'user_id' => 1, 'timestamp' => time()];
```

## Cached Response Metadata

Cached responses include metadata indicating they came from cache:

```php
$response = $cache->remember(...);

if ($response->getMeta('from_cache')) {
    $cachedAt = $response->getMeta('cached_at');
    echo "Response cached at: " . date('Y-m-d H:i:s', $cachedAt);
}
```

## Statistics and Monitoring

Track cache performance with built-in statistics:

```php
$stats = $cache->getStats();

// [
//     'hits' => 150,      // Requests served from cache
//     'misses' => 50,     // Requests that required API calls
//     'stores' => 50,     // Responses stored in cache
//     'total_requests' => 200,
//     'hit_rate' => 75.0, // Percentage
// ]

// Reset statistics
$cache->resetStats();
```

### Logging

The cache automatically logs operations at debug level:

```
[DEBUG] Response cache hit {"agent":"faq-agent","key":"agent_orchestrator:response:abc123"}
[DEBUG] Response cached {"agent":"faq-agent","key":"agent_orchestrator:response:def456"}
```

## Enable and Disable

Control caching at runtime:

```php
// Disable temporarily for debugging
$cache->disable();
$response = $agent->run($input); // Bypasses cache

// Re-enable
$cache->enable();

// Check status
if ($cache->isEnabled()) {
    // Caching is active
}
```

## Cache Invalidation

### Forget Specific Entry

Remove a specific cached response:

```php
$cache->forget(
    agentName: 'faq-agent',
    input: 'What are your hours?',
    context: [],
    model: 'gpt-4o',
);
```

### Flush Agent Cache

Request cache flush for all responses from an agent:

```php
// Note: Requires cache tags or pattern deletion support
$cache->flushAgent('faq-agent');
```

### Flush All

Clear all cached responses:

```php
$cache->flush();
```

## Serialization

Responses are serialized for storage and reconstructed when retrieved:

### Stored Data Structure

```php
[
    'content' => 'The response text...',
    'metadata' => ['key' => 'value'],
    'tool_calls' => [...],
    'usage' => [
        'prompt_tokens' => 100,
        'completion_tokens' => 50,
        'total_tokens' => 150,
    ],
    'cached' => true,
    'cached_at' => 1706745600,
]
```

### Reconstructed Response

The deserialized response includes cache metadata:

```php
$response = $cache->get($key);

$response->content;           // Original content
$response->getMeta('from_cache'); // true
$response->getMeta('cached_at');  // Unix timestamp
```

## Use Cases

### FAQ Bot

Cache common questions for instant responses:

```php
$cache = new ResponseCache([
    'enabled' => true,
    'ttl' => 86400 * 30, // 30 days
]);

$response = $cache->remember(
    'faq-bot',
    $userQuestion,
    ['category' => 'shipping'],
    'gpt-4o-mini',
    fn() => $faqAgent->answer($userQuestion),
);
```

### Multi-Locale Support

Cache responses per locale:

```php
$response = $cache->remember(
    'support-agent',
    'How do I reset my password?',
    ['locale' => app()->getLocale()], // Different cache per locale
    'gpt-4o',
    fn() => $agent->run($question),
);
```

### A/B Testing Models

Compare models while caching both:

```php
// Cache separately for each model
foreach (['gpt-4o', 'claude-3-5-sonnet'] as $model) {
    $response = $cache->remember(
        'test-agent',
        $input,
        $context,
        $model,
        fn() => $agent->withModel($model)->run($input),
    );
}
```

## Best Practices

### 1. Identify Cacheable Agents

Not all agents benefit from response caching:

| Agent Type | Cache? | Rationale |
|------------|--------|-----------|
| FAQ/Help | Yes | Responses rarely change |
| Search | Maybe | Depends on data freshness needs |
| Conversation | No | Context-dependent responses |
| Real-time | No | Data must be current |
| Creative | No | Variety is desired |

### 2. Set Appropriate TTLs

```php
'agents' => [
    'faq-agent' => ['ttl' => 86400 * 7],  // Static content
    'product-agent' => ['ttl' => 3600],    // Inventory can change
    'news-agent' => ['ttl' => 300],        // Frequent updates
],
```

### 3. Include Relevant Context

Ensure context includes all factors that affect the response:

```php
$context = [
    'locale' => $user->locale,
    'subscription_tier' => $user->tier,
    'feature_flags' => $user->features,
    // Don't include: timestamp, request_id, session_id
];
```

### 4. Monitor Hit Rates

Low hit rates indicate caching may not be effective:

```php
$stats = $cache->getStats();

if ($stats['hit_rate'] < 20) {
    Log::warning('Low cache hit rate', [
        'agent' => 'faq-agent',
        'hit_rate' => $stats['hit_rate'],
    ]);
}
```

## Integration Example

Complete integration with an agent:

```php
namespace App\Services;

use AgenticOrchestrator\Caching\ResponseCache;
use App\Agents\SupportAgent;

class CachedAgentService
{
    public function __construct(
        private ResponseCache $cache,
        private SupportAgent $agent,
    ) {}

    public function query(string $input, array $context = []): string
    {
        $response = $this->cache->remember(
            agentName: 'support-agent',
            input: $input,
            context: array_merge($context, [
                'locale' => app()->getLocale(),
            ]),
            model: $this->agent->getModel(),
            callback: fn() => $this->agent->run($input),
        );

        return $response->content;
    }

    public function getStats(): array
    {
        return $this->cache->getStats();
    }
}
```
