# Agents

Agents are the core building blocks of Agent Orchestrator. An agent is an AI-powered entity that can receive natural language input, execute tools to perform actions, maintain memory across conversations, and produce intelligent responses.

## What is an Agent?

An agent combines several capabilities:

- **Language Understanding**: Process and understand natural language queries
- **Tool Execution**: Invoke functions to interact with your application
- **Memory Management**: Remember context across conversation turns
- **Multi-Tenancy**: Operate within team-scoped boundaries
- **Delegation**: Hand off tasks to specialized sub-agents

## Key Concepts

### Agent Classes

Agents are defined as PHP classes that extend the base `Agent` class. Each agent has:

- **Name**: A human-readable identifier shown in dashboards and logs
- **Instructions**: The system prompt that defines behavior and personality
- **Model**: The LLM model to use (e.g., `gpt-4o`, `claude-3-5-sonnet-20241022`)
- **Provider**: The LLM provider (e.g., `openai`, `anthropic`)
- **Tools**: Functions the agent can call to perform actions

```php
use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Tools\Attributes\Tool;

class CustomerSupportAgent extends Agent
{
    protected string $name = 'Customer Support';
    protected string $description = 'Helps customers with orders and inquiries';
    protected string $model = 'gpt-4o';
    protected string $provider = 'openai';

    public function instructions(): string
    {
        return <<<PROMPT
            You are a helpful customer support agent for our e-commerce platform.
            Be friendly, professional, and always try to resolve issues quickly.
            If you cannot help, offer to escalate to a human agent.
        PROMPT;
    }

    #[Tool('Look up an order by its ID')]
    public function lookupOrder(string $orderId): array
    {
        return Order::findOrFail($orderId)->toArray();
    }
}
```

### System vs Custom Agents

Agent Orchestrator distinguishes between two types of agents:

| Type | Scope | Created By | Example |
|------|-------|------------|---------|
| **System Agents** | Platform-wide, read-only | Developers | General assistant, compliance checker |
| **Custom Agents** | Team-specific | Team admins | Team-specific support bot |

System agents are available to all teams but cannot be modified. Custom agents are scoped to specific teams and can be customized for their needs.

### Agent Lifecycle

When an agent processes a message, it follows this lifecycle:

1. **AgentStarted** event is dispatched
2. **Context Building**: Conversation history is loaded from memory
3. **Tool Loop**: The agent iterates with the LLM, executing tools as needed
4. **Memory Storage**: The conversation is stored for future reference
5. **AgentCompleted** event is dispatched with the response

```
[User Message]
    |
    v
[AgentStarted Event]
    |
    v
[Build Context + Load History]
    |
    v
[LLM Request] <--+
    |            |
    v            |
[Tool Calls?] ---+  (loop until no more tool calls)
    |
    v
[Store Conversation]
    |
    v
[AgentCompleted Event]
    |
    v
[AgentResponse]
```

## Basic Usage

### Creating an Agent Instance

Use the static `make()` method or dependency injection:

```php
// Using the factory method
$agent = CustomerSupportAgent::make();

// Using dependency injection
public function __construct(CustomerSupportAgent $agent)
{
    $this->agent = $agent;
}

// Using the Agent Manager
$agent = app(AgentManager::class)->make('customer-support');
```

### Sending Messages

Use the `respond()` method to process a message and get a response:

```php
$response = $agent->respond('What is the status of my order #12345?');

// Access the response content
echo $response->content;

// Check if tools were called
if ($response->hasToolCalls()) {
    foreach ($response->getToolCalls() as $call) {
        echo "Called: {$call['name']}";
    }
}

// Get usage statistics
echo "Tokens used: " . $response->getTotalTokens();
```

### Team Scoping

Agents automatically inherit team context when scoped:

```php
use AgenticOrchestrator\Agents\AgentManager;

$manager = app(AgentManager::class);

// Create agent scoped to a team
$agent = $manager->makeForTeam('customer-support', $team);

// Or scope an existing agent
$agent = CustomerSupportAgent::make()->forTeam($team);
```

## Configuration Options

Agents support various configuration options through class properties:

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$name` | string | Class name | Display name |
| `$description` | string | `''` | Agent description |
| `$model` | string | `'gpt-4o'` | LLM model identifier |
| `$provider` | string | `'openai'` | LLM provider |
| `$temperature` | float | `0.7` | Response creativity (0-2) |
| `$maxTokens` | int\|null | `null` | Max response tokens |
| `$memory` | array | `['driver' => 'cache']` | Memory configuration |
| `$capabilities` | array | See below | Agent capabilities |
| `$isSystem` | bool | `false` | Whether system agent |
| `$tools` | array | `[]` | External tool classes |

### Capabilities Configuration

The `$capabilities` array controls agent behavior:

```php
protected array $capabilities = [
    'can_delegate' => false,      // Can this agent delegate to others?
    'can_be_delegate' => true,    // Can this agent receive delegated tasks?
    'can_use_rag' => false,       // Can this agent use RAG retrieval?
    'can_stream' => true,         // Does this agent support streaming?
    'max_iterations' => 10,       // Maximum tool call iterations
];
```

See the [Streaming documentation](../api-reference/streaming.md) for details on real-time streaming responses.

## Fluent Configuration

Override configuration at runtime using fluent methods:

```php
$response = CustomerSupportAgent::make()
    ->withModel('gpt-4o-mini')
    ->withProvider('openai')
    ->withTemperature(0.5)
    ->withMaxTokens(1000)
    ->forTeam($team)
    ->forUser($user)
    ->respond('Hello!');
```

## In This Section

- **[Creating Agents](./creating-agents.md)**: Step-by-step guide to creating agents
- **[Agent Response](./agent-response.md)**: Understanding the AgentResponse object
- **[Agent Registry](./agent-registry.md)**: Registering and discovering agents
- **[Traits](./traits.md)**: Available traits and their capabilities
- **[Events](./events.md)**: Agent lifecycle events

## Next Steps

1. Learn how to [create your first agent](./creating-agents.md)
2. Understand the [AgentResponse](./agent-response.md) object
3. Explore available [traits](./traits.md) for extended functionality
4. Set up [event listeners](./events.md) for monitoring
