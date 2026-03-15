# Workflow Results

`WorkflowResult` represents the outcome of a workflow execution.

## Overview

A workflow result contains:

- Execution identifier
- Final status (success, failed, paused/waiting)
- Output data from completed steps
- Error information if failed
- The workflow context with full state
- Execution duration and metadata

## Result States

### Success

```php
use AgenticOrchestrator\Workflows\WorkflowResult;

$result = $runner->run($workflow, $input);

if ($result->isSuccess()) {
    $output = $result->getOutput();
    // Process successful result
}
```

### Failed

```php
if ($result->isFailed()) {
    $error = $result->error;
    $exception = $result->exception;

    Log::error("Workflow failed: {$error}", [
        'exception' => $exception?->getMessage(),
    ]);
}
```

### Paused (Waiting for Human Input)

```php
if ($result->isPaused()) {
    // Workflow is waiting for human approval or input
    $state = $result->getState();

    // Store for later resumption
    cache()->put("workflow:{$result->executionId}", $state, now()->addDays(7));
}
```

## Result Properties

The `WorkflowResult` uses public readonly properties:

```php
// Unique execution identifier
$result->executionId;  // string

// Execution status ('success', 'failed', 'waiting', 'pending')
$result->status;  // string

// The workflow output data
$result->output;  // mixed

// The workflow context with full state
$result->context;  // WorkflowContext

// Execution duration in milliseconds
$result->duration;  // float

// Additional metadata
$result->metadata;  // array

// Error message (if failed)
$result->error;  // ?string

// Exception (if failed)
$result->exception;  // ?Throwable
```

## Accessing Output

### Get All Output

```php
$output = $result->getOutput();

// Returns the workflow output data
// Structure depends on your workflow
```

### Get Specific Output Key

```php
// Get a specific key from output (if output is an array)
$analysis = $result->get('analysis');
$summary = $result->get('summary', 'No summary available');
```

## Accessing State

### Get Full State for Persistence

```php
$state = $result->getState();

// Returns the context state:
[
    'input' => [...],
    'data' => [...],
    'metadata' => [...],
    'completed_steps' => ['step1', 'step2'],
    'failed_steps' => [],
    'tenant_id' => 'tenant-123',
    'user_id' => 42,
]
```

### Get Step Information

```php
// Get completed step names
$completed = $result->getCompletedSteps();
// ['analyze', 'process', 'summarize']

// Get failed step info
$failed = $result->getFailedSteps();
// ['step-name' => ['message' => 'Error message', 'exception' => 'ExceptionClass']]
```

### Get Metadata

```php
$value = $result->getMeta('key', 'default');
```

## Result Transformation

### To Array

```php
$array = $result->toArray();

// Returns:
[
    'execution_id' => 'exec-123',
    'status' => 'success',
    'output' => [...],
    'duration_ms' => 5230.5,
    'metadata' => [...],
    'completed_steps' => ['step1', 'step2'],
    'failed_steps' => [],
    'error' => null,
    'state' => [...],
]
```

### JSON Serialization

The result implements `JsonSerializable`:

```php
$json = json_encode($result);
```

### For API Response

```php
return response()->json([
    'status' => $result->isSuccess() ? 'completed' : 'failed',
    'data' => $result->getOutput(),
    'execution_id' => $result->executionId,
    'duration_ms' => $result->duration,
]);
```

## Working with Paused Workflows

### Storing Pause State

```php
if ($result->isPaused()) {
    $pauseData = [
        'execution_id' => $result->executionId,
        'state' => $result->getState(),
        'partial_output' => $result->getOutput(),
        'paused_at' => now()->toISOString(),
    ];

    cache()->put("paused_workflow:{$result->executionId}", $pauseData, now()->addDays(7));
}
```

### Resuming Workflow

```php
// Retrieve pause data
$pauseData = cache()->get("paused_workflow:{$executionId}");

// Recreate context from state
$context = WorkflowContext::fromState($pauseData['state']);

// Add human input
$context->set('approval_decision', $humanDecision);
$context->set('approved_by', auth()->id());

// Resume workflow execution
$result = $runner->resume($workflow, $context);
```

## Error Handling

### Getting Error Details

```php
if ($result->isFailed()) {
    $errorMessage = $result->error;
    $exception = $result->exception;

    // Log detailed error
    Log::error('Workflow failed', [
        'execution_id' => $result->executionId,
        'message' => $errorMessage,
        'exception' => $exception?->getMessage(),
        'trace' => $exception?->getTraceAsString(),
        'failed_steps' => $result->getFailedSteps(),
    ]);
}
```

## Complete Example

```php
use App\Workflows\OrderProcessingWorkflow;
use AgenticOrchestrator\Workflows\WorkflowRunner;
use AgenticOrchestrator\Workflows\WorkflowContext;

$runner = app(WorkflowRunner::class);

$result = $runner->run(OrderProcessingWorkflow::class, [
    'order_id' => 'ORD-123',
    'customer_id' => 'CUST-456',
]);

if ($result->isSuccess()) {
    // Get final output
    $confirmation = $result->getOutput();

    // Send confirmation
    $this->sendConfirmation($confirmation);

    // Log success
    Log::info('Order processed', [
        'order_id' => 'ORD-123',
        'execution_id' => $result->executionId,
        'duration_ms' => $result->duration,
        'completed_steps' => $result->getCompletedSteps(),
    ]);

} elseif ($result->isPaused()) {
    // Handle human-in-the-loop
    $state = $result->getState();

    // Store for later resumption
    cache()->put("workflow:{$result->executionId}", $state, now()->addDay());

    // Notify approvers
    $this->notifyApprovers($result->executionId);

} else {
    // Handle failure
    Log::error('Order processing failed', [
        'order_id' => 'ORD-123',
        'execution_id' => $result->executionId,
        'error' => $result->error,
        'failed_steps' => $result->getFailedSteps(),
    ]);

    $this->notifyFailure($result);
}
```
