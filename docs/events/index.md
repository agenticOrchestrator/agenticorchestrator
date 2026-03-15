# Events

Agent Orchestrator integrates with Laravel's event system to provide real-time visibility into agent and workflow operations. Events are dispatched at key lifecycle moments, enabling you to build monitoring dashboards, logging systems, analytics pipelines, and real-time notifications.

## Overview

The event system is organized into two main categories:

| Category | Description | Events |
|----------|-------------|--------|
| **Agent Events** | Fired during agent execution lifecycle | `AgentStarted`, `AgentResponded`, `AgentFailed`, `AgentDelegated` |
| **Workflow Events** | Fired during workflow and step execution | `WorkflowStarted`, `WorkflowCompleted`, `WorkflowFailed`, `StepStarted`, `StepCompleted`, `StepFailed`, `WorkflowPaused`, `AgentDelegated` |

## Laravel Event Integration

All events in Agent Orchestrator use Laravel's standard event traits, making them fully compatible with the Laravel ecosystem:

```php
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;

class AgentStarted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    // ...
}
```

This means you can:

- Listen to events using Laravel's event service provider
- Queue event listeners for background processing
- Broadcast events to frontend applications via WebSockets
- Use event subscribers to group related listeners

## Event Flow

### Agent Lifecycle

When an agent processes a message, events are dispatched in this order:

```
User Message
    |
    v
[AgentStarted]
    |
    v
[AgentResponded] or [AgentFailed]
```

### Workflow Lifecycle

Workflows dispatch events at both the workflow and step levels:

```
Workflow Input
    |
    v
[WorkflowStarted]
    |
    v
[StepStarted]
    |
[StepCompleted] or [StepFailed]
    |
    v
[AgentDelegated] (if delegation occurs)
    |
    v
[WorkflowPaused] (if human approval needed)
    |
    v
[WorkflowCompleted] or [WorkflowFailed]
```

## Quick Start

### Registering Listeners

Add event listeners in your `EventServiceProvider`:

```php
<?php

namespace App\Providers;

use AgenticOrchestrator\Events\AgentEvents\AgentStarted;
use AgenticOrchestrator\Events\AgentEvents\AgentResponded;
use AgenticOrchestrator\Events\AgentEvents\AgentFailed;
use AgenticOrchestrator\Events\AgentEvents\AgentDelegated;
use App\Listeners\LogAgentActivity;
use App\Listeners\TrackAgentMetrics;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        AgentStarted::class => [
            LogAgentActivity::class,
        ],
        AgentResponded::class => [
            TrackAgentMetrics::class,
        ],
        AgentFailed::class => [
            LogAgentActivity::class,
        ],
        AgentDelegated::class => [
            LogAgentActivity::class,
        ],
    ];
}
```

### Creating a Simple Listener

```php
<?php

namespace App\Listeners;

use AgenticOrchestrator\Events\AgentEvents\AgentResponded;
use Illuminate\Support\Facades\Log;

class TrackAgentMetrics
{
    public function handle(AgentResponded $event): void
    {
        Log::info('Agent completed', [
            'agent' => $event->agentName,
            'conversation' => $event->conversationId,
            'tokens' => $event->getTotalTokens(),
            'duration_ms' => $event->duration,
        ]);
    }
}
```

## Event Namespaces

Agent Orchestrator provides events in two namespaces with slightly different use cases:

### Primary Events (Recommended)

Located in `AgenticOrchestrator\Events\AgentEvents` and `AgenticOrchestrator\Workflows\Events`:

```php
use AgenticOrchestrator\Events\AgentEvents\AgentStarted;
use AgenticOrchestrator\Events\AgentEvents\AgentResponded;
use AgenticOrchestrator\Events\AgentEvents\AgentFailed;
use AgenticOrchestrator\Events\AgentEvents\AgentDelegated;
use AgenticOrchestrator\Workflows\Events\WorkflowStarted;
use AgenticOrchestrator\Workflows\Events\WorkflowCompleted;
// etc.
```

These events include:
- Serializable properties (agent name, conversation ID, etc.)
- Multi-tenancy support via the `TenantInterface`
- Helper methods for common operations

### Alternative Events

Located in `AgenticOrchestrator\Events\AgentEvents`:

```php
use AgenticOrchestrator\Events\AgentEvents\AgentStarted;
use AgenticOrchestrator\Events\AgentEvents\AgentResponded;
use AgenticOrchestrator\Events\AgentEvents\AgentFailed;
use AgenticOrchestrator\Events\AgentEvents\AgentDelegated;
```

These events include direct references to agent objects rather than serialized data. Use these when you need access to the full agent instance in synchronous listeners.

## Multi-Tenancy Support

Events that include tenant context provide the `getTenantKey()` method:

```php
public function handle(AgentStarted $event): void
{
    if ($tenantKey = $event->getTenantKey()) {
        // Log to tenant-specific channel
        Log::channel("tenant-{$tenantKey}")->info('Agent started', [
            'agent' => $event->agentName,
        ]);
    }
}
```

## Common Use Cases

### Usage Analytics

Track token usage and costs per team:

```php
public function handle(AgentResponded $event): void
{
    UsageRecord::create([
        'tenant_id' => $event->getTenantKey(),
        'agent_name' => $event->agentName,
        'input_tokens' => $event->inputTokens,
        'output_tokens' => $event->outputTokens,
        'total_tokens' => $event->getTotalTokens(),
        'duration_ms' => $event->duration,
        'occurred_at' => now(),
    ]);
}
```

### Error Alerting

Send alerts when agents fail:

```php
public function handle(AgentFailed $event): void
{
    if ($this->isHighPriorityAgent($event->agentName)) {
        Notification::route('slack', config('services.slack.alerts_channel'))
            ->notify(new AgentFailureAlert($event));
    }
}
```

### Performance Monitoring

Track tool execution performance:

```php
public function handle(AgentDelegated $event): void
{
    if ($event->duration > 5000) { // Over 5 seconds
        Log::warning('Slow tool execution', [
            'agent' => $event->agentName,
            'tool' => $event->toolName,
            'duration_ms' => $event->duration,
        ]);
    }
}
```

## In This Section

- **[Agent Events](./agent-events.md)**: Events for agent lifecycle and tool execution
- **[Workflow Events](./workflow-events.md)**: Events for workflow and step execution
- **[Listening](./listening.md)**: Creating and configuring event listeners
- **[Broadcasting](./broadcasting.md)**: Real-time event broadcasting to frontends

## Next Steps

1. Learn about [Agent Events](./agent-events.md) for monitoring agent operations
2. Explore [Workflow Events](./workflow-events.md) for multi-agent orchestration
3. Set up [Event Listeners](./listening.md) for your use case
4. Configure [Broadcasting](./broadcasting.md) for real-time updates
