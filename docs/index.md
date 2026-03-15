# Agent Orchestrator Documentation

Welcome to the Agent Orchestrator documentation. This Laravel package provides a comprehensive framework for building, orchestrating, and managing AI agents in your applications.

## Overview

Agent Orchestrator is a production-ready Laravel package that enables you to:

- **Build AI Agents** - Create intelligent agents with tools, memory, and structured outputs
- **Orchestrate Workflows** - Coordinate multiple agents with parallel, conditional, and human-in-the-loop patterns
- **Manage Multi-Tenancy** - Scope agents and resources to teams with first-class multi-tenancy support
- **Ensure Production Quality** - Built-in resilience, rate limiting, caching, and evaluation frameworks

## Documentation Sections

### Getting Started
- [Overview](getting-started/index.md) - Introduction and key concepts
- [Installation](getting-started/installation.md) - Package installation and setup
- [Configuration](getting-started/configuration.md) - Configuration options
- [Quick Start](getting-started/quick-start.md) - Your first agent in 5 minutes

### Core Concepts

#### [Agents](agents/index.md)
The heart of the framework. Learn how to create, configure, and use AI agents.
- [Creating Agents](agents/creating-agents.md)
- [Agent Response](agents/agent-response.md)
- [Agent Registry](agents/agent-registry.md)
- [Traits & Concerns](agents/traits.md)
- [Agent Events](agents/events.md)

#### [Tools](tools/index.md)
Extend agent capabilities with custom tools using PHP attributes.
- [Defining Tools](tools/defining-tools.md)
- [Built-in Tools](tools/built-in-tools.md)
- [Tool Execution](tools/tool-execution.md)
- [Tool Registry](tools/tool-registry.md)
- [Advanced Patterns](tools/advanced.md)

#### [Memory](memory/index.md)
Persistent and semantic memory for agents.
- [Memory Drivers](memory/drivers.md)
- [Conversations](memory/conversations.md)
- [Vector Memory](memory/vector-memory.md)
- [Custom Drivers](memory/custom-drivers.md)

#### [Workflows](workflows/index.md)
Coordinate complex multi-agent processes.
- [Creating Workflows](workflows/creating-workflows.md)
- [Workflow Steps](workflows/steps.md)
- [Context & Data Flow](workflows/context.md)
- [Workflow Patterns](workflows/patterns.md)
- [Persistence & Resumption](workflows/persistence.md)

### Advanced Features

#### [Embeddings & Vector Stores](embeddings/index.md)
Semantic search and RAG capabilities.
- [Embedding Providers](embeddings/providers.md)
- [Vector Stores](embeddings/vector-stores.md)
- [Semantic Search](embeddings/searching.md)

#### [Evaluation](evaluation/index.md)
Test and evaluate agent performance.
- [Test Suites](evaluation/test-suites.md)
- [Assertions](evaluation/assertions.md)
- [LLM Judge](evaluation/llm-judge.md)

#### [Multi-Tenancy](multi-tenancy/index.md)
Team-scoped agents and resources.
- [Team Scoping](multi-tenancy/team-scoping.md)
- [Agent Visibility](multi-tenancy/agent-visibility.md)
- [Memory Isolation](multi-tenancy/memory-isolation.md)

#### [MCP Integration](mcp/index.md)
Model Context Protocol support.
- [MCP Client](mcp/mcp-client.md)
- [MCP Tools](mcp/mcp-tools.md)
- [Configuration](mcp/configuration.md)

### Production Features

#### [Resilience](resilience/index.md)
Error handling and fault tolerance.
- [Retry Strategies](resilience/retry-strategy.md)
- [Circuit Breaker](resilience/circuit-breaker.md)
- [Fallback Handlers](resilience/fallback.md)

#### [Rate Limiting](rate-limiting/index.md)
Protect resources and manage costs.
- [Rate Limiters](rate-limiting/limiters.md)
- [Handling Limits](rate-limiting/handling-limits.md)

#### [Caching](caching/index.md)
Optimize performance with intelligent caching.
- [Response Cache](caching/response-cache.md)
- [Embedding Cache](caching/embedding-cache.md)
- [Tool Cache](caching/tool-cache.md)

### Development

#### [Testing](testing/index.md)
Test doubles and testing utilities.
- [FakeAgent](testing/fake-agent.md)
- [FakeTool](testing/fake-tool.md)
- [FakeMemory](testing/fake-memory.md)
- [FakeWorkflow](testing/fake-workflow.md)
- [FakeResponse](testing/fake-response.md)
- [AgentTestCase](testing/agent-test-case.md)
- [Examples](testing/examples.md)

#### [Events](events/index.md)
Event-driven architecture.
- [Agent Events](events/agent-events.md)
- [Workflow Events](events/workflow-events.md)
- [Event Listeners](events/listening.md)

#### [Artisan Commands](commands/index.md)
CLI tools for development.
- [Generator Commands](commands/generators.md)
- [Management Commands](commands/management.md)
- [Execution Commands](commands/execution.md)

### Reference

#### [API Reference](api-reference/index.md)
Complete API documentation.
- [Interfaces](api-reference/interfaces.md)
- [Exceptions](api-reference/exceptions.md)
- [Facades](api-reference/facades.md)
- [Streaming](api-reference/streaming.md)

## Quick Example

```php
use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Tools\Attributes\Tool;
use AgenticOrchestrator\Tools\Attributes\ToolParameter;

class CustomerSupportAgent extends Agent
{
    protected string $name = 'customer_support';
    protected string $description = 'Handles customer inquiries';
    protected string $model = 'gpt-4';

    public function instructions(): string
    {
        return 'You are a helpful customer support agent...';
    }

    #[Tool('Look up order details')]
    public function lookupOrder(
        #[ToolParameter('The order ID to look up')]
        string $orderId
    ): array {
        return Order::find($orderId)->toArray();
    }
}

// Usage
$response = CustomerSupportAgent::make()
    ->forTeam($team)
    ->respond('Where is my order #12345?');
```

## Requirements

- PHP 8.2+
- Laravel 10.x or 11.x
- Prism PHP for LLM integration

## License

Agent Orchestrator is open-sourced software licensed under the MIT license.
