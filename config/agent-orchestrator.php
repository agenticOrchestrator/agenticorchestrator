<?php

use AgenticOrchestrator\Exceptions\ProviderUnavailableException;
use AgenticOrchestrator\Exceptions\RateLimitException;
use AgenticOrchestrator\Models\AgentDefinition;
use AgenticOrchestrator\Models\AgentEvaluation;
use AgenticOrchestrator\Models\AgentMemory;
use AgenticOrchestrator\Models\AgentMessage;
use AgenticOrchestrator\Models\AgentSession;
use AgenticOrchestrator\Models\AgentTool;
use AgenticOrchestrator\Models\WorkflowDefinition;
use AgenticOrchestrator\Models\WorkflowExecution;
use AgenticOrchestrator\Models\WorkflowStep;
use App\Models\Team;
use App\Models\User;
use GuzzleHttp\Exception\ConnectException;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Provider Configuration
    |--------------------------------------------------------------------------
    |
    | The default LLM provider and model to use when not specified on the agent.
    |
    */
    'default_provider' => env('AGENT_DEFAULT_PROVIDER', 'openai'),
    'default_model' => env('AGENT_DEFAULT_MODEL', 'gpt-4o'),

    /*
    |--------------------------------------------------------------------------
    | LLM Providers
    |--------------------------------------------------------------------------
    |
    | Configuration for each supported LLM provider. All providers are managed
    | through Prism PHP, which provides a unified interface.
    |
    | Supported: openai, anthropic, gemini, mistral, ollama, groq, xai, deepseek
    |
    */
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

    /*
    |--------------------------------------------------------------------------
    | Memory Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the memory system for agents. Memory persists information
    | across conversations and enables semantic search capabilities.
    |
    | Drivers: session, cache, database, vector, rag
    |
    */
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
                'chunk_size' => env('AGENT_RAG_CHUNK_SIZE', 1000),
                'chunk_overlap' => env('AGENT_RAG_CHUNK_OVERLAP', 200),
                'retrieve_limit' => env('AGENT_RAG_RETRIEVE_LIMIT', 5),
                'score_threshold' => env('AGENT_RAG_SCORE_THRESHOLD', 0.7),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vector Stores
    |--------------------------------------------------------------------------
    |
    | Configuration for vector database providers used for semantic search
    | and memory retrieval.
    |
    | Supported: pinecone, weaviate, qdrant, chroma, pgvector
    |
    */
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

    /*
    |--------------------------------------------------------------------------
    | RAG (Retrieval-Augmented Generation) Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the RAG pipeline, including chunking strategies,
    | retrieval settings, and default parameters.
    |
    */
    'rag' => [
        'default_chunker' => env('AGENT_RAG_CHUNKER', 'recursive'),
        'default_retriever' => env('AGENT_RAG_RETRIEVER', 'vector'),

        'chunking' => [
            'size' => env('AGENT_RAG_CHUNK_SIZE', 1000),
            'overlap' => env('AGENT_RAG_CHUNK_OVERLAP', 200),
        ],

        'retrieval' => [
            'limit' => env('AGENT_RAG_RETRIEVE_LIMIT', 5),
            'threshold' => env('AGENT_RAG_SCORE_THRESHOLD', 0.7),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy Configuration
    |--------------------------------------------------------------------------
    |
    | Enable team-based isolation for agents, memories, and conversations.
    | This is the key differentiator from other Laravel agent packages.
    |
    | Supported drivers:
    | - 'auto': Auto-detect from installed packages
    | - 'jetstream': Laravel Jetstream Teams
    | - 'stancl': Stancl Tenancy for Laravel
    | - 'spatie': Spatie Laravel Multitenancy
    | - 'filament': Filament Multi-tenancy
    | - 'generic': Custom Eloquent model
    | - 'null': Disabled (single-tenant mode)
    |
    */
    'multi_tenancy' => [
        'enabled' => env('AGENT_MULTI_TENANCY', true),

        // Driver for tenant resolution
        // Use 'auto' to detect installed packages automatically
        'driver' => env('AGENT_TENANCY_DRIVER', 'auto'),

        // The Tenant/Team model class (required for 'generic' driver)
        'model' => env('AGENT_TENANT_MODEL', Team::class),

        // The User model class for user scoping
        'user_model' => env('AGENT_USER_MODEL', User::class),

        // User-to-tenant relationship name (for generic driver)
        'user_relationship' => env('AGENT_USER_TENANT_RELATION', 'teams'),

        // Tenant model field mappings (for generic driver)
        'key_field' => 'id',
        'name_field' => 'name',
        'owner_field' => 'owner',
        'members_relationship' => 'users',

        // Session key for storing current tenant (for generic driver)
        'session_key' => 'current_tenant_id',

        // System agents available to all teams (read-only)
        'system_agents' => [
            // \App\Agents\SystemHelpAgent::class,
            // \App\Agents\SystemAnalyticsAgent::class,
        ],

        // Default agent limits per subscription tier
        'agent_limits' => [
            'free' => 3,
            'pro' => 10,
            'enterprise' => PHP_INT_MAX,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limits at different levels to prevent abuse and control
    | costs. All limits are per time period specified in seconds.
    |
    */
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

        // Token-based rate limiting (per minute)
        'tokens' => [
            'enabled' => env('AGENT_TOKEN_RATE_LIMITING', false),
            'per_user' => env('AGENT_TOKEN_LIMIT_USER', 100000),
            'per_team' => env('AGENT_TOKEN_LIMIT_TEAM', 1000000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Usage Tracking
    |--------------------------------------------------------------------------
    |
    | Track token usage, costs, and latency for all agent interactions.
    | Essential for billing, analytics, and optimization.
    |
    */
    'tracking' => [
        'enabled' => env('AGENT_TRACKING', true),

        // Custom UsageLog model (if you want to use existing one)
        'log_model' => env('AGENT_USAGE_LOG_MODEL'),

        // What to log
        'log_requests' => env('AGENT_LOG_REQUESTS', true),
        'log_responses' => env('AGENT_LOG_RESPONSES', false), // Warning: can be large
        'log_tool_calls' => env('AGENT_LOG_TOOL_CALLS', true),

        // Retention policy
        'retention_days' => env('AGENT_LOG_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Pricing
    |--------------------------------------------------------------------------
    |
    | Pricing per million tokens for cost calculation. Updated as of Jan 2026.
    | Format: ['input' => price_per_million, 'output' => price_per_million]
    |
    */
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
        'gemini' => [
            'gemini-2.0-flash-exp' => ['input' => 0.00, 'output' => 0.00], // Free tier
            'gemini-1.5-pro' => ['input' => 1.25, 'output' => 5.00],
            'gemini-1.5-flash' => ['input' => 0.075, 'output' => 0.30],
        ],
        'mistral' => [
            'mistral-large-latest' => ['input' => 2.00, 'output' => 6.00],
            'mistral-small-latest' => ['input' => 0.20, 'output' => 0.60],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    |
    | Configure retry strategies, circuit breakers, and fallback providers
    | for resilient agent operations.
    |
    */
    'error_handling' => [
        'retry' => [
            'enabled' => true,
            'max_attempts' => 3,
            'backoff' => 'exponential', // linear, exponential, fixed
            'base_delay' => 1000, // milliseconds
            'retryable_exceptions' => [
                ConnectException::class,
                RateLimitException::class,
                ProviderUnavailableException::class,
            ],
        ],

        'circuit_breaker' => [
            'enabled' => true,
            'failure_threshold' => 5,
            'recovery_timeout' => 60, // seconds
        ],

        'fallbacks' => [
            // Fallback provider/model when primary fails
            'openai' => ['provider' => 'anthropic', 'model' => 'claude-3-5-sonnet-20241022'],
            'anthropic' => ['provider' => 'openai', 'model' => 'gpt-4o'],
            'default' => ['provider' => 'ollama', 'model' => 'llama3.2'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Cache configuration for responses, embeddings, and tool results.
    | Caching can significantly reduce costs and latency.
    |
    */
    'caching' => [
        'responses' => [
            'enabled' => env('AGENT_CACHE_RESPONSES', false),
            'ttl' => env('AGENT_CACHE_RESPONSE_TTL', 3600),
            'store' => env('AGENT_CACHE_STORE', 'redis'),
        ],

        'embeddings' => [
            'enabled' => env('AGENT_CACHE_EMBEDDINGS', true),
            'ttl' => env('AGENT_CACHE_EMBEDDING_TTL', 86400 * 7), // 7 days
            'store' => env('AGENT_CACHE_STORE', 'redis'),
        ],

        'tools' => [
            'enabled' => env('AGENT_CACHE_TOOLS', true),
            'ttl' => env('AGENT_CACHE_TOOL_TTL', 300),
            'store' => env('AGENT_CACHE_STORE', 'redis'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Evaluation
    |--------------------------------------------------------------------------
    |
    | Configuration for the agent evaluation framework, including
    | LLM-as-a-judge settings and default evaluation criteria.
    |
    */
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
        'timeout' => env('AGENT_EVAL_TIMEOUT', 120), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversation Settings
    |--------------------------------------------------------------------------
    |
    | Configure conversation management including history limits,
    | context windows, and automatic summarization.
    |
    */
    'conversation' => [
        'max_history' => env('AGENT_MAX_HISTORY', 50),
        'context_window' => env('AGENT_CONTEXT_WINDOW', 128000),
        'reserved_for_response' => env('AGENT_RESERVED_TOKENS', 4096),

        'summarization' => [
            'enabled' => env('AGENT_SUMMARIZATION', true),
            'threshold' => env('AGENT_SUMMARIZE_THRESHOLD', 0.75), // % of context window
            'provider' => env('AGENT_SUMMARIZE_PROVIDER'), // null = use agent's provider
            'model' => env('AGENT_SUMMARIZE_MODEL'), // null = use agent's model
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tool Settings
    |--------------------------------------------------------------------------
    |
    | Global settings for the tool system, including parallel execution
    | and timeout configurations.
    |
    */
    'tools' => [
        'parallel_execution' => env('AGENT_PARALLEL_TOOLS', true),
        'max_parallel' => env('AGENT_MAX_PARALLEL_TOOLS', 5),
        'timeout' => env('AGENT_TOOL_TIMEOUT', 30), // seconds

        // MCP (Model Context Protocol) integration
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

    /*
    |--------------------------------------------------------------------------
    | Workflow Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the workflow orchestration engine.
    |
    */
    'workflows' => [
        'max_steps' => env('AGENT_WORKFLOW_MAX_STEPS', 50),
        'step_timeout' => env('AGENT_WORKFLOW_STEP_TIMEOUT', 300), // seconds
        'persistence' => env('AGENT_WORKFLOW_PERSISTENCE', true),

        // Settings for WorkflowDefinition::parallelQueued() / QueueParallelDriver.
        // The default parallel() pattern runs in-process and ignores these.
        'parallel' => [
            'queue_connection' => env('AGENT_WORKFLOW_PARALLEL_CONNECTION'), // null = default connection
            'queue' => env('AGENT_WORKFLOW_PARALLEL_QUEUE', 'agents'),
            'timeout' => env('AGENT_WORKFLOW_PARALLEL_TIMEOUT', 300), // max seconds to await the batch
            'poll_interval' => env('AGENT_WORKFLOW_PARALLEL_POLL', 250), // batch status poll interval (ms)
        ],

        'human_in_loop' => [
            'default_timeout' => env('AGENT_HITL_TIMEOUT', 86400), // 24 hours
            'escalation' => 'notify', // notify, auto_approve, reject
            'notification_channel' => env('AGENT_HITL_CHANNEL', 'mail'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Streaming Settings
    |--------------------------------------------------------------------------
    |
    | Configure streaming response behavior.
    |
    */
    'streaming' => [
        'enabled' => env('AGENT_STREAMING', true),
        'chunk_size' => env('AGENT_STREAM_CHUNK_SIZE', 1), // tokens per chunk
        'flush_interval' => env('AGENT_STREAM_FLUSH', 50), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Eloquent Models
    |--------------------------------------------------------------------------
    |
    | Customize the Eloquent models used by the package. Useful if you need
    | to extend the default models with additional functionality.
    |
    */
    'models' => [
        'agent_definition' => AgentDefinition::class,
        'agent_session' => AgentSession::class,
        'agent_message' => AgentMessage::class,
        'agent_memory' => AgentMemory::class,
        'agent_tool' => AgentTool::class,
        'workflow_definition' => WorkflowDefinition::class,
        'workflow_execution' => WorkflowExecution::class,
        'workflow_step' => WorkflowStep::class,
        'agent_evaluation' => AgentEvaluation::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    |
    | Optionally expose API endpoints for interacting with agents.
    | Disabled by default - use Facades/direct classes for control.
    |
    */
    'routes' => [
        'enabled' => env('AGENT_ROUTES_ENABLED', false),
        'prefix' => env('AGENT_ROUTES_PREFIX', 'api/agents'),
        'middleware' => ['api', 'auth:sanctum'],
        'rate_limit' => env('AGENT_ROUTES_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which queue connection and queue name to use for
    | asynchronous operations like workflow execution.
    |
    */
    'queue' => [
        'connection' => env('AGENT_QUEUE_CONNECTION'),
        'queue' => env('AGENT_QUEUE', 'agents'),
    ],
];
