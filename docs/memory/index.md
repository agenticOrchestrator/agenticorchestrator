# Memory System

The memory system provides persistence and retrieval capabilities for AI agents, enabling them to maintain context across conversations and perform semantic searches over stored information.

## Why Agents Need Memory

AI agents without memory are limited to single-turn interactions. They cannot:

- Remember user preferences or past interactions
- Build context over multiple conversations
- Learn from previous tool executions
- Reference earlier decisions or outcomes

The Agent Orchestrator memory system solves these limitations by providing a flexible, pluggable architecture that supports multiple storage backends and retrieval strategies.

## Core Concepts

### Memory Interface

All memory operations conform to a consistent interface (`MemoryInterface`) that provides:

| Method | Description |
|--------|-------------|
| `store(key, value, metadata)` | Persist a value with optional metadata |
| `recall(key)` | Retrieve a stored value by key |
| `has(key)` | Check if a key exists in memory |
| `search(query, limit)` | Search memory by content (semantic or keyword) |
| `forget(key)` | Remove a specific key from memory |
| `clear()` | Clear all memory for the current scope |
| `getConversationHistory(limit)` | Get recent messages |
| `addMessage(message)` | Add a message to history |
| `getDriver()` | Get the memory driver name |
| `getNamespace()` | Get the namespace for this memory scope |

### Namespacing

Memory is scoped by namespace to provide isolation between agents, users, and tenants. Namespaces follow the pattern:

```
{tenant_id}:{agent_name}:{user_id}:{session_id}
```

This allows fine-grained control over memory visibility and prevents data leakage between different contexts.

### Memory Types

The system recognizes different memory types for categorization:

| Type | Purpose |
|------|---------|
| `general` | Generic key-value storage |
| `fact` | Learned facts about users or domains |
| `preference` | User preferences and settings |
| `context` | Contextual information for current task |
| `message` | Conversation history entries |

## Available Drivers

Agent Orchestrator includes multiple memory drivers for different use cases:

| Driver | Persistence | Semantic Search | Use Case |
|--------|-------------|-----------------|----------|
| `session` | In-request only | Keyword | Testing, single-turn bots |
| `cache` | TTL-based | Keyword | Short-lived context, development |
| `database` | Permanent | Keyword | Production, multi-tenant apps |
| `vector` | Permanent | Semantic | RAG applications, knowledge bases |

See [Memory Drivers](drivers.md) for detailed information on each driver.

## Quick Start

### Basic Usage

```php
use AgenticOrchestrator\Facades\Memory;

// Get the default memory driver
$memory = Memory::driver();

// Store a value
$memory->store('user_preference', ['theme' => 'dark']);

// Retrieve a value
$preference = $memory->recall('user_preference');

// Check existence
if ($memory->has('user_preference')) {
    // ...
}

// Search memory
$results = $memory->search('dark theme', limit: 5);

// Remove from memory
$memory->forget('user_preference');
```

### Scoped Memory

```php
use AgenticOrchestrator\Facades\Memory;

// Get memory for a specific namespace
$agentMemory = Memory::driver()
    ->forNamespace('team_1:assistant:user_42');

$agentMemory->store('context', 'User prefers concise answers');
$context = $agentMemory->recall('context');
```

### Conversation History

```php
use AgenticOrchestrator\Conversations\Message;
use AgenticOrchestrator\Facades\Memory;

$memory = Memory::driver();

// Add messages to history
$memory->addMessage(Message::user('What is the weather?'));
$memory->addMessage(Message::assistant('The current temperature is 72F.'));

// Retrieve recent history
$history = $memory->getConversationHistory(limit: 50);
```

## Integration with Agents

Memory is automatically integrated with agents when configured:

```php
use AgenticOrchestrator\Agent;

$agent = Agent::create('assistant')
    ->withMemory('database')  // Use database driver
    ->withSystemPrompt('You are a helpful assistant.');

// The agent will automatically:
// - Store conversation history
// - Recall relevant context
// - Maintain user preferences
```

## Memory in Multi-Tenant Applications

When multi-tenancy is enabled, memory can be scoped to a specific tenant. Note that the `forTenant()` method is available on the `DatabaseDriver`, not on the `Memory` wrapper:

```php
use AgenticOrchestrator\Memory\Drivers\DatabaseDriver;
use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;

// Create a database driver instance
$driver = new DatabaseDriver(['table' => 'agent_memories']);

// Scope to a specific tenant
$scopedDriver = $driver->forTenant($currentTeam);

// All operations now isolated to this tenant
$scopedDriver->store('team_setting', $value);
```

Alternatively, use the `TenantManager` for automatic scoping:

```php
use AgenticOrchestrator\Memory\Drivers\DatabaseDriver;
use AgenticOrchestrator\MultiTenancy\TenantManager;

$driver = new DatabaseDriver(['table' => 'agent_memories']);
$driver->setTenantManager(app(TenantManager::class));

// Operations are automatically scoped to the current tenant
$driver->store('team_setting', $value);
```

See [Configuration](configuration.md) for details on per-agent and global memory settings.

## Next Steps

- [Memory Drivers](drivers.md) - Detailed driver documentation
- [Configuration](configuration.md) - Configure memory per agent
- [Conversations](conversations.md) - Message handling and history
- [Vector Memory](vector-memory.md) - Semantic search with embeddings
- [Custom Drivers](custom-drivers.md) - Create your own memory backend
