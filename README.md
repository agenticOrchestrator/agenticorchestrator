# Agent Orchestrator

A Laravel AI Agent framework with **first-class multi-tenancy support**.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/agenticorchestrator/agenticorchestrator.svg?style=flat-square)](https://packagist.org/packages/agenticorchestrator/agenticorchestrator)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/agenticorchestrator/agenticorchestrator/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/agenticorchestrator/agenticorchestrator/actions?query=workflow%3Atests+branch%3Amain)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat-square)](https://phpstan.org/)
[![License](https://img.shields.io/packagist/l/agenticorchestrator/agenticorchestrator.svg?style=flat-square)](https://packagist.org/packages/agenticorchestrator/agenticorchestrator)

## Key Highlights

- **First-Class Multi-Tenancy** — Team isolation, system vs custom agents, per-team cost attribution
- **10+ LLM Providers** — OpenAI, Anthropic, Gemini, Mistral, Ollama, and more via Prism PHP
- **5 Vector Stores** — Pinecone, Qdrant, Weaviate, Chroma, PgVector for semantic memory
- **Full Workflow Engine** — Sequential, parallel (in-process or queued across workers), conditional, loop, and human-in-the-loop patterns
- **LLM-as-Judge Evaluation** — Built-in evaluation framework with custom assertions and metrics
- **Production Ready** — Rate limiting, caching, circuit breakers, and comprehensive event system

## Installation

```bash
composer require agenticorchestrator/agenticorchestrator
```

Publish the configuration:

```bash
php artisan vendor:publish --provider="AgenticOrchestrator\AgenticOrchestratorServiceProvider"
```

Run migrations:

```bash
php artisan migrate
```

## Quick Start

### Create Your First Agent

```bash
php artisan agent:make CustomerSupportAgent
```

```php
<?php

namespace App\Agents;

use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Tools\Attributes\Tool;

class CustomerSupportAgent extends Agent
{
    protected string $name = 'Customer Support';
    protected string $description = 'Handles customer inquiries';
    protected string $model = 'gpt-4o';

    public function instructions(): string
    {
        return "You are a helpful customer support agent for {$this->team->name}.";
    }

    #[Tool('Look up customer order')]
    public function lookupOrder(string $orderId): array
    {
        return $this->team->orders()
            ->where('id', $orderId)
            ->firstOrFail()
            ->toArray();
    }
}
```

### Use Your Agent

```php
use App\Agents\CustomerSupportAgent;

// Simple usage
$response = CustomerSupportAgent::make()
    ->forTeam($team)
    ->respond('What is the status of order #12345?');

echo $response->content;

// With streaming
$stream = CustomerSupportAgent::make()
    ->forTeam($team)
    ->stream('Tell me about your return policy');

foreach ($stream as $chunk) {
    echo $chunk->content;
}
```

### Multi-Tenancy Support

```php
// System agents (platform-wide, read-only)
class SystemHelpAgent extends Agent
{
    protected bool $isSystem = true;
}

// Custom agents (team-owned)
class TeamSalesAgent extends Agent
{
    protected bool $isSystem = false;
}

// Get available agents for a team
$agents = $team->availableAgents(); // System + team's custom agents
```

### Workflow Orchestration

```php
use AgenticOrchestrator\Workflows\Workflow;
use AgenticOrchestrator\Workflows\Patterns\ParallelPattern;
use AgenticOrchestrator\Workflows\Steps\{AgentStep, ConditionalStep};

class OnboardingWorkflow extends Workflow
{
    public function definition(): array
    {
        return [
            new AgentStep(WelcomeAgent::class, output: 'welcome'),

            ParallelPattern::make([
                new AgentStep(AccountSetupAgent::class, output: 'account'),
                new AgentStep(PreferencesAgent::class, output: 'prefs'),
            ]),

            new ConditionalStep(
                condition: fn ($ctx) => $ctx->get('customer.plan') === 'enterprise',
                then: new AgentStep(EnterpriseSetupAgent::class),
            ),

            new AgentStep(SummaryAgent::class, output: 'summary'),
        ];
    }
}

// Execute
$result = OnboardingWorkflow::make()
    ->forTeam($team)
    ->run(['customer' => $customerData]);
```

### Memory Systems

```php
// Configure memory in your agent
protected array $memory = [
    'driver' => 'vector',
    'vector_store' => 'pinecone',
    'namespace' => 'support',
];

// Use memory
$this->getMemory()->store('customer_123', $preferences);
$data = $this->getMemory()->recall('customer_123');
$results = $this->getMemory()->search('shipping policy', limit: 5);
```

## Features

### Core Features
- **Multi-Tenancy**: Team isolation, system vs custom agents, per-team cost tracking
- **10+ LLM Providers**: Via Prism PHP (OpenAI, Anthropic, Gemini, Mistral, Ollama, etc.)
- **Tool System**: Attribute-based tools with parallel execution
- **Memory**: Session, cache, database, vector (5 stores), and RAG drivers
- **Streaming**: Real-time token streaming with callbacks

### Advanced Features
- **Workflows**: Sequential, parallel, conditional, loop, human-in-the-loop patterns
- **Agent Delegation**: Agents can delegate tasks to other agents
- **Evaluation**: LLM-as-judge with custom assertions and metrics
- **Error Handling**: Retry strategies, circuit breakers, fallback providers
- **Usage Tracking**: Tokens, cost, latency tracking per team/agent/user

### Production Features
- **Rate Limiting**: Per-user, per-team, per-agent limits
- **Caching**: Response, embedding, and tool result caching
- **Events**: Comprehensive event system for all operations
- **Testing**: Fakes, mocks, and evaluation framework

## Configuration

```php
// config/agent-orchestrator.php
return [
    'default_provider' => 'openai',
    'default_model' => 'gpt-4o',

    'multi_tenancy' => [
        'enabled' => true,
        'team_model' => \App\Models\Team::class,
        'system_agents' => [
            \App\Agents\SystemHelpAgent::class,
        ],
    ],

    'memory' => [
        'default' => 'cache',
        'drivers' => [
            'vector' => [
                'store' => 'pinecone',
                'embedding_provider' => 'openai',
            ],
        ],
    ],

    'rate_limiting' => [
        'per_team' => ['requests' => 1000, 'period' => 60],
    ],
];
```

## Artisan Commands

| Command | Description |
|---------|-------------|
| `agent:make {name}` | Create a new agent |
| `agent:make-tool {name}` | Create a new tool |
| `agent:make-workflow {name}` | Create a new workflow |
| `agent:list` | List all agents |
| `agent:run {agent} {message}` | Run an agent |
| `agent:chat {agent}` | Interactive chat |
| `agent:evaluate {agent}` | Run evaluation suite |

## Testing

```php
use AgenticOrchestrator\Facades\Agent;

// Fake responses
Agent::fake([
    CustomerSupportAgent::class => 'Mocked response',
]);

// Assertions
Agent::assertResponded(CustomerSupportAgent::class);
Agent::assertToolCalled(CustomerSupportAgent::class, 'lookupOrder');
```

## Requirements

- PHP 8.3+
- Laravel 11.0, 12.0, or 13.0

## Documentation

Full documentation available in the [docs](docs/) folder.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security issues, please email agenticorchestrator@proton.me instead of using the issue tracker.

## Credits

- Built with [Prism PHP](https://prism.echolabs.dev) for LLM abstraction
- Inspired by [LarAgent](https://github.com/MaestroError/LarAgent), [Vizra ADK](https://vizra.ai), and [Neuron AI](https://neuron-ai.dev)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
