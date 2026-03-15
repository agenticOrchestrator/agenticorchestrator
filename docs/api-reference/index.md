# API Reference

This section provides comprehensive API documentation for the Agent Orchestrator package. Here you will find detailed information about interfaces, facades, exception classes, and streaming utilities.

## Overview

The API Reference is organized into the following sections:

| Section | Description |
|---------|-------------|
| **[Interfaces](./interfaces.md)** | Core contracts that define the package's architecture |
| **[Facades](./facades.md)** | Laravel facades for convenient static access |
| **[Exceptions](./exceptions.md)** | Exception classes for error handling and recovery |
| **[Streaming](./streaming.md)** | Classes for handling streaming LLM responses |
| **[Structured Output](./structured-output.md)** | Tools for typed JSON schema building and validation |

## Quick Links

### Core Interfaces

- [`AgentInterface`](./interfaces.md#agentinterface) - Core contract for all AI agents
- [`ToolInterface`](./interfaces.md#toolinterface) - Contract for agent tools
- [`MemoryInterface`](./interfaces.md#memoryinterface) - Contract for memory systems
- [`WorkflowInterface`](./interfaces.md#workflowinterface) - Contract for workflow orchestration
- [`StepInterface`](./interfaces.md#stepinterface) - Contract for workflow steps
- [`TeamScopedInterface`](./interfaces.md#teamscopedinterface) - Contract for multi-tenant resources

### Facades

- [`Agent`](./facades.md#agent-facade) - Access the Agent Manager
- [`Memory`](./facades.md#memory-facade) - Access the Memory Manager
- [`Tenant`](./facades.md#tenant-facade) - Access the Tenant Manager

### Exception Hierarchy

```
Exception
├── AgentException (base class)
│   ├── ToolExecutionException
│   ├── RateLimitException
│   ├── ProviderException
│   ├── CircuitBreakerOpenException
│   ├── ValidationException
│   ├── MemoryException
│   └── WorkflowException
├── AgentNotFoundException
└── AgentAccessDeniedException
```

### Streaming Classes

- [`StreamResponse`](./streaming.md#streamresponse) - Wraps streaming LLM responses
- [`StreamChunk`](./streaming.md#streamchunk) - Represents a single stream chunk
- [`StreamHandler`](./streaming.md#streamhandler) - Utilities for HTTP streaming

### Structured Output

- [`SchemaBuilder`](./structured-output.md#schemabuilder) - Fluent JSON schema builder
- [`StructuredResponse`](./structured-output.md#structuredresponse) - Typed wrapper for JSON responses

## Namespace Structure

All classes in Agent Orchestrator are organized under the `AgenticOrchestrator` namespace:

```
AgenticOrchestrator\
├── Contracts\           # Interfaces
├── Facades\             # Laravel facades
├── Exceptions\          # Exception classes
├── Streaming\           # Streaming response handling
├── StructuredOutput\    # JSON schema and validation
├── Agents\              # Agent implementations
├── Tools\               # Tool implementations
├── Memory\              # Memory drivers
├── Workflows\           # Workflow engine
└── ...
```

## Error Handling Patterns

The package provides structured exception handling with context for debugging:

```php
use AgenticOrchestrator\Exceptions\AgentException;
use AgenticOrchestrator\Exceptions\ToolExecutionException;
use AgenticOrchestrator\Exceptions\RateLimitException;

try {
    $response = $agent->respond('Process this request');
} catch (RateLimitException $e) {
    // Handle rate limiting - check retry time
    $retryAfter = $e->getRetryAfter();
    Log::warning("Rate limited, retry after {$retryAfter}s", $e->getContext());
} catch (ToolExecutionException $e) {
    // Handle tool failures
    Log::error("Tool '{$e->getToolName()}' failed", [
        'arguments' => $e->getArguments(),
        'context' => $e->getContext(),
    ]);
} catch (AgentException $e) {
    // Handle general agent errors
    if ($e->isRecoverable()) {
        // Attempt recovery
    }
    Log::error($e->getMessage(), $e->toArray());
}
```

## Type Safety

The package uses PHP 8.1+ features for improved type safety:

- Strict types declaration in all files
- Typed properties and return types
- Union types where appropriate
- Generics documentation via PHPDoc

## Next Steps

- Review the [Interfaces](./interfaces.md) to understand the architectural contracts
- Learn about available [Facades](./facades.md) for convenient access
- Understand [Exception](./exceptions.md) handling patterns
- Explore [Streaming](./streaming.md) for real-time responses
- Use [Structured Output](./structured-output.md) for typed LLM responses
