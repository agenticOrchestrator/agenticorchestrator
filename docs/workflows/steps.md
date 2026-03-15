# Workflow Steps

Steps are the building blocks of workflows, defining individual operations to be executed.

## Overview

A workflow consists of sequential or branching steps that:

- Execute specific actions (agent calls, callbacks, conditionals)
- Pass data between each other via context
- Can be paused for human approval
- Support retry and timeout configuration

## Step Types

### AgentStep

Execute an agent and capture its response:

```php
use AgenticOrchestrator\Workflows\Steps\AgentStep;

// Using the static factory method (recommended)
$step = AgentStep::make('sentiment-agent', fn($context) => "Analyze: {$context->get('text')}")
    ->as('analyze-sentiment');

// Using the constructor directly
$step = new AgentStep(
    agent: 'sentiment-agent',  // Agent name or AgentInterface instance
    message: fn($context) => "Analyze: {$context->get('text')}",
);
```

### CallbackStep

Execute custom logic via a callback:

```php
use AgenticOrchestrator\Workflows\Steps\CallbackStep;

// Using the static factory method
$step = CallbackStep::make(function ($context) {
    $data = $context->get('input');
    return ['processed' => strtoupper($data)];
})->as('transform-data');

// Using the constructor directly
$step = new CallbackStep(
    callback: function ($context) {
        return ['result' => $context->get('value') * 2];
    },
);
```

### ConditionalStep

Branch based on conditions:

```php
use AgenticOrchestrator\Workflows\Steps\ConditionalStep;
use AgenticOrchestrator\Workflows\Steps\AgentStep;

// Using the static factory method (recommended)
$step = ConditionalStep::when(
    fn($context) => $context->get('amount') > 1000,
    AgentStep::make('approval-agent', 'Request manager approval')
)->otherwise(
    AgentStep::make('auto-approve-agent', 'Auto-approve this request')
)->as('check-approval');

// Using convenience methods
$step = ConditionalStep::ifHas('premium_user',
    AgentStep::make('premium-agent', 'Premium service')
);

$step = ConditionalStep::ifEquals('status', 'active',
    AgentStep::make('active-agent', 'Handle active status')
);
```

### HumanApprovalStep

Pause workflow for human input:

```php
use AgenticOrchestrator\Workflows\Steps\HumanApprovalStep;

// Using the static factory method
$step = HumanApprovalStep::make('Please review and approve this expense report')
    ->as('manager-approval')
    ->withReviewData(fn($ctx) => [
        'amount' => $ctx->get('expense_amount'),
        'category' => $ctx->get('expense_category'),
    ])
    ->allowActions(['approve', 'reject', 'request-info'])
    ->timeoutAfter(hours: 24)
    ->onTimeout('auto-reject')
    ->notifyVia(['mail', 'slack'])
    ->notifyUsers(fn($ctx) => $ctx->get('approvers'));

// Using the constructor directly
$step = new HumanApprovalStep(
    prompt: 'Review this content before publishing',
);
```

## Step Configuration

### Setting Step Name

```php
$step = AgentStep::make('analyzer-agent', 'Analyze the document')
    ->as('document-analysis');  // Set the step name
```

### With Output Key

Store step results under a specific context key:

```php
$step = AgentStep::make('classifier-agent', 'Classify this document')
    ->as('classify')
    ->outputAs('classification_result');  // Results stored as 'classification_result'
```

### With Retry Logic

```php
$step = AgentStep::make('api-agent', 'Call external API')
    ->as('api-call')
    ->retry(5);  // Retry up to 5 times on failure

// Disable retries
$step = AgentStep::make('critical-agent', 'One-shot operation')
    ->noRetry();
```

### With Timeout

```php
$step = AgentStep::make('report-agent', 'Generate comprehensive report')
    ->as('generate-report')
    ->timeout(120);  // 120 seconds timeout
```

### With Dependencies

Specify context keys that must exist before the step runs:

```php
$step = AgentStep::make('summarizer-agent', 'Summarize the analysis')
    ->as('summarize')
    ->dependsOn(['analysis_result', 'metadata']);  // Requires these context keys
```

### With Human Approval Required

```php
$step = AgentStep::make('publish-agent', 'Publish to production')
    ->as('publish')
    ->requireApproval();  // Workflow will pause for approval before this step
```

## Step Results

Each step produces a `StepResult`:

```php
$result = $step->execute($context);

// Check status
$result->isSuccess();   // true if completed successfully
$result->isFailed();    // true if step failed
$result->isSkipped();   // true if step was skipped
$result->isPending();   // true if step is pending
$result->isWaiting();   // true if waiting for human input

// Flow control
$result->shouldContinue();  // true if workflow should continue
$result->shouldPause();     // true if workflow should pause

// Access data (public readonly properties)
$result->output;      // The step output data
$result->status;      // Status string ('success', 'failed', etc.)
$result->message;     // Optional message
$result->exception;   // Exception if failed
$result->metadata;    // Additional metadata array

// Get metadata
$result->getMeta('key', 'default');

// Convert to array
$result->toArray();
```

### Creating Step Results

```php
use AgenticOrchestrator\Workflows\StepResult;

// Success result
$result = StepResult::success(['data' => $processedData]);

// Failed result
$result = StepResult::failed('Processing failed', $exception);

// Skipped result
$result = StepResult::skipped('Condition not met');

// Pending result
$result = StepResult::pending();

// Waiting for human input
$result = StepResult::waiting('Awaiting approval');
```

## Custom Steps

Create custom step types by extending the abstract `Step` class:

```php
use AgenticOrchestrator\Workflows\Steps\Step;
use AgenticOrchestrator\Workflows\WorkflowContext;
use AgenticOrchestrator\Workflows\StepResult;

class ValidationStep extends Step
{
    public function __construct(
        protected array $rules,
    ) {}

    protected function handle(WorkflowContext $context): mixed
    {
        $data = $context->get('input');
        $validator = Validator::make($data, $this->rules);

        if ($validator->fails()) {
            return StepResult::failed($validator->errors()->first());
        }

        return ['validated' => true, 'data' => $data];
    }
}

// Usage
$step = (new ValidationStep(['email' => 'required|email']))
    ->as('validate-input')
    ->outputAs('validation_result');
```

The `handle()` method can return:
- A `StepResult` instance for explicit control
- Any other value, which will be wrapped in `StepResult::success()`

## Step Events

Steps emit events during execution:

```php
use AgenticOrchestrator\Workflows\Events\StepStarted;
use AgenticOrchestrator\Workflows\Events\StepCompleted;
use AgenticOrchestrator\Workflows\Events\StepFailed;

// Listen for step events
Event::listen(StepStarted::class, function ($event) {
    Log::info("Step started: {$event->step->getName()}");
});

Event::listen(StepCompleted::class, function ($event) {
    Log::info("Step completed: {$event->step->getName()}");
});

Event::listen(StepFailed::class, function ($event) {
    Log::error("Step failed: {$event->step->getName()}", [
        'error' => $event->error,
    ]);
});
```

## Best Practices

1. **Keep steps focused** - Each step should do one thing well
2. **Use meaningful names** - Step names appear in logs and debugging
3. **Handle failures gracefully** - Configure retries for unreliable operations
4. **Use outputAs() for clarity** - Explicitly name your output keys
5. **Test steps independently** - Each step should be testable in isolation
6. **Prefer factory methods** - Use `::make()` for cleaner fluent chains
