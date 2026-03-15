# Configuration

Agent Orchestrator provides extensive configuration options through the `config/agent-orchestrator.php` file. This guide covers all available settings and their purposes.

## Configuration File Location

After publishing, the configuration file is located at:

```
config/agent-orchestrator.php
```

## Default Provider Settings

Configure the default LLM provider and model used when not specified on individual agents.

```php
'default_provider' => env('AGENT_DEFAULT_PROVIDER', 'openai'),
'default_model' => env('AGENT_DEFAULT_MODEL', 'gpt-4o'),
```

| Option | Description | Default |
|--------|-------------|---------|
| `default_provider` | The LLM provider to use by default | `openai` |
| `default_model` | The model identifier for the default provider | `gpt-4o` |

## LLM Providers

Configure credentials and settings for each supported LLM provider. All providers are managed through Prism PHP.

```php
'providers' => [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
        'base_url' => env('OPENAI_BASE_URL'),
    ],
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],
    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),
    ],
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
    ],
    'xai' => [
        'api_key' => env('XAI_API_KEY'),
    ],
    'deepseek' => [
        'api_key' => env('DEEPSEEK_API_KEY'),
    ],
    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
    ],
],
```

### Supported Providers

| Provider | Required Environment Variables |
|----------|-------------------------------|
| OpenAI | `OPENAI_API_KEY`, optionally `OPENAI_ORGANIZATION`, `OPENAI_BASE_URL` |
| Anthropic | `ANTHROPIC_API_KEY` |
| Gemini | `GEMINI_API_KEY` |
| Mistral | `MISTRAL_API_KEY` |
| Groq | `GROQ_API_KEY` |
| xAI | `XAI_API_KEY` |
| DeepSeek | `DEEPSEEK_API_KEY` |
| Ollama | `OLLAMA_BASE_URL` (defaults to localhost) |

## Memory Configuration

Configure the memory system for persisting conversation history and agent knowledge.

```php
'memory' => [
    'default' => env('AGENT_MEMORY_DRIVER', 'cache'),

    'drivers' => [
        'session' => [
            // Session memory is in-request only, no configuration needed
        ],

        'cache' => [
            'store' => env('AGENT_CACHE_STORE', 'redis'),
            'ttl' => env('AGENT_CACHE_TTL', 3600),
            'prefix' => 'agent_memory:',
        ],

        'database' => [
            'connection' => env('AGENT_DB_CONNECTION'),
            'table' => 'agent_memories',
        ],

        'vector' => [
            'store' => env('AGENT_VECTOR_STORE', 'pinecone'),
            'embedding_provider' => env('AGENT_EMBEDDING_PROVIDER', 'openai'),
            'embedding_model' => env('AGENT_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'dimensions' => env('AGENT_EMBEDDING_DIMENSIONS', 1536),
        ],

        'rag' => [
            'service' => env('AGENT_RAG_SERVICE'),
            'endpoint' => env('AGENT_RAG_ENDPOINT'),
            'username' => env('AGENT_RAG_USERNAME'),
            'password' => env('AGENT_RAG_PASSWORD'),
        ],
    ],
],
```

### Memory Drivers

| Driver | Description | Persistence | Best For |
|--------|-------------|-------------|----------|
| `session` | In-memory only | Request lifecycle | Testing, stateless agents |
| `cache` | Redis/Memcached | TTL-based | Short conversations |
| `database` | MySQL/PostgreSQL | Permanent | Long-term history |
| `vector` | Vector database | Permanent | Semantic search, RAG |
| `rag` | External RAG service | External | Enterprise knowledge bases |

### Cache Driver Options

| Option | Description | Default |
|--------|-------------|---------|
| `store` | Laravel cache store to use | `redis` |
| `ttl` | Time-to-live in seconds | `3600` (1 hour) |
| `prefix` | Cache key prefix | `agent_memory:` |

### Vector Driver Options

| Option | Description | Default |
|--------|-------------|---------|
| `store` | Vector database provider | `pinecone` |
| `embedding_provider` | Provider for generating embeddings | `openai` |
| `embedding_model` | Model for embeddings | `text-embedding-3-small` |
| `dimensions` | Vector dimensions | `1536` |

## Vector Stores

Configure vector database providers for semantic search capabilities.

```php
'vector_stores' => [
    'pinecone' => [
        'api_key' => env('PINECONE_API_KEY'),
        'environment' => env('PINECONE_ENVIRONMENT'),
        'index' => env('PINECONE_INDEX'),
    ],

    'weaviate' => [
        'host' => env('WEAVIATE_HOST', 'http://localhost:8080'),
        'api_key' => env('WEAVIATE_API_KEY'),
    ],

    'qdrant' => [
        'host' => env('QDRANT_HOST', 'http://localhost:6333'),
        'api_key' => env('QDRANT_API_KEY'),
        'collection' => env('QDRANT_COLLECTION', 'agent_memories'),
    ],

    'chroma' => [
        'host' => env('CHROMA_HOST', 'http://localhost:8000'),
        'collection' => env('CHROMA_COLLECTION', 'agent_memories'),
    ],

    'pgvector' => [
        'connection' => env('PGVECTOR_CONNECTION', 'pgsql'),
        'table' => 'embeddings',
    ],
],
```

### Supported Vector Stores

| Store | Type | Best For |
|-------|------|----------|
| Pinecone | Managed cloud | Production, scalability |
| Weaviate | Self-hosted/cloud | Hybrid search |
| Qdrant | Self-hosted/cloud | Performance |
| Chroma | Self-hosted | Development, prototyping |
| PgVector | PostgreSQL extension | Existing PostgreSQL setups |

## Multi-Tenancy Configuration

Configure team-based isolation for agents, memories, and conversations.

```php
'multi_tenancy' => [
    'enabled' => env('AGENT_MULTI_TENANCY', true),

    // Driver for tenant resolution
    'driver' => env('AGENT_TENANCY_DRIVER', 'auto'),

    // The Tenant/Team model class
    'model' => env('AGENT_TENANT_MODEL', \App\Models\Team::class),

    // The User model class
    'user_model' => env('AGENT_USER_MODEL', \App\Models\User::class),

    // User-to-tenant relationship name
    'user_relationship' => env('AGENT_USER_TENANT_RELATION', 'teams'),

    // Tenant model field mappings
    'key_field' => 'id',
    'name_field' => 'name',
    'owner_field' => 'owner',
    'members_relationship' => 'users',

    // Session key for storing current tenant
    'session_key' => 'current_tenant_id',

    // System agents available to all teams
    'system_agents' => [
        // \App\Agents\SystemHelpAgent::class,
    ],

    // Agent limits per subscription tier
    'agent_limits' => [
        'free' => 3,
        'pro' => 10,
        'enterprise' => PHP_INT_MAX,
    ],
],
```

### Tenancy Drivers

| Driver | Description |
|--------|-------------|
| `auto` | Auto-detect from installed packages |
| `jetstream` | Laravel Jetstream Teams |
| `stancl` | Stancl Tenancy for Laravel |
| `spatie` | Spatie Laravel Multitenancy |
| `filament` | Filament Multi-tenancy |
| `generic` | Custom Eloquent model |
| `null` | Disabled (single-tenant mode) |

### Disabling Multi-Tenancy

For single-tenant applications, disable multi-tenancy:

```env
AGENT_MULTI_TENANCY=false
```

## Rate Limiting

Configure rate limits at different levels to prevent abuse and control costs.

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

### Rate Limit Options

| Level | Default Requests | Default Period | Description |
|-------|-----------------|----------------|-------------|
| Per User | 100 | 60 seconds | Individual user limits |
| Per Team | 1000 | 60 seconds | Team-wide limits |
| Per Agent | 500 | 60 seconds | Per-agent limits |

### Token-Based Limits

Enable token-based rate limiting for cost control:

```env
AGENT_TOKEN_RATE_LIMITING=true
AGENT_TOKEN_LIMIT_USER=100000
AGENT_TOKEN_LIMIT_TEAM=1000000
```

## Usage Tracking

Configure tracking for token usage, costs, and latency.

```php
'tracking' => [
    'enabled' => env('AGENT_TRACKING', true),

    'log_model' => env('AGENT_USAGE_LOG_MODEL'),

    'log_requests' => env('AGENT_LOG_REQUESTS', true),
    'log_responses' => env('AGENT_LOG_RESPONSES', false),
    'log_tool_calls' => env('AGENT_LOG_TOOL_CALLS', true),

    'retention_days' => env('AGENT_LOG_RETENTION_DAYS', 90),
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `enabled` | Enable usage tracking | `true` |
| `log_model` | Custom UsageLog model class | `null` |
| `log_requests` | Log request data | `true` |
| `log_responses` | Log response data (can be large) | `false` |
| `log_tool_calls` | Log tool invocations | `true` |
| `retention_days` | Days to retain logs | `90` |

## Token Pricing

Configure token pricing for cost calculations. Prices are per million tokens.

```php
'pricing' => [
    'openai' => [
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
        'o1' => ['input' => 15.00, 'output' => 60.00],
        'o1-mini' => ['input' => 3.00, 'output' => 12.00],
    ],
    'anthropic' => [
        'claude-3-5-sonnet-20241022' => ['input' => 3.00, 'output' => 15.00],
        'claude-3-5-haiku-20241022' => ['input' => 0.80, 'output' => 4.00],
        'claude-3-opus-20240229' => ['input' => 15.00, 'output' => 75.00],
    ],
    // ... additional providers
],
```

## Error Handling

Configure retry strategies, circuit breakers, and fallback providers.

```php
'error_handling' => [
    'retry' => [
        'enabled' => true,
        'max_attempts' => 3,
        'backoff' => 'exponential', // linear, exponential, fixed
        'base_delay' => 1000, // milliseconds
        'retryable_exceptions' => [
            \GuzzleHttp\Exception\ConnectException::class,
            \AgenticOrchestrator\Exceptions\RateLimitException::class,
            \AgenticOrchestrator\Exceptions\ProviderUnavailableException::class,
        ],
    ],

    'circuit_breaker' => [
        'enabled' => true,
        'failure_threshold' => 5,
        'recovery_timeout' => 60, // seconds
    ],

    'fallbacks' => [
        'openai' => ['provider' => 'anthropic', 'model' => 'claude-3-5-sonnet-20241022'],
        'anthropic' => ['provider' => 'openai', 'model' => 'gpt-4o'],
        'default' => ['provider' => 'ollama', 'model' => 'llama3.2'],
    ],
],
```

### Retry Strategies

| Strategy | Description |
|----------|-------------|
| `linear` | Fixed delay between retries |
| `exponential` | Doubling delay between retries |
| `fixed` | Same delay for all retries |

### Circuit Breaker

The circuit breaker prevents cascading failures by stopping requests to a failing provider:

| Option | Description | Default |
|--------|-------------|---------|
| `failure_threshold` | Failures before opening circuit | `5` |
| `recovery_timeout` | Seconds before attempting recovery | `60` |

## Caching

Configure caching for responses, embeddings, and tool results.

```php
'caching' => [
    'responses' => [
        'enabled' => env('AGENT_CACHE_RESPONSES', false),
        'ttl' => env('AGENT_CACHE_RESPONSE_TTL', 3600),
        'store' => env('AGENT_CACHE_STORE', 'redis'),
    ],

    'embeddings' => [
        'enabled' => env('AGENT_CACHE_EMBEDDINGS', true),
        'ttl' => env('AGENT_CACHE_EMBEDDING_TTL', 604800), // 7 days
        'store' => env('AGENT_CACHE_STORE', 'redis'),
    ],

    'tools' => [
        'enabled' => env('AGENT_CACHE_TOOLS', true),
        'ttl' => env('AGENT_CACHE_TOOL_TTL', 300),
        'store' => env('AGENT_CACHE_STORE', 'redis'),
    ],
],
```

| Cache Type | Default Enabled | Default TTL | Description |
|------------|-----------------|-------------|-------------|
| Responses | `false` | 1 hour | Cache LLM responses |
| Embeddings | `true` | 7 days | Cache vector embeddings |
| Tools | `true` | 5 minutes | Cache tool results |

## Conversation Settings

Configure conversation management settings.

```php
'conversation' => [
    'max_history' => env('AGENT_MAX_HISTORY', 50),
    'context_window' => env('AGENT_CONTEXT_WINDOW', 128000),
    'reserved_for_response' => env('AGENT_RESERVED_TOKENS', 4096),

    'summarization' => [
        'enabled' => env('AGENT_SUMMARIZATION', true),
        'threshold' => env('AGENT_SUMMARIZE_THRESHOLD', 0.75),
        'provider' => env('AGENT_SUMMARIZE_PROVIDER'),
        'model' => env('AGENT_SUMMARIZE_MODEL'),
    ],
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `max_history` | Maximum messages to retain | `50` |
| `context_window` | Token limit for context | `128000` |
| `reserved_for_response` | Tokens reserved for response | `4096` |

### Summarization

Automatic summarization compresses conversation history when approaching context limits:

| Option | Description | Default |
|--------|-------------|---------|
| `enabled` | Enable auto-summarization | `true` |
| `threshold` | Context window percentage trigger | `0.75` (75%) |
| `provider` | Provider for summarization | Agent's provider |
| `model` | Model for summarization | Agent's model |

## Tool Settings

Configure the tool system.

```php
'tools' => [
    'parallel_execution' => env('AGENT_PARALLEL_TOOLS', true),
    'max_parallel' => env('AGENT_MAX_PARALLEL_TOOLS', 5),
    'timeout' => env('AGENT_TOOL_TIMEOUT', 30),

    'mcp' => [
        'enabled' => env('AGENT_MCP_ENABLED', false),
        'servers' => [
            // 'filesystem' => [
            //     'command' => 'npx',
            //     'args' => ['-y', '@modelcontextprotocol/server-filesystem', '/path'],
            // ],
        ],
    ],
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `parallel_execution` | Execute tools in parallel | `true` |
| `max_parallel` | Maximum concurrent tool executions | `5` |
| `timeout` | Tool execution timeout (seconds) | `30` |

### MCP Integration

Agent Orchestrator supports [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) servers:

```php
'mcp' => [
    'enabled' => true,
    'servers' => [
        'filesystem' => [
            'command' => 'npx',
            'args' => ['-y', '@modelcontextprotocol/server-filesystem', '/data'],
        ],
    ],
],
```

## Workflow Settings

Configure the workflow orchestration engine.

```php
'workflows' => [
    'max_steps' => env('AGENT_WORKFLOW_MAX_STEPS', 50),
    'step_timeout' => env('AGENT_WORKFLOW_STEP_TIMEOUT', 300),
    'persistence' => env('AGENT_WORKFLOW_PERSISTENCE', true),

    'human_in_loop' => [
        'default_timeout' => env('AGENT_HITL_TIMEOUT', 86400),
        'escalation' => 'notify', // notify, auto_approve, reject
        'notification_channel' => env('AGENT_HITL_CHANNEL', 'mail'),
    ],
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `max_steps` | Maximum workflow steps | `50` |
| `step_timeout` | Step timeout (seconds) | `300` (5 min) |
| `persistence` | Persist workflow state | `true` |

### Human-in-the-Loop

| Option | Description | Default |
|--------|-------------|---------|
| `default_timeout` | Approval timeout (seconds) | `86400` (24 hours) |
| `escalation` | Action on timeout | `notify` |
| `notification_channel` | Laravel notification channel | `mail` |

## Streaming Settings

Configure streaming response behavior.

```php
'streaming' => [
    'enabled' => env('AGENT_STREAMING', true),
    'chunk_size' => env('AGENT_STREAM_CHUNK_SIZE', 1),
    'flush_interval' => env('AGENT_STREAM_FLUSH', 50),
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `enabled` | Enable streaming | `true` |
| `chunk_size` | Tokens per chunk | `1` |
| `flush_interval` | Flush interval (ms) | `50` |

## Eloquent Models

Customize the Eloquent models used by the package.

```php
'models' => [
    'agent_definition' => \AgenticOrchestrator\Models\AgentDefinition::class,
    'agent_session' => \AgenticOrchestrator\Models\AgentSession::class,
    'agent_message' => \AgenticOrchestrator\Models\AgentMessage::class,
    'agent_memory' => \AgenticOrchestrator\Models\AgentMemory::class,
    'agent_tool' => \AgenticOrchestrator\Models\AgentTool::class,
    'workflow_definition' => \AgenticOrchestrator\Models\WorkflowDefinition::class,
    'workflow_execution' => \AgenticOrchestrator\Models\WorkflowExecution::class,
    'workflow_step' => \AgenticOrchestrator\Models\WorkflowStep::class,
    'agent_evaluation' => \AgenticOrchestrator\Models\AgentEvaluation::class,
],
```

To customize a model, extend the base class and update the configuration:

```php
// app/Models/CustomAgentSession.php
namespace App\Models;

use AgenticOrchestrator\Models\AgentSession;

class CustomAgentSession extends AgentSession
{
    // Your customizations
}

// config/agent-orchestrator.php
'models' => [
    'agent_session' => \App\Models\CustomAgentSession::class,
    // ...
],
```

## API Routes

Configure optional API endpoints for agent interaction.

```php
'routes' => [
    'enabled' => env('AGENT_ROUTES_ENABLED', false),
    'prefix' => env('AGENT_ROUTES_PREFIX', 'api/agents'),
    'middleware' => ['api', 'auth:sanctum'],
    'rate_limit' => env('AGENT_ROUTES_RATE_LIMIT', 60),
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `enabled` | Enable API routes | `false` |
| `prefix` | Route prefix | `api/agents` |
| `middleware` | Applied middleware | `['api', 'auth:sanctum']` |
| `rate_limit` | Requests per minute | `60` |

## Queue Configuration

Configure queue settings for asynchronous operations.

```php
'queue' => [
    'connection' => env('AGENT_QUEUE_CONNECTION'),
    'queue' => env('AGENT_QUEUE', 'agents'),
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `connection` | Queue connection | Default connection |
| `queue` | Queue name | `agents` |

## Evaluation Settings

Configure the agent evaluation framework.

```php
'evaluation' => [
    'judge_provider' => env('AGENT_EVAL_PROVIDER', 'openai'),
    'judge_model' => env('AGENT_EVAL_MODEL', 'gpt-4o'),

    'default_criteria' => [
        'relevance',
        'accuracy',
        'helpfulness',
        'coherence',
    ],

    'parallel_tests' => env('AGENT_EVAL_PARALLEL', 5),
    'timeout' => env('AGENT_EVAL_TIMEOUT', 120),
],
```

| Option | Description | Default |
|--------|-------------|---------|
| `judge_provider` | LLM provider for judging | `openai` |
| `judge_model` | Model for judging | `gpt-4o` |
| `default_criteria` | Default evaluation criteria | See above |
| `parallel_tests` | Concurrent test executions | `5` |
| `timeout` | Test timeout (seconds) | `120` |

## Environment Variables Reference

Here is a complete list of environment variables:

```env
# Provider Settings
AGENT_DEFAULT_PROVIDER=openai
AGENT_DEFAULT_MODEL=gpt-4o

# Provider API Keys
OPENAI_API_KEY=
ANTHROPIC_API_KEY=
GEMINI_API_KEY=
MISTRAL_API_KEY=
GROQ_API_KEY=
XAI_API_KEY=
DEEPSEEK_API_KEY=
OLLAMA_BASE_URL=http://localhost:11434

# Memory
AGENT_MEMORY_DRIVER=cache
AGENT_CACHE_STORE=redis
AGENT_CACHE_TTL=3600

# Vector Stores
AGENT_VECTOR_STORE=pinecone
AGENT_EMBEDDING_PROVIDER=openai
AGENT_EMBEDDING_MODEL=text-embedding-3-small
PINECONE_API_KEY=
PINECONE_ENVIRONMENT=
PINECONE_INDEX=

# Multi-Tenancy
AGENT_MULTI_TENANCY=true
AGENT_TENANCY_DRIVER=auto

# Rate Limiting
AGENT_RATE_LIMITING=true
AGENT_RATE_LIMIT_USER_REQUESTS=100
AGENT_RATE_LIMIT_TEAM_REQUESTS=1000

# Tracking
AGENT_TRACKING=true
AGENT_LOG_RETENTION_DAYS=90

# Caching
AGENT_CACHE_RESPONSES=false
AGENT_CACHE_EMBEDDINGS=true
AGENT_CACHE_TOOLS=true

# Streaming
AGENT_STREAMING=true

# Workflows
AGENT_WORKFLOW_MAX_STEPS=50
AGENT_WORKFLOW_PERSISTENCE=true

# Queue
AGENT_QUEUE_CONNECTION=
AGENT_QUEUE=agents

# Routes
AGENT_ROUTES_ENABLED=false
AGENT_ROUTES_PREFIX=api/agents
```

## Next Steps

Now that you understand the configuration options:

- **[Quick Start](./quick-start.md)**: Create your first agent
- **[Multi-Tenancy](/docs/multi-tenancy/)**: Learn about team isolation
- **[Memory](/docs/memory/)**: Configure memory drivers
