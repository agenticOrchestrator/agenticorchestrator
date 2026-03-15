# Memory Drivers

Agent Orchestrator provides multiple memory drivers to suit different application requirements. The `SessionDriver`, `CacheDriver`, and `DatabaseDriver` implement the `MemoryInterface` contract, ensuring consistent behavior across these storage backends. The `VectorMemoryDriver` provides a specialized API for semantic search and does not implement `MemoryInterface`.

## Driver Comparison

| Feature | Session | Cache | Database | Vector |
|---------|---------|-------|----------|--------|
| Implements MemoryInterface | Yes | Yes | Yes | No |
| Persistence | Request only | TTL-based | Permanent | Permanent |
| Search type | Keyword | Keyword | Keyword | Semantic |
| Multi-tenant | No | Yes | Yes | Yes |
| Scalability | Low | Medium | High | High |
| Setup complexity | None | Low | Medium | High |
| Best for | Testing | Development | Production | RAG apps |
| MemoryManager integration | Yes | Yes | Yes | Yes |

## Session Driver

The session driver stores memory in PHP arrays, persisting only for the duration of a single HTTP request.

### Use Cases

- Unit and integration testing
- Single-turn chatbots
- Stateless API endpoints
- Development and debugging

### Configuration

```php
// config/agent-orchestrator.php
'memory' => [
    'default' => 'session',
    'drivers' => [
        'session' => [
            // No configuration required
        ],
    ],
],
```

### Behavior

```php
use AgenticOrchestrator\Memory\Drivers\SessionDriver;

$driver = new SessionDriver();

// Store data (in-memory array)
$driver->store('key', 'value', ['type' => 'fact']);

// Data available during request
$value = $driver->recall('key'); // Returns 'value'

// After request ends, all data is lost
```

### Search Capabilities

The session driver performs case-insensitive substring matching:

```php
$driver->store('fact_1', 'The user prefers dark mode');
$driver->store('fact_2', 'The user lives in New York');

$results = $driver->search('dark', limit: 5);
// Returns fact_1 with score 1.0 (no semantic scoring)
```

### Limitations

- No data persistence between requests
- No namespace isolation
- Not suitable for production use
- No semantic search capability

---

## Cache Driver

The cache driver uses Laravel's cache system for persistence, supporting TTL-based expiration and multiple cache stores.

### Use Cases

- Development environments
- Short-lived conversational context
- Temporary user sessions
- High-performance read scenarios

### Configuration

```php
// config/agent-orchestrator.php
'memory' => [
    'default' => 'cache',
    'drivers' => [
        'cache' => [
            'store' => env('AGENT_CACHE_STORE', 'default'),
            'ttl' => env('AGENT_CACHE_TTL', 3600),
            'prefix' => 'agent_memory:',
        ],
    ],
],
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `store` | string | `'default'` | Laravel cache store name |
| `ttl` | integer | `3600` | Time-to-live in seconds |
| `prefix` | string | `'agent_memory:'` | Cache key prefix |

### Constructor

The cache driver constructor accepts named parameters:

```php
use AgenticOrchestrator\Memory\Drivers\CacheDriver;

$driver = new CacheDriver(
    store: 'redis',      // Cache store name (default: 'default')
    ttl: 3600,           // Time-to-live in seconds (default: 3600)
    prefix: 'agent_memory:', // Key prefix (default: 'agent_memory:')
);
```

### Usage

```php
use AgenticOrchestrator\Facades\Memory;

// Default cache driver
$memory = Memory::driver('cache');

// Store with automatic TTL
$memory->store('session_context', [
    'topic' => 'weather',
    'location' => 'San Francisco',
]);

// Data expires after TTL
$context = $memory->recall('session_context');
```

### Namespace Support

You can set the namespace via the `Memory` wrapper or directly on the driver:

```php
// Via Memory facade (recommended)
$memory = Memory::driver('cache')
    ->forNamespace('team_1:agent_assistant:user_42');

// Keys are prefixed: agent_memory:team_1:agent_assistant:user_42:key
$memory->store('preference', 'concise');

// Or directly on the driver
use AgenticOrchestrator\Memory\Drivers\CacheDriver;

$driver = new CacheDriver(store: 'redis', ttl: 3600);
$driver->setNamespace('team_1:agent_assistant:user_42');
```

### Conversation History

The cache driver stores conversation history as a serialized array:

```php
use AgenticOrchestrator\Conversations\Message;

$memory = Memory::driver('cache');

// Messages stored as serialized arrays
$memory->addMessage(Message::user('Hello'));
$memory->addMessage(Message::assistant('Hi there!'));

// Retrieve as Message objects
$history = $memory->getConversationHistory(50);
// Returns array of Message instances
```

The cache driver maintains a maximum of 100 messages to prevent unbounded growth.

### Key Tracking

The cache driver tracks all stored keys to support the `clear()` operation:

```php
$memory->store('key1', 'value1');
$memory->store('key2', 'value2');

// Clear removes all tracked keys
$memory->clear();

$memory->has('key1'); // false
```

---

## Database Driver

The database driver provides permanent storage with full multi-tenancy support, using Laravel's query builder for efficient data access.

### Use Cases

- Production applications
- Multi-tenant SaaS platforms
- Long-term memory retention
- Audit and compliance requirements

### Configuration

```php
// config/agent-orchestrator.php
'memory' => [
    'default' => 'database',
    'drivers' => [
        'database' => [
            'connection' => env('AGENT_DB_CONNECTION'),
            'table' => 'agent_memories',
        ],
    ],
],
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `connection` | string\|null | `null` | Database connection (null = default) |
| `table` | string | `'agent_memories'` | Table name for memories |

### Constructor

The database driver constructor accepts a configuration array:

```php
use AgenticOrchestrator\Memory\Drivers\DatabaseDriver;

$driver = new DatabaseDriver([
    'connection' => null,           // Database connection (null = default)
    'table' => 'agent_memories',    // Table name
]);
```

### Database Schema

The driver uses the `agent_memories` table with the following structure:

```php
Schema::create('agent_memories', function (Blueprint $table) {
    $table->id();
    $table->string('namespace')->index();
    $table->string('key')->index();
    $table->string('type')->default('general');
    $table->text('content');
    $table->json('metadata')->nullable();

    // Multi-tenancy columns
    $table->nullableMorphs('tenant');
    $table->string('agent_name')->nullable()->index();
    $table->foreignId('session_id')->nullable();
    $table->foreignId('user_id')->nullable();

    // Retrieval scoring
    $table->float('importance')->default(0.5);
    $table->unsignedInteger('access_count')->default(0);
    $table->timestamp('last_accessed_at')->nullable();

    // Expiration
    $table->timestamp('expires_at')->nullable();

    $table->timestamps();

    $table->unique(['namespace', 'key']);
});
```

### Multi-Tenancy Support

```php
use AgenticOrchestrator\Memory\Drivers\DatabaseDriver;
use AgenticOrchestrator\MultiTenancy\TenantManager;

$driver = new DatabaseDriver(['table' => 'agent_memories']);
$driver->setTenantManager(app(TenantManager::class));

// All queries automatically scoped to current tenant
$driver->store('key', 'value');
```

Or scope explicitly using `forTenant()`, which returns a new driver instance scoped to the tenant:

```php
$scopedDriver = $driver->forTenant($team);
$scopedDriver->store('team_setting', $value);
```

You can also set the namespace using `setNamespace()`:

```php
$driver->setNamespace('custom_namespace');
```

### Memory Metadata

Store additional metadata for filtering and retrieval:

```php
$driver->store('user_fact', 'Prefers morning meetings', [
    'type' => 'preference',
    'agent_name' => 'scheduler',
    'user_id' => 42,
    'importance' => 0.8,
    'expires_at' => now()->addDays(30),
]);
```

### Access Tracking

The database driver automatically tracks access patterns:

```php
// Each recall() updates:
// - access_count (incremented)
// - last_accessed_at (current timestamp)

$value = $driver->recall('frequently_used');
// access_count: 1 -> 2
// last_accessed_at: updated
```

This enables intelligent retrieval ordering in searches:

```php
$results = $driver->search('preference');
// Results ordered by: importance DESC, last_accessed_at DESC
```

### Expiration Support

Memories can have expiration dates:

```php
$driver->store('temp_context', $data, [
    'expires_at' => now()->addHours(2),
]);

// Expired memories are excluded from recall() and search()
$value = $driver->recall('temp_context');
// Returns null after 2 hours
```

Clean up expired memories:

```php
$deleted = $driver->cleanup();
// Returns count of deleted records
```

### All Keys and Values

```php
// Get all keys in namespace
$keys = $driver->keys();

// Get all key-value pairs
$all = $driver->all();
```

---

## Vector Memory Driver

The vector driver enables semantic search using embeddings, ideal for RAG (Retrieval-Augmented Generation) applications.

The `VectorMemoryDriver` provides a specialized API for vector-based semantic operations. When used through the `MemoryManager`, it is wrapped in a `VectorMemoryAdapter` that implements the standard `MemoryInterface`. You can also instantiate `VectorMemoryDriver` directly for advanced vector-specific operations like `similar()`, `remember()`, and `setMany()`.

### Use Cases

- Knowledge base retrieval
- Semantic document search
- Similar memory finding
- Context-aware responses

### Constructor

The vector driver requires an embedding provider and a vector store:

```php
use AgenticOrchestrator\Memory\Drivers\VectorMemoryDriver;
use AgenticOrchestrator\Embeddings\Contracts\EmbeddingProviderInterface;
use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;

$driver = new VectorMemoryDriver(
    embeddings: $embeddingProvider, // EmbeddingProviderInterface instance
    store: $vectorStore,            // VectorStoreInterface instance
);
```

### API Differences

The vector driver uses different method names from `MemoryInterface`:

| MemoryInterface | VectorMemoryDriver | Description |
|-----------------|-------------------|-------------|
| `store()` | `set()` | Store a value |
| `recall()` | `get()` | Retrieve a value |
| `clear()` | `flush()` | Clear all memory |
| `forget()` | `forget()` | Remove a key (returns `bool`) |
| `search()` | `search()` | Search (adds `$threshold` parameter) |

### Basic Usage

```php
// Store a value
$driver->set('key', 'value', ttl: 3600);

// Retrieve a value
$value = $driver->get('key', default: null);

// Check existence
$exists = $driver->has('key');

// Remove a key
$deleted = $driver->forget('key');

// Clear namespace
$driver->flush();
```

### Semantic Search

```php
// Search with similarity threshold
$results = $driver->search(
    query: 'What is machine learning?',
    limit: 5,
    threshold: 0.7  // Minimum similarity score (0-1)
);

// Results are VectorSearchResult objects
foreach ($results as $result) {
    echo $result->content;
    echo $result->score;
}
```

### Additional Methods

```php
// Auto-generate key when storing
$key = $driver->remember('Important fact about user', [
    'category' => 'user_facts',
]);

// Batch operations
$driver->setMany([
    'key1' => 'value1',
    'key2' => 'value2',
]);

$values = $driver->getMany(['key1', 'key2']);

// Find similar memories
$similar = $driver->similar('reference_key', limit: 5);
```

### Namespace Scoping

```php
// Set namespace (uses fluent interface)
$driver->namespace('tenant_1:agent_assistant');

// Get current namespace
$ns = $driver->getNamespace();
```

### Configuration

```php
// config/agent-orchestrator.php
'memory' => [
    'drivers' => [
        'vector' => [
            'store' => env('AGENT_VECTOR_STORE', 'pinecone'),
            'embedding_provider' => env('AGENT_EMBEDDING_PROVIDER', 'openai'),
            'embedding_model' => env('AGENT_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'dimensions' => env('AGENT_EMBEDDING_DIMENSIONS', 1536),
        ],
    ],
],

'vector_stores' => [
    'pinecone' => [
        'api_key' => env('PINECONE_API_KEY'),
        'environment' => env('PINECONE_ENVIRONMENT'),
        'index' => env('PINECONE_INDEX'),
    ],
    // Additional stores...
],
```

See [Vector Memory](vector-memory.md) for complete documentation.

---

## Choosing a Driver

### Decision Matrix

| Requirement | Recommended Driver |
|-------------|-------------------|
| Testing/development | `session` |
| Temporary context | `cache` |
| Multi-tenant production | `database` |
| Semantic search needed | `vector` |
| High read performance | `cache` with Redis |
| Audit requirements | `database` |
| Knowledge retrieval | `vector` |

### Hybrid Approaches

You can use different drivers for different purposes:

```php
use AgenticOrchestrator\Facades\Memory;
use AgenticOrchestrator\Memory\Drivers\DatabaseDriver;
use AgenticOrchestrator\Memory\Drivers\VectorMemoryDriver;

// Fast session context (via MemoryManager)
$sessionMemory = Memory::driver('cache')
    ->forNamespace('session:' . $sessionId);

// Persistent user preferences (direct instantiation required)
$databaseDriver = new DatabaseDriver(['table' => 'agent_memories']);
$databaseDriver->setNamespace('user:' . $userId);

// Semantic knowledge base (direct instantiation required)
$vectorDriver = new VectorMemoryDriver(
    embeddings: $embeddingProvider,
    store: $vectorStore,
);
$vectorDriver->namespace('knowledge');
```

### Performance Considerations

| Driver | Read Latency | Write Latency | Concurrent Access |
|--------|--------------|---------------|-------------------|
| Session | ~0ms | ~0ms | Single request |
| Cache (Redis) | 1-5ms | 1-5ms | Excellent |
| Cache (File) | 5-20ms | 10-50ms | Poor |
| Database | 5-50ms | 10-100ms | Excellent |
| Vector | 50-500ms | 100-1000ms | Good |

## Next Steps

- [Configuration](configuration.md) - Configure drivers per agent
- [Vector Memory](vector-memory.md) - Semantic search setup
- [Custom Drivers](custom-drivers.md) - Build your own driver
