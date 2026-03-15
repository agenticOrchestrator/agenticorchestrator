# Generator Commands

Artisan commands for scaffolding agents, tools, workflows, and evaluations.

## Available Commands

| Command | Description |
|---------|-------------|
| `agent:make` | Create a new agent class |
| `agent:make-tool` | Create a new tool class |
| `agent:make-workflow` | Create a new workflow class |
| `agent:make-evaluation` | Create a new evaluation test suite |

## Agent Commands

### Create Agent

```bash
php artisan agent:make CustomerSupportAgent
```

Creates `app/Agents/CustomerSupportAgent.php`:

```php
<?php

namespace App\Agents;

use AgenticOrchestrator\Agents\Agent;

class CustomerSupportAgent extends Agent
{
    protected string $name = 'customer_support';
    protected string $description = 'Customer support agent';
    protected string $model = 'gpt-4';

    public function instructions(): string
    {
        return 'You are a helpful customer support agent.';
    }
}
```

### With Options

```bash
# Create with specific model
php artisan agent:make OrderAgent --model=gpt-4-turbo

# Create in subdirectory
php artisan agent:make Support/TicketAgent

# Force overwrite existing
php artisan agent:make ExistingAgent --force
```

## Tool Commands

### Create Tool

```bash
php artisan agent:make-tool OrderLookup
```

Creates `app/Tools/OrderLookupTool.php`:

```php
<?php

namespace App\Tools;

use AgenticOrchestrator\Tools\Attributes\Tool;
use AgenticOrchestrator\Tools\Attributes\ToolParameter;

class OrderLookupTool
{
    #[Tool('Look up order details by ID')]
    public function handle(
        #[ToolParameter('The order ID to look up')]
        string $orderId
    ): array {
        // Implement your logic
        return [];
    }
}
```

### Tool Options

```bash
# Create in subdirectory
php artisan agent:make-tool Orders/SearchTool

# Force overwrite existing
php artisan agent:make-tool ExistingTool --force
```

## Workflow Commands

### Create Workflow

```bash
php artisan agent:make-workflow OrderProcessing
```

Creates `app/Workflows/OrderProcessingWorkflow.php`:

```php
<?php

namespace App\Workflows;

use AgenticOrchestrator\Workflows\Workflow;
use AgenticOrchestrator\Workflows\WorkflowDefinition;

class OrderProcessingWorkflow extends Workflow
{
    public function definition(): WorkflowDefinition
    {
        return $this->define()
            ->step($this->validateStep())
            ->step($this->processStep())
            ->step($this->notifyStep());
    }

    protected function validateStep(): Step
    {
        // Define step
    }

    // ...
}
```

### Workflow Options

```bash
# Create in subdirectory
php artisan agent:make-workflow Orders/ProcessingWorkflow

# Force overwrite existing
php artisan agent:make-workflow ExistingWorkflow --force
```

## Evaluation Commands

### Create Evaluation

```bash
php artisan agent:make-evaluation CustomerSupportEvaluation
```

Creates `app/Evaluations/CustomerSupportEvaluation.php`:

```php
<?php

namespace App\Evaluations;

use AgenticOrchestrator\Evaluation\TestSuite;

class CustomerSupportEvaluation extends TestSuite
{
    protected string $name = 'customer_support_evaluation';

    protected function cases(): array
    {
        return [
            // Define test cases
        ];
    }
}
```

### Evaluation Options

```bash
# Create with agent reference
php artisan agent:make-evaluation SupportEvaluation --agent="App\\Agents\\SupportAgent"

# Force overwrite existing
php artisan agent:make-evaluation ExistingEvaluation --force
```

## Stub Customization

### Publish Stubs

```bash
php artisan vendor:publish --tag=agent-orchestrator-stubs
```

Publishes stubs to `stubs/agent-orchestrator/`:

```
stubs/
└── agent-orchestrator/
    ├── agent.stub
    ├── tool.stub
    ├── workflow.stub
    └── evaluation.stub
```

### Customize Stubs

Edit `stubs/agent-orchestrator/agent.stub`:

```php
<?php

namespace {{ namespace }};

use AgenticOrchestrator\Agents\Agent;
use App\Traits\HasCustomLogging;

class {{ class }} extends Agent
{
    use HasCustomLogging;

    protected string $name = '{{ name }}';
    protected string $description = '{{ description }}';
    protected string $model = '{{ model }}';

    // Custom default configuration
    protected array $memory = [
        'driver' => 'redis',
        'ttl' => 7200,
    ];

    public function instructions(): string
    {
        return <<<PROMPT
{{ instructions }}
PROMPT;
    }
}
```

## Listing Commands

### List All Generator Commands

```bash
php artisan list agent
```

Output:
```
agent
  agent:make             Create a new agent class
  agent:make-tool        Create a new tool class
  agent:make-workflow    Create a new workflow class
  agent:make-evaluation  Create a new evaluation test suite
  agent:list             List all registered agents
  agent:list-tools       List all registered tools
  agent:run              Run an agent with a single message
  agent:chat             Start an interactive chat session
  agent:evaluate         Run evaluation tests against an agent
  agent:sync-system      Sync system agents from configuration
```

## Command Help

```bash
php artisan agent:make --help
```

Output:
```
Description:
  Create a new agent class

Usage:
  agent:make [options] [--] <name>

Arguments:
  name                  The name of the agent

Options:
      --model[=MODEL]   The model to use [default: "gpt-4"]
      --force           Overwrite existing files
  -h, --help            Display help for the command
```

## Best Practices

1. **Use descriptive names** - `CustomerSupportAgent` not `Agent1`
2. **Organize in subdirectories** - `Support/TicketAgent` for related agents
3. **Customize stubs** - Match your team's coding standards
4. **Review generated code** - Generators create starting points, not final code
