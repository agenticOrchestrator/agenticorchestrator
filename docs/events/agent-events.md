# Agent Events

Agent events are fired during agent lifecycle to enable monitoring, logging, and custom integrations.

## Available Events

| Event | Fired When |
|-------|------------|
| `AgentStarted` | Agent begins processing a request |
| `AgentResponded` | Agent completes a response |
| `AgentFailed` | Agent encounters an error |
| `AgentDelegated` | Agent delegates to another agent |

All agent events are located in the `AgenticOrchestrator\Events\AgentEvents` namespace.

## AgentStarted

Fired when an agent begins processing a request.

```php
use AgenticOrchestrator\Events\AgentEvents\AgentStarted;

Event::listen(AgentStarted::class, function ($event) {
    Log::info('Agent started', [
        'agent' => $event->agent->getName(),
        'message' => $event->message,
    ]);
});
```

### Event Properties

```php
$event->agent;    // The Agent instance
$event->message;  // The input message
```

## AgentResponded

Fired when an agent completes a response.

```php
use AgenticOrchestrator\Events\AgentEvents\AgentResponded;

Event::listen(AgentResponded::class, function ($event) {
    // Log response metrics
    Log::info('Agent responded', [
        'agent' => $event->agent->getName(),
        'tokens' => $event->response->getTotalTokens(),
        'latency' => $event->response->getLatency(),
        'finish_reason' => $event->response->finishReason,
    ]);

    // Track usage for billing
    UsageTracker::record(
        team: $event->agent->getTeam(),
        tokens: $event->response->getTotalTokens(),
    );
});
```

### Event Properties

```php
$event->agent;      // The Agent instance
$event->response;   // The AgentResponse object
```

## AgentFailed

Fired when an agent encounters an error.

```php
use AgenticOrchestrator\Events\AgentEvents\AgentFailed;

Event::listen(AgentFailed::class, function ($event) {
    Log::error('Agent failed', [
        'agent' => $event->agent->getName(),
        'error' => $event->exception->getMessage(),
        'trace' => $event->exception->getTraceAsString(),
    ]);

    // Notify on critical failures
    if ($event->exception instanceof RateLimitException) {
        Notification::send($admins, new RateLimitAlert($event));
    }
});
```

### Event Properties

```php
$event->agent;      // The Agent instance
$event->exception;  // The thrown exception
```

## AgentDelegated

Fired when an agent delegates a task to another agent.

```php
use AgenticOrchestrator\Events\AgentEvents\AgentDelegated;

Event::listen(AgentDelegated::class, function ($event) {
    Log::info('Agent delegated', [
        'from' => $event->fromAgent->getName(),
        'to' => $event->toAgent->getName(),
        'task' => $event->task,
    ]);
});
```

### Event Properties

```php
$event->fromAgent;  // The delegating Agent instance
$event->toAgent;    // The delegate Agent instance
$event->task;       // The delegated task/message
```

## Registering Listeners

### In EventServiceProvider

```php
use AgenticOrchestrator\Events\AgentEvents\AgentStarted;
use AgenticOrchestrator\Events\AgentEvents\AgentResponded;
use AgenticOrchestrator\Events\AgentEvents\AgentFailed;
use AgenticOrchestrator\Events\AgentEvents\AgentDelegated;

protected $listen = [
    AgentStarted::class => [
        LogAgentStart::class,
    ],
    AgentResponded::class => [
        LogAgentResponse::class,
        TrackUsage::class,
    ],
    AgentFailed::class => [
        LogAgentFailure::class,
        NotifyOnFailure::class,
    ],
    AgentDelegated::class => [
        LogAgentDelegation::class,
    ],
];
```

### Using Subscribers

```php
use AgenticOrchestrator\Events\AgentEvents\AgentStarted;
use AgenticOrchestrator\Events\AgentEvents\AgentResponded;
use AgenticOrchestrator\Events\AgentEvents\AgentFailed;
use AgenticOrchestrator\Events\AgentEvents\AgentDelegated;

class AgentEventSubscriber
{
    public function handleStarted(AgentStarted $event): void
    {
        // ...
    }

    public function handleResponded(AgentResponded $event): void
    {
        // ...
    }

    public function handleFailed(AgentFailed $event): void
    {
        // ...
    }

    public function handleDelegated(AgentDelegated $event): void
    {
        // ...
    }

    public function subscribe($events): array
    {
        return [
            AgentStarted::class => 'handleStarted',
            AgentResponded::class => 'handleResponded',
            AgentFailed::class => 'handleFailed',
            AgentDelegated::class => 'handleDelegated',
        ];
    }
}
```

## Common Use Cases

### Usage Tracking

```php
Event::listen(AgentResponded::class, function ($event) {
    DB::table('usage_logs')->insert([
        'team_id' => $event->agent->getTeam()?->id,
        'agent' => $event->agent->getName(),
        'prompt_tokens' => $event->response->getPromptTokens(),
        'completion_tokens' => $event->response->getCompletionTokens(),
        'created_at' => now(),
    ]);
});
```

### Performance Monitoring

```php
Event::listen(AgentStarted::class, function ($event) {
    Cache::put("agent_start:{$event->agent->getId()}", microtime(true));
});

Event::listen(AgentResponded::class, function ($event) {
    $start = Cache::pull("agent_start:{$event->agent->getId()}");
    if ($start) {
        $duration = microtime(true) - $start;

        Metrics::histogram('agent_response_time', $duration, [
            'agent' => $event->agent->getName(),
        ]);
    }
});
```

### Audit Logging

```php
Event::listen(AgentResponded::class, function ($event) {
    AuditLog::create([
        'user_id' => auth()->id(),
        'team_id' => $event->agent->getTeam()?->id,
        'action' => 'agent_response',
        'agent' => $event->agent->getName(),
        'output' => $event->response->content,
        'metadata' => [
            'tokens' => $event->response->getTotalTokens(),
            'tools_used' => collect($event->response->getToolCalls())
                ->pluck('name')->all(),
        ],
    ]);
});
```

## Testing Events

```php
use Illuminate\Support\Facades\Event;
use AgenticOrchestrator\Events\AgentEvents\AgentResponded;

it('fires AgentResponded event', function () {
    Event::fake([AgentResponded::class]);

    $agent = FakeAgent::make()->respondWith('Hello');
    $agent->respond('Hi');

    Event::assertDispatched(AgentResponded::class, function ($event) {
        return $event->response->content === 'Hello';
    });
});
```
