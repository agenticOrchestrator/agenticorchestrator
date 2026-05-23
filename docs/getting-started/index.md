# Getting Started

Agent Orchestrator is the most comprehensive Laravel AI Agent framework with first-class multi-tenancy support. It provides everything you need to build, deploy, and manage AI agents in your Laravel applications.

## What is Agent Orchestrator?

Agent Orchestrator is a Laravel package that enables you to create intelligent AI agents that can:

- Process natural language queries and generate responses
- Execute tools and actions on behalf of users
- Maintain conversation history and memory across sessions
- Work within team-based multi-tenant environments
- Orchestrate complex workflows with multiple agents

## Key Features

### Multi-Tenancy Support

Agent Orchestrator was built from the ground up with multi-tenancy in mind. Unlike other Laravel agent packages, it provides:

- **Team Isolation**: Agents, memories, and conversations are automatically scoped to teams
- **System Agents**: Platform-wide agents that are read-only for all teams
- **Custom Agents**: Team-specific agents that can be created and managed by team administrators
- **Cost Attribution**: Track usage and costs per team, user, or agent

### Multiple LLM Providers

Powered by [Prism PHP](https://prism.echolabs.dev), Agent Orchestrator supports 10+ LLM providers through a unified interface:

| Provider | Models |
|----------|--------|
| OpenAI | GPT-4o, GPT-4o-mini, GPT-4 Turbo, o1, o1-mini |
| Anthropic | Claude 3.5 Sonnet, Claude 3.5 Haiku, Claude 3 Opus |
| Google | Gemini 2.0 Flash, Gemini 1.5 Pro, Gemini 1.5 Flash |
| Mistral | Mistral Large, Mistral Small |
| Groq | LLaMA, Mixtral models |
| xAI | Grok models |
| DeepSeek | DeepSeek models |
| Ollama | Local models (LLaMA, Mistral, etc.) |

### Tool System

Create powerful tools using PHP attributes:

```php
use AgenticOrchestrator\Tools\Attributes\Tool;
use AgenticOrchestrator\Tools\Attributes\ToolParameter;

#[Tool('Search for products')]
public function searchProducts(
    #[ToolParameter('Search query')]
    string $query,
    #[ToolParameter('Maximum results', enum: ['10', '25', '50'])]
    string $limit = '10',
): array {
    return Product::search($query)->take((int) $limit)->get()->toArray();
}
```

### Memory Systems

Five memory drivers to fit different use cases:

| Driver | Use Case | Persistence |
|--------|----------|-------------|
| Session | In-request only | None |
| Cache | Short-term conversations | TTL-based |
| Database | Long-term storage | Permanent |
| Vector | Semantic search | Permanent |
| RAG | External knowledge retrieval | External |

### Workflow Orchestration

Build complex multi-agent workflows with:

- **Sequential Steps**: Execute agents one after another
- **Parallel Steps**: Run multiple agents concurrently
- **Conditional Steps**: Branch based on context
- **Loop Steps**: Iterate until conditions are met
- **Human-in-the-Loop**: Pause for human approval

### Production-Ready Features

- **Rate Limiting**: Per-user, per-team, and per-agent limits
- **Error Handling**: Retry strategies, circuit breakers, fallback providers
- **Caching**: Response, embedding, and tool result caching
- **Usage Tracking**: Token usage, costs, and latency metrics
- **Evaluation**: LLM-as-judge testing framework

## Feature Comparison

| Feature | Agent Orchestrator | LarAgent | Vizra ADK |
|---------|-------------------|----------|-----------|
| Multi-Tenancy | First-class | None | None |
| System Agents | Yes | No | No |
| Team Scoping | Automatic | No | No |
| Vector Memory | 5 stores | No | 1 store |
| Workflows | Full engine | No | Basic |
| Human-in-Loop | Yes | No | No |
| LLM Providers | 10+ via Prism | 3 | 10+ |
| Cost Attribution | Per-team | No | No |
| Evaluation | LLM-as-judge | No | Yes |

## Requirements

Before installing Agent Orchestrator, ensure your environment meets these requirements:

- **PHP**: 8.3 or higher
- **Laravel**: 11.0 or higher (including Laravel 12 and 13)
- **Database**: MySQL, PostgreSQL, or SQLite
- **Cache**: Redis recommended for production

## Next Steps

Ready to get started? Follow these guides in order:

1. **[Installation](./installation.md)**: Install the package and set up your environment
2. **[Configuration](./configuration.md)**: Configure providers, memory, and multi-tenancy
3. **[Quick Start](./quick-start.md)**: Create and run your first agent

## Documentation Structure

This documentation is organized into the following sections:

- **Getting Started**: Installation, configuration, and basic usage
- **[Agents](/docs/agents/)**: Creating and customizing agents
- **[Tools](/docs/tools/)**: Building and using tools
- **[Memory](/docs/memory/)**: Memory drivers and configuration
- **[Multi-Tenancy](/docs/multi-tenancy/)**: Team isolation and system agents
- **[Workflows](/docs/workflows/)**: Multi-agent orchestration
- **[Events](/docs/events/)**: Event system and listeners
- **[Testing](/docs/testing/)**: Testing strategies and fakes
- **[Evaluation](/docs/evaluation/)**: LLM-as-judge framework
- **[API Reference](/docs/api-reference/)**: Complete API documentation

## Support

- **GitHub Issues**: [Report bugs and request features](https://github.com/agenticOrchestrator/agenticorchestrator/issues)
- **Documentation**: [Full documentation](https://agent-orchestrator.dev)
- **Security**: Report security issues to security@agent-orchestrator.dev
