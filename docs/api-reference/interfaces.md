# Interfaces

Core interfaces that define contracts for the Agent Orchestrator package.

## AgentInterface

The primary interface for all agents.

```php
namespace AgenticOrchestrator\Contracts;

interface AgentInterface
{
    /**
     * Get the agent's unique ID.
     */
    public function getId(): string;

    /**
     * Get the agent's name.
     */
    public function getName(): string;

    /**
     * Get the agent's description.
     */
    public function getDescription(): string;

    /**
     * Get the model name.
     */
    public function getModel(): string;

    /**
     * Get the provider name.
     */
    public function getProvider(): string;

    /**
     * Get the agent's system instructions.
     */
    public function instructions(): string;

    /**
     * Respond to a message.
     */
    public function respond(string $message, array $context = []): AgentResponse;

    /**
     * Stream a response.
     */
    public function stream(string $message, array $context = []): Generator;

    /**
     * Get available tools.
     */
    public function getTools(): array;

    /**
     * Get the memory instance.
     */
    public function getMemory(): ?MemoryInterface;

    /**
     * Delegate to another agent.
     */
    public function delegate(AgentInterface $agent, string $task, array $context = []): AgentResponse;

    /**
     * Check if agent can be used as a delegate.
     */
    public function canBeDelegate(): bool;

    /**
     * Get agent configuration.
     */
    public function getConfig(): array;
}
```

## ToolInterface

Interface for agent tools.

```php
namespace AgenticOrchestrator\Contracts;

interface ToolInterface
{
    /**
     * Get the tool's name.
     */
    public function getName(): string;

    /**
     * Get the tool's description.
     */
    public function getDescription(): string;

    /**
     * Get parameter definitions.
     */
    public function getParameters(): array;

    /**
     * Execute the tool with arguments.
     */
    public function execute(array $arguments): mixed;

    /**
     * Get the tool's JSON schema.
     */
    public function toSchema(): array;

    /**
     * Check if tool supports parallel execution.
     */
    public function isParallel(): bool;

    /**
     * Check if results are cacheable.
     */
    public function isCacheable(): bool;

    /**
     * Get cache TTL in seconds.
     */
    public function getCacheTtl(): int;

    /**
     * Validate arguments before execution.
     */
    public function validate(array $arguments): bool;
}
```

## MemoryInterface

Interface for memory implementations.

```php
namespace AgenticOrchestrator\Contracts;

use Illuminate\Support\Collection;
use AgenticOrchestrator\Conversations\Message;

interface MemoryInterface
{
    /**
     * Store a value.
     */
    public function store(string $key, mixed $value, array $metadata = []): void;

    /**
     * Recall a stored value.
     */
    public function recall(string $key): mixed;

    /**
     * Check if a key exists.
     */
    public function has(string $key): bool;

    /**
     * Search stored values.
     */
    public function search(string $query, int $limit = 5): Collection;

    /**
     * Forget a stored value.
     */
    public function forget(string $key): void;

    /**
     * Clear all stored values.
     */
    public function clear(): void;

    /**
     * Get conversation history.
     */
    public function getConversationHistory(int $limit = 50): array;

    /**
     * Add a message to conversation.
     */
    public function addMessage(Message $message): void;

    /**
     * Get the driver name.
     */
    public function getDriver(): string;

    /**
     * Get the current namespace.
     */
    public function getNamespace(): string;
}
```

## WorkflowInterface

Interface for workflow definitions.

```php
namespace AgenticOrchestrator\Contracts;

use AgenticOrchestrator\Workflows\WorkflowDefinition;

interface WorkflowInterface
{
    /**
     * Get the workflow definition.
     */
    public function definition(): WorkflowDefinition;

    /**
     * Scope workflow to a team.
     */
    public function forTeam(int|string|object $team): static;
}
```

## StepInterface

Interface for workflow steps.

```php
namespace AgenticOrchestrator\Contracts;

interface StepInterface
{
    /**
     * Get the step name.
     */
    public function getName(): string;

    /**
     * Execute the step.
     */
    public function execute(WorkflowContext $context): StepResult;

    /**
     * Check if step should be skipped.
     */
    public function shouldSkip(WorkflowContext $context): bool;
}
```

## ParallelDriverInterface

Strategy for executing the branches of a `ParallelPattern`. The default
`SyncParallelDriver` runs branches in-process; `QueueParallelDriver` fans them
out across queue workers via `Bus::batch()`.

```php
namespace AgenticOrchestrator\Contracts;

use AgenticOrchestrator\Workflows\Patterns\ParallelOptions;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;

interface ParallelDriverInterface
{
    /**
     * Execute the given branch steps and aggregate their results.
     *
     * @param  array<StepInterface>  $steps
     */
    public function run(array $steps, WorkflowContext $context, ParallelOptions $options): StepResult;
}
```

Select the driver with `ParallelPattern::useDriver()`, or use
`WorkflowDefinition::parallelQueued()` for the queued variant.

## TeamScopedInterface

Interface for team-scoped components.

```php
namespace AgenticOrchestrator\Contracts;

interface TeamScopedInterface
{
    /**
     * Scope to a specific team.
     */
    public function forTeam(int|string|object $team): static;

    /**
     * Get the current team.
     */
    public function getTeam(): ?object;

    /**
     * Scope to a specific user.
     */
    public function forUser(int|string|object $user): static;

    /**
     * Get the current user.
     */
    public function getUser(): ?object;

    /**
     * Check if this is a system agent.
     */
    public function isSystemAgent(): bool;

    /**
     * Check if accessible by a team.
     */
    public function isAccessibleBy(int|string|object $team): bool;
}
```

## Implementing Interfaces

### Custom Agent

```php
use AgenticOrchestrator\Contracts\AgentInterface;

class CustomAgent implements AgentInterface
{
    public function getId(): string
    {
        return 'custom-agent-id';
    }

    public function getName(): string
    {
        return 'custom-agent';
    }

    public function getDescription(): string
    {
        return 'A custom agent implementation';
    }

    public function getModel(): string
    {
        return 'gpt-4';
    }

    public function getProvider(): string
    {
        return 'openai';
    }

    public function instructions(): string
    {
        return 'You are a custom agent.';
    }

    public function respond(string $message, array $context = []): AgentResponse
    {
        // Custom response logic
    }

    // ... implement other methods
}
```

### Custom Memory

```php
use AgenticOrchestrator\Contracts\MemoryInterface;

class RedisMemory implements MemoryInterface
{
    public function store(string $key, mixed $value, array $metadata = []): void
    {
        Redis::set($this->prefixKey($key), serialize([
            'value' => $value,
            'metadata' => $metadata,
        ]));
    }

    public function recall(string $key): mixed
    {
        $data = Redis::get($this->prefixKey($key));
        return $data ? unserialize($data)['value'] : null;
    }

    // ... implement other methods
}
```

### Custom Tool

```php
use AgenticOrchestrator\Contracts\ToolInterface;

class WeatherTool implements ToolInterface
{
    public function getName(): string
    {
        return 'get_weather';
    }

    public function getDescription(): string
    {
        return 'Get current weather for a location';
    }

    public function getParameters(): array
    {
        return [
            'location' => [
                'type' => 'string',
                'description' => 'City name or coordinates',
            ],
        ];
    }

    public function execute(array $arguments): mixed
    {
        $location = $arguments['location'];
        // Fetch weather data
        return ['temperature' => 72, 'conditions' => 'sunny'];
    }

    public function toSchema(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => $this->getDescription(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => $this->getParameters(),
                    'required' => ['location'],
                ],
            ],
        ];
    }

    public function isParallel(): bool
    {
        return true;
    }

    public function isCacheable(): bool
    {
        return true;
    }

    public function getCacheTtl(): int
    {
        return 300; // 5 minutes
    }

    public function validate(array $arguments): bool
    {
        return isset($arguments['location']);
    }
}
```
