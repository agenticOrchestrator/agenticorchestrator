# Installation

This guide walks you through installing Agent Orchestrator in your Laravel application.

## Requirements

Before installing, ensure your environment meets these requirements:

| Requirement | Version |
|-------------|---------|
| PHP | 8.3+ |
| Laravel | 11.0, 12.0, or 13.0 |
| Composer | 2.0+ |

## Step 1: Install via Composer

Install the package using Composer:

```bash
composer require agenticorchestrator/agenticorchestrator
```

This will install Agent Orchestrator and its dependencies, including [Prism PHP](https://github.com/prism-php/prism) for LLM provider abstraction.

## Step 2: Publish Configuration

Publish the configuration file to customize the package settings:

```bash
php artisan vendor:publish --tag=agent-orchestrator-config
```

This creates `config/agent-orchestrator.php` with all available configuration options.

## Step 3: Publish Migrations

Publish and run the database migrations:

```bash
php artisan vendor:publish --tag=agent-orchestrator-migrations
php artisan migrate
```

This creates the following tables:

| Table | Purpose |
|-------|---------|
| `agent_definitions` | Stores registered agent definitions |
| `agent_sessions` | Tracks conversation sessions |
| `agent_messages` | Stores conversation messages |
| `agent_memories` | Persistent memory storage |
| `agent_usage_logs` | Usage tracking and analytics |
| `agent_workflow_states` | Workflow execution state |
| `agent_evaluations` | Evaluation results |

## Step 4: Configure Environment

Add the required environment variables to your `.env` file. At minimum, you need to configure at least one LLM provider.

### OpenAI (Recommended for Getting Started)

```env
OPENAI_API_KEY=sk-your-api-key-here
AGENT_DEFAULT_PROVIDER=openai
AGENT_DEFAULT_MODEL=gpt-4o
```

### Anthropic

```env
ANTHROPIC_API_KEY=sk-ant-your-api-key-here
AGENT_DEFAULT_PROVIDER=anthropic
AGENT_DEFAULT_MODEL=claude-3-5-sonnet-20241022
```

### Google Gemini

```env
GEMINI_API_KEY=your-api-key-here
AGENT_DEFAULT_PROVIDER=gemini
AGENT_DEFAULT_MODEL=gemini-2.0-flash-exp
```

### Local with Ollama

```env
OLLAMA_BASE_URL=http://localhost:11434
AGENT_DEFAULT_PROVIDER=ollama
AGENT_DEFAULT_MODEL=llama3.2
```

## Optional: Publish Stubs

If you want to customize the generated agent, tool, and workflow templates:

```bash
php artisan vendor:publish --tag=agent-orchestrator-stubs
```

This copies the stub files to `stubs/agent-orchestrator/` in your application root.

## Optional: Install Vector Store Dependencies

If you plan to use vector memory for semantic search, install the appropriate driver:

### Pinecone

```bash
composer require pinecone-io/pinecone-php
```

```env
PINECONE_API_KEY=your-api-key
PINECONE_ENVIRONMENT=your-environment
PINECONE_INDEX=your-index-name
```

### Qdrant

```bash
composer require qdrant/qdrant-php
```

```env
QDRANT_HOST=http://localhost:6333
QDRANT_API_KEY=your-api-key
QDRANT_COLLECTION=agent_memories
```

### Weaviate

```bash
composer require weaviate/weaviate-php
```

```env
WEAVIATE_HOST=http://localhost:8080
WEAVIATE_API_KEY=your-api-key
```

### PgVector

Ensure your PostgreSQL database has the pgvector extension installed:

```sql
CREATE EXTENSION IF NOT EXISTS vector;
```

```env
PGVECTOR_CONNECTION=pgsql
```

## Service Provider Registration

The package automatically registers its service provider via Laravel's package auto-discovery. If you have disabled auto-discovery, add the service provider manually to `config/app.php`:

```php
'providers' => [
    // ...
    AgenticOrchestrator\AgenticOrchestratorServiceProvider::class,
],
```

## Facade Aliases

The following facades are automatically registered:

| Facade | Class |
|--------|-------|
| `Agent` | `AgenticOrchestrator\Facades\Agent` |
| `Memory` | `AgenticOrchestrator\Facades\Memory` |
| `Tenant` | `AgenticOrchestrator\Facades\Tenant` |

If you have disabled auto-discovery, add these to your `config/app.php`:

```php
'aliases' => [
    // ...
    'Agent' => AgenticOrchestrator\Facades\Agent::class,
    'Memory' => AgenticOrchestrator\Facades\Memory::class,
    'Tenant' => AgenticOrchestrator\Facades\Tenant::class,
],
```

## Verify Installation

Verify the installation by listing available commands:

```bash
php artisan list agent
```

You should see the following commands:

```
agent:chat          Interactive chat with an agent
agent:evaluate      Run evaluation suite for an agent
agent:list          List all registered agents
agent:list-tools    List all registered tools
agent:make          Create a new agent class
agent:make-tool     Create a new tool class
agent:make-workflow Create a new workflow class
agent:run           Run an agent with a message
agent:sync-system   Sync system agents to database
agent:workflow      Run a workflow
```

## Directory Structure

After installation, Agent Orchestrator expects your agents, tools, and workflows in the following locations:

```
app/
├── Agents/           # Your agent classes
│   └── CustomerSupportAgent.php
├── Tools/            # External tool classes
│   └── WeatherTool.php
└── Workflows/        # Workflow definitions
    └── OnboardingWorkflow.php
```

These directories will be created automatically when you use the `agent:make` commands.

## Troubleshooting

### Package Not Found

If Composer cannot find the package, ensure you have the correct repository configured:

```bash
composer config repositories.agenticorchestrator vcs https://github.com/agenticOrchestrator/agenticorchestrator
```

### Migration Errors

If you encounter migration errors, ensure your database connection is properly configured and the database exists:

```bash
php artisan migrate:status
```

### Provider Authentication Errors

If you see authentication errors when using an LLM provider:

1. Verify your API key is correctly set in `.env`
2. Clear the config cache: `php artisan config:clear`
3. Check the provider's API status page

### Memory/Cache Issues

If you experience memory or caching issues:

1. Ensure Redis is running if using Redis cache
2. Clear the cache: `php artisan cache:clear`
3. Verify the cache driver in your `.env`: `CACHE_DRIVER=redis`

## Next Steps

Now that Agent Orchestrator is installed, continue to:

- **[Configuration](./configuration.md)**: Learn about all configuration options
- **[Quick Start](./quick-start.md)**: Create your first agent
