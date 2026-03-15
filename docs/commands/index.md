# Artisan Commands

Agent Orchestrator provides a comprehensive set of Artisan commands for managing agents, tools, workflows, and evaluations from the command line.

## Command Overview

### Generator Commands

Create new components with scaffolded boilerplate code:

| Command | Description |
|---------|-------------|
| [`agent:make`](generators.md#agentmake) | Create a new agent class |
| [`agent:make-tool`](generators.md#agentmake-tool) | Create a new tool class |
| [`agent:make-workflow`](generators.md#agentmake-workflow) | Create a new workflow class |
| [`agent:make-evaluation`](generators.md#agentmake-evaluation) | Create a new evaluation test suite |

### Management Commands

List and manage registered components:

| Command | Description |
|---------|-------------|
| [`agent:list`](management.md#agentlist) | List all registered agents |
| [`agent:list-tools`](management.md#agentlist-tools) | List all registered tools |
| [`agent:sync-system`](management.md#agentsync-system) | Sync system agents from configuration |

### Execution Commands

Run agents and workflows interactively:

| Command | Description |
|---------|-------------|
| [`agent:run`](execution.md#agentrun) | Run an agent with a single message |
| [`agent:chat`](execution.md#agentchat) | Start an interactive chat session |
| [`workflow:run`](execution.md#workflowrun) | Run or resume a workflow |

### Evaluation Commands

Test and evaluate agent behavior:

| Command | Description |
|---------|-------------|
| [`agent:evaluate`](evaluation.md#agentevaluate) | Run evaluation tests against an agent |

## Quick Start

### Creating Your First Agent

```bash
# Create a basic agent
php artisan agent:make CustomerSupport

# Create an agent with example tools
php artisan agent:make CustomerSupport --tool

# Create a system agent (available to all teams)
php artisan agent:make GlobalAssistant --system
```

### Running an Agent

```bash
# Single message interaction
php artisan agent:run customer-support "How do I reset my password?"

# Interactive chat session
php artisan agent:chat customer-support
```

### Creating and Running a Workflow

```bash
# Create a workflow
php artisan agent:make-workflow OrderProcessing

# Run the workflow
php artisan workflow:run OrderProcessing --input="order_id=12345"
```

### Creating and Running Evaluations

```bash
# Create an evaluation suite
php artisan agent:make-evaluation CustomerSupportEvaluation --agent="App\\Agents\\CustomerSupportAgent"

# Run the evaluation
php artisan agent:evaluate "App\\Evaluations\\CustomerSupportEvaluation"
```

## Global Options

Most commands support these common options:

| Option | Description |
|--------|-------------|
| `--help` | Display help information for the command |
| `-v`, `-vv`, `-vvv` | Increase verbosity of output |
| `--no-interaction` | Do not ask interactive questions |
| `--env` | The environment the command should run under |

## Command Namespaces

All agent-related commands use the `agent:` prefix, while workflow commands use `workflow:`:

```
agent:make           # Create components
agent:list           # List components
agent:run            # Execute agents
agent:chat           # Interactive sessions
agent:evaluate       # Test agents

workflow:run         # Execute workflows
```

## Next Steps

- [Generator Commands](generators.md) - Learn about code generation
- [Management Commands](management.md) - Managing your agents and tools
- [Execution Commands](execution.md) - Running agents and workflows
- [Evaluation Commands](evaluation.md) - Testing agent behavior
- [Stubs Reference](stubs.md) - Customizing generated code
