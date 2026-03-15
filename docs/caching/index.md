# Caching

Agent Orchestrator provides a comprehensive caching system designed to reduce API costs, improve response times, and enhance the overall performance of your AI agents. The caching layer is modular, configurable, and supports multiple cache backends through Laravel's cache abstraction.

## Why Cache AI Agent Operations?

AI agent operations involve expensive API calls to LLM providers. Caching delivers significant benefits:

| Benefit | Impact |
|---------|--------|
| **Cost Reduction** | Avoid redundant API calls for identical requests |
| **Latency Improvement** | Return cached responses in milliseconds instead of seconds |
| **Rate Limit Management** | Reduce API calls to stay within provider limits |
| **Embedding Efficiency** | Cache vector embeddings to avoid recomputation |
| **Tool Result Reuse** | Cache deterministic tool outputs |

## Cache Components

Agent Orchestrator includes three specialized cache classes:

### ResponseCache

Caches complete agent responses for identical inputs. Best suited for:

- FAQ-style queries with predictable answers
- Agents with deterministic outputs
- High-traffic scenarios with repeated questions

```php
use AgenticOrchestrator\Caching\ResponseCache;

$cache = new ResponseCache([
    'enabled' => true,
    'ttl' => 3600,
    'cache_store' => 'redis',
]);

$response = $cache->remember(
    agentName: 'support-agent',
    input: 'What are your business hours?',
    context: ['user_id' => 123],
    model: 'gpt-4o',
    callback: fn() => $agent->run('What are your business hours?'),
);
```

[Learn more about ResponseCache](./response-cache.md)

### EmbeddingCache

Caches vector embeddings to reduce embedding API costs. Essential for:

- RAG (Retrieval-Augmented Generation) pipelines
- Semantic search operations
- Document processing workflows

```php
use AgenticOrchestrator\Caching\EmbeddingCache;

$cache = new EmbeddingCache([
    'enabled' => true,
    'ttl' => 86400 * 7, // 7 days
    'cache_store' => 'redis',
]);

$text = 'Product documentation content...';
$embedding = $cache->remember(
    text: $text,
    model: 'text-embedding-3-small',
    dimensions: 1536,
    callback: fn() => $embeddingService->embed($text),
);
```

[Learn more about EmbeddingCache](./embedding-cache.md)

### ToolResultCache

Caches tool execution results for deterministic tools. Ideal for:

- Database lookups
- API calls to external services
- Computation-heavy operations

```php
use AgenticOrchestrator\Caching\ToolResultCache;

$cache = new ToolResultCache([
    'enabled' => true,
    'ttl' => 300,
    'cache_store' => 'redis',
]);

$result = $cache->remember(
    toolName: 'get_product',
    arguments: ['id' => 'prod_123'],
    teamId: $team->id,
    callback: fn() => $tool->execute(['id' => 'prod_123']),
);
```

[Learn more about ToolResultCache](./tool-cache.md)

## Cache Key Generation

All cache classes use `CacheKeyGenerator` to create consistent, collision-free cache keys:

```php
use AgenticOrchestrator\Caching\CacheKeyGenerator;

$keyGenerator = new CacheKeyGenerator('my_app');

// Generate keys for different data types
$responseKey = $keyGenerator->forResponse('agent', 'input', $context, 'gpt-4o');
$embeddingKey = $keyGenerator->forEmbedding('text', 'model', 1536);
$toolKey = $keyGenerator->forToolResult('tool', $arguments, $teamId);
```

[Learn more about CacheKeyGenerator](./cache-keys.md)

## Quick Configuration

Enable caching in your `config/agent-orchestrator.php`:

```php
'caching' => [
    'responses' => [
        'enabled' => env('AGENT_CACHE_RESPONSES', false),
        'ttl' => env('AGENT_CACHE_RESPONSE_TTL', 3600),
        'store' => env('AGENT_CACHE_STORE', 'redis'),
    ],

    'embeddings' => [
        'enabled' => env('AGENT_CACHE_EMBEDDINGS', true),
        'ttl' => env('AGENT_CACHE_EMBEDDING_TTL', 86400 * 7),
        'store' => env('AGENT_CACHE_STORE', 'redis'),
    ],

    'tools' => [
        'enabled' => env('AGENT_CACHE_TOOLS', true),
        'ttl' => env('AGENT_CACHE_TOOL_TTL', 300),
        'store' => env('AGENT_CACHE_STORE', 'redis'),
    ],
],
```

[Learn more about Configuration](./configuration.md)

## Cache Statistics

All cache classes provide built-in statistics tracking:

```php
$stats = $cache->getStats();

// Returns:
// [
//     'hits' => 150,
//     'misses' => 50,
//     'stores' => 50,
//     'total_requests' => 200,
//     'hit_rate' => 75.0,
// ]
```

## Recommended Cache Store

For production environments, Redis is the recommended cache store:

```env
CACHE_STORE=redis
AGENT_CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Redis provides:

- High performance for read/write operations
- TTL support with automatic expiration
- Atomic operations for concurrent access
- Persistence options for durability

## Multi-Tenancy Support

Cache keys automatically include team context when provided, ensuring data isolation:

```php
// Keys include team_id for multi-tenant isolation
$key = $keyGenerator->forToolResult('search', $args, teamId: 42);
// Result: agent_orchestrator:tool:abc123... (includes team_id in hash)
```

## Best Practices

### 1. Enable Caching Progressively

Start with embedding caching (highest ROI), then add tool caching, and finally response caching:

```php
// Phase 1: Embedding caching (always beneficial)
'embeddings' => ['enabled' => true, 'ttl' => 604800],

// Phase 2: Tool caching (for deterministic tools)
'tools' => ['enabled' => true, 'ttl' => 300],

// Phase 3: Response caching (for predictable queries)
'responses' => ['enabled' => true, 'ttl' => 3600],
```

### 2. Set Appropriate TTLs

| Cache Type | Recommended TTL | Rationale |
|------------|-----------------|-----------|
| Responses | 1-24 hours | Balance freshness with cost savings |
| Embeddings | 7-30 days | Embeddings rarely change |
| Tool Results | 5-60 minutes | Depends on data volatility |

### 3. Monitor Cache Performance

Regularly check hit rates and adjust configuration:

```php
// Log cache statistics periodically
Log::info('Cache stats', [
    'response_cache' => $responseCache->getStats(),
    'embedding_cache' => $embeddingCache->getStats(),
    'tool_cache' => $toolCache->getStats(),
]);
```

### 4. Disable Caching for Development

Use environment variables to disable caching during development:

```env
# .env.local
AGENT_CACHE_RESPONSES=false
AGENT_CACHE_EMBEDDINGS=false
AGENT_CACHE_TOOLS=false
```

## Next Steps

- [ResponseCache](./response-cache.md) - Cache agent responses
- [EmbeddingCache](./embedding-cache.md) - Cache vector embeddings
- [ToolResultCache](./tool-cache.md) - Cache tool results
- [CacheKeyGenerator](./cache-keys.md) - Understand key generation
- [Configuration](./configuration.md) - Full configuration reference
