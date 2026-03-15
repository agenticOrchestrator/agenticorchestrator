# Workflow Events

Events fired during workflow execution for monitoring, logging, and custom integrations.

## Available Events

| Event | Fired When |
|-------|------------|
| `WorkflowStarted` | Workflow begins execution |
| `WorkflowCompleted` | Workflow completes successfully |
| `WorkflowFailed` | Workflow encounters an error |
| `WorkflowPaused` | Workflow pauses for human input |
| `StepStarted` | Step begins execution |
| `StepCompleted` | Step completes successfully |
| `StepFailed` | Step encounters an error |
| `AgentDelegated` | Agent delegation occurs within workflow |

All workflow events are located in the `AgenticOrchestrator\Workflows\Events` namespace.

## WorkflowStarted

Fired when a workflow begins execution.

```php
use AgenticOrchestrator\Workflows\Events\WorkflowStarted;

Event::listen(WorkflowStarted::class, function ($event) {
    Log::info('Workflow started', [
        'execution_id' => $event->executionId,
        'workflow' => $event->workflowName,
        'input' => $event->input,
        'tenant' => $event->getTenantKey(),
    ]);
});
```

### Event Properties

```php
$event->executionId;   // Unique execution identifier
$event->workflowName;  // Name of the workflow
$event->input;         // Input data array
$event->tenant;        // Tenant object (if multi-tenancy enabled)

// Methods
$event->getTenantKey(); // Get tenant key (or null)
```

## WorkflowCompleted

Fired when a workflow completes successfully.

```php
use AgenticOrchestrator\Workflows\Events\WorkflowCompleted;

Event::listen(WorkflowCompleted::class, function ($event) {
    Log::info('Workflow completed', [
        'execution_id' => $event->executionId,
        'duration_ms' => $event->duration,
        'duration_s' => $event->getDurationInSeconds(),
        'output' => $event->output,
    ]);

    // Track metrics
    Metrics::histogram('workflow_duration', $event->getDurationInSeconds(), [
        'workflow' => 'order_processing',
    ]);
});
```

### Event Properties

```php
$event->executionId;  // Unique execution identifier
$event->output;       // Workflow output data
$event->duration;     // Duration in milliseconds

// Methods
$event->getDurationInSeconds(); // Duration as float in seconds
```

## WorkflowFailed

Fired when a workflow encounters an error.

```php
use AgenticOrchestrator\Workflows\Events\WorkflowFailed;

Event::listen(WorkflowFailed::class, function ($event) {
    Log::error('Workflow failed', [
        'execution_id' => $event->executionId,
        'error' => $event->error,
        'exception_class' => $event->getExceptionClass(),
    ]);

    // Alert on critical workflows
    Notification::send($admins, new WorkflowFailureAlert($event));
});
```

### Event Properties

```php
$event->executionId;  // Unique execution identifier
$event->error;        // Error message
$event->exception;    // The thrown exception (if available)

// Methods
$event->getExceptionClass(); // Get exception class name
$event->getExceptionTrace(); // Get exception stack trace
```

## WorkflowPaused

Fired when a workflow pauses (e.g., for human approval).

```php
use AgenticOrchestrator\Workflows\Events\WorkflowPaused;

Event::listen(WorkflowPaused::class, function ($event) {
    Log::info('Workflow paused', [
        'execution_id' => $event->executionId,
        'paused_at' => $event->pausedAt,
        'reason' => $event->reason,
        'has_state' => $event->hasState(),
    ]);

    // Notify users who need to take action
    $this->notifyApprovers($event);
});
```

### Event Properties

```php
$event->executionId;  // Unique execution identifier
$event->pausedAt;     // Step name where paused
$event->reason;       // Reason for pausing
$event->state;        // Workflow state for resumption

// Methods
$event->hasState();   // Check if state is available
```

## Step Events

### StepStarted

```php
use AgenticOrchestrator\Workflows\Events\StepStarted;

Event::listen(StepStarted::class, function ($event) {
    Log::debug('Step started', [
        'execution_id' => $event->executionId,
        'step' => $event->stepName,
        'index' => $event->stepIndex,
    ]);
});
```

#### Event Properties

```php
$event->executionId;  // Unique execution identifier
$event->stepName;     // Name of the step
$event->stepIndex;    // Step index in workflow (0-based)
```

### StepCompleted

```php
use AgenticOrchestrator\Workflows\Events\StepCompleted;

Event::listen(StepCompleted::class, function ($event) {
    Log::debug('Step completed', [
        'execution_id' => $event->executionId,
        'step' => $event->stepName,
        'duration_ms' => $event->duration,
        'output' => $event->output,
    ]);

    // Track step performance
    Metrics::timing('step_duration', $event->duration, [
        'step' => $event->stepName,
    ]);
});
```

#### Event Properties

```php
$event->executionId;  // Unique execution identifier
$event->stepName;     // Name of the step
$event->output;       // Step output data
$event->duration;     // Duration in milliseconds
```

### StepFailed

```php
use AgenticOrchestrator\Workflows\Events\StepFailed;

Event::listen(StepFailed::class, function ($event) {
    Log::error('Step failed', [
        'execution_id' => $event->executionId,
        'step' => $event->stepName,
        'error' => $event->error,
    ]);
});
```

#### Event Properties

```php
$event->executionId;  // Unique execution identifier
$event->stepName;     // Name of the step
$event->error;        // Error message
$event->exception;    // The thrown exception (if available)
```

## AgentDelegated (Workflow Context)

Fired when agent delegation occurs within a workflow.

```php
use AgenticOrchestrator\Workflows\Events\AgentDelegated;

Event::listen(AgentDelegated::class, function ($event) {
    Log::info('Agent delegated in workflow', [
        'execution_id' => $event->executionId,
        'from_agent' => $event->fromAgent,
        'to_agent' => $event->toAgent,
        'task' => $event->task,
    ]);
});
```

## Event Subscribers

```php
use AgenticOrchestrator\Workflows\Events\WorkflowStarted;
use AgenticOrchestrator\Workflows\Events\WorkflowCompleted;
use AgenticOrchestrator\Workflows\Events\WorkflowFailed;
use AgenticOrchestrator\Workflows\Events\StepCompleted;

class WorkflowEventSubscriber
{
    public function handleWorkflowStarted(WorkflowStarted $event): void
    {
        // Start tracking
    }

    public function handleWorkflowCompleted(WorkflowCompleted $event): void
    {
        // Record success
    }

    public function handleWorkflowFailed(WorkflowFailed $event): void
    {
        // Handle failure
    }

    public function handleStepCompleted(StepCompleted $event): void
    {
        // Track progress
    }

    public function subscribe($events): array
    {
        return [
            WorkflowStarted::class => 'handleWorkflowStarted',
            WorkflowCompleted::class => 'handleWorkflowCompleted',
            WorkflowFailed::class => 'handleWorkflowFailed',
            StepCompleted::class => 'handleStepCompleted',
        ];
    }
}
```

## Common Use Cases

### Progress Tracking

```php
Event::listen(StepCompleted::class, function ($event) {
    // Update progress based on step index
    $totalSteps = 5; // Known total
    $progress = ($event->stepIndex + 1) / $totalSteps * 100;

    broadcast(new WorkflowProgress(
        executionId: $event->executionId,
        progress: $progress,
        currentStep: $event->stepName,
    ));
});
```

### Audit Trail

```php
Event::listen(WorkflowCompleted::class, function ($event) {
    WorkflowAudit::create([
        'execution_id' => $event->executionId,
        'output' => $event->output,
        'duration_ms' => $event->duration,
        'completed_at' => now(),
    ]);
});
```

### SLA Monitoring

```php
Event::listen(WorkflowCompleted::class, function ($event) {
    $slaMs = 30000; // 30 seconds

    if ($event->duration > $slaMs) {
        Log::warning('Workflow exceeded SLA', [
            'execution_id' => $event->executionId,
            'sla_ms' => $slaMs,
            'actual_ms' => $event->duration,
        ]);
    }
});
```

## Testing Events

```php
use Illuminate\Support\Facades\Event;
use AgenticOrchestrator\Workflows\Events\WorkflowCompleted;

it('fires WorkflowCompleted event', function () {
    Event::fake([WorkflowCompleted::class]);

    $workflow = new TestWorkflow();
    $workflow->run(['input' => 'data']);

    Event::assertDispatched(WorkflowCompleted::class, function ($event) {
        return $event->output !== null;
    });
});
```
