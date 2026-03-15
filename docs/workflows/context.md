# Workflow Context

`WorkflowContext` manages shared state and data flow between workflow steps.

## Overview

The context:

- Stores input data for the workflow
- Accumulates outputs from each step
- Tracks workflow execution state
- Provides data access for step execution
- Supports array access for convenient data manipulation

## Creating Context

### Basic Context

```php
use AgenticOrchestrator\Workflows\WorkflowContext;

$context = new WorkflowContext([
    'order_id' => 'ORD-123',
    'customer_id' => 'CUST-456',
]);
```

### With Metadata

```php
$context = new WorkflowContext(
    input: ['document' => $documentContent],
    metadata: ['started_at' => now()->toISOString()],
);
```

### From Saved State

Restore a context from a previously saved state:

```php
$savedState = cache()->get("workflow:{$executionId}");
$context = WorkflowContext::fromState($savedState);
```

## Accessing Data

### Get Values

```php
// Get a value (checks data first, then input)
$orderId = $context->get('order_id');

// With default
$priority = $context->get('priority', 'normal');

// Check existence
if ($context->has('customer_email')) {
    // ...
}
```

### Array Access

The context implements `ArrayAccess`:

```php
// Get value
$value = $context['order_id'];

// Set value
$context['processed'] = true;

// Check existence
if (isset($context['customer_email'])) {
    // ...
}

// Unset
unset($context['temporary_data']);
```

### Get All Data

```php
// Get original input data only
$input = $context->getInput();

// Get all data (input merged with step outputs)
$allData = $context->getData();

// Get only step output data
$outputs = $context->getOutputs();

// Get all data via toArray() (same as getData())
$all = $context->toArray();
```

## Modifying Context

### Set Values

```php
// Set single value
$context->set('processed', true);

// Fluent interface
$context->set('status', 'complete')
    ->set('completed_at', now());
```

### Merge Values

```php
// Merge multiple values at once
$context->merge([
    'status' => 'completed',
    'completed_at' => now(),
]);
```

### Clone with Additional Data

```php
// Create a new context with additional data (original unchanged)
$newContext = $context->with([
    'extra_field' => 'value',
]);
```

### Remove Values

```php
$context->forget('temporary_data');
```

## Metadata

Metadata is separate from regular data and is useful for tracking execution information:

```php
// Set metadata
$context->setMeta('started_at', now());
$context->setMeta('user_id', auth()->id());

// Get metadata
$startedAt = $context->getMeta('started_at');
$userId = $context->getMeta('user_id', 'anonymous');

// Get all metadata
$allMeta = $context->getMetadata();
```

## Step Tracking

### Marking Steps Complete

```php
// Mark step as completed
$context->markStepCompleted('analyze');

// Check if step completed
$context->isStepCompleted('analyze'); // true

// Get all completed step names
$completed = $context->getCompletedSteps(); // ['analyze', ...]
```

### Marking Steps Failed

```php
// Mark step as failed
$context->markStepFailed('process', 'Connection timeout', ConnectionException::class);

// Check if step failed
$context->isStepFailed('process'); // true

// Get all failed steps with error info
$failed = $context->getFailedSteps();
// ['process' => ['message' => 'Connection timeout', 'exception' => 'ConnectionException']]
```

### Accessing Step Data

Step output is stored in the context data using the step name or output key:

```php
// In a later step, access previous step's output
$step = AgentStep::make('summarizer', function ($context) {
    // Get output from 'analyze' step (stored as 'analyze' key in data)
    $analysis = $context->get('analyze');
    return "Summarize this analysis: " . json_encode($analysis);
});
```

## Multi-Tenancy Support

### Tenant Scoping

```php
$context = new WorkflowContext(['data' => '...']);
$context->setTenant($tenant);

// Access tenant in steps
$tenant = $context->getTenant();
$tenantId = $context->getTenant()?->getTenantKey();
```

### User Context

```php
$context->setUser($user);

// Access in steps
$user = $context->getUser();
$userId = $context->getUser()?->id;
```

## Serialization

### For Persistence

```php
// Get full state for storage
$state = $context->getState();

// Returns:
[
    'input' => ['order_id' => 'ORD-123'],
    'data' => ['processed' => true],
    'metadata' => ['started_at' => '2024-01-15T10:00:00Z'],
    'completed_steps' => ['analyze', 'process'],
    'failed_steps' => [],
    'tenant_id' => 'tenant-123',
    'user_id' => 42,
]

// Store in database or cache
WorkflowRun::create([
    'workflow_id' => $workflow->id,
    'state' => $state,
]);

// Restore from storage
$context = WorkflowContext::fromState($state);
```

### JSON Serialization

The context implements `JsonSerializable`:

```php
// Serializes to the full state
$json = json_encode($context);
```

## Complete Example

```php
use AgenticOrchestrator\Workflows\WorkflowContext;

// Create workflow context
$context = new WorkflowContext([
    'document' => $uploadedDocument,
    'options' => [
        'language' => 'en',
        'format' => 'summary',
    ],
]);

// Set tenant scope
$context->setTenant($tenant);

// Set metadata
$context->setMeta('started_at', now()->toISOString());

// Execute steps (simplified)
$analysisResult = $analyzeStep->execute($context);
$context->markStepCompleted('analyze');

// The step's outputAs() or default name stores results automatically
// Access via context->get()
$analysis = $context->get('analyze');

$summaryResult = $summarizeStep->execute($context);
$context->markStepCompleted('summarize');

// Check completion
if ($context->isStepCompleted('summarize')) {
    $summary = $context->get('summarize');

    // Store final result
    $context->set('final_result', $summary);
}

// Get full state for persistence
$state = $context->getState();
```

## Best Practices

1. **Use descriptive keys** - Make context keys self-documenting
2. **Keep input immutable** - Use `set()` or `merge()` to add data, avoid modifying input
3. **Use metadata for tracking** - Store execution info in metadata, not regular data
4. **Serialize carefully** - Ensure all data in context can be JSON serialized
5. **Clean up** - Use `forget()` to remove temporary data before final persistence
