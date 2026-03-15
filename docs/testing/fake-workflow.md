# FakeWorkflow

`FakeWorkflow` is a test double for testing workflow interactions without executing real workflow logic.

## Overview

Use `FakeWorkflow` to:

- Test components that depend on workflows
- Simulate success, failure, and pause states
- Verify workflows are run with correct inputs
- Test workflow resumption scenarios

## Basic Usage

```php
use AgenticOrchestrator\Testing\FakeWorkflow;

$workflow = FakeWorkflow::make()
    ->succeedsWith(['result' => 'completed']);

$result = $workflow->run(['input' => 'data']);

expect($result->isSuccess())->toBeTrue();
expect($result->getOutput())->toBe(['result' => 'completed']);
```

## Creating Fake Workflows

### Simple Creation

```php
$workflow = FakeWorkflow::make();
```

### With Name

```php
$workflow = FakeWorkflow::make()->named('OrderProcessingWorkflow');
```

## Configuring Outcomes

### Success with Output

```php
$workflow = FakeWorkflow::make()
    ->succeedsWith([
        'order_id' => '123',
        'status' => 'processed',
    ]);

$result = $workflow->run([]);
expect($result->isSuccess())->toBeTrue();
expect($result->getOutput()['order_id'])->toBe('123');
```

### Dynamic Success with Closure

```php
$workflow = FakeWorkflow::make()
    ->succeedsWith(fn ($input) => [
        'processed' => $input['data'],
        'timestamp' => now(),
    ]);

$result = $workflow->run(['data' => 'test']);
expect($result->getOutput()['processed'])->toBe('test');
```

### Failure with Error

```php
$workflow = FakeWorkflow::make()
    ->fails('Validation failed', 'validate-input');

$result = $workflow->run([]);

expect($result->isFailed())->toBeTrue();
expect($result->error)->toBe('Validation failed');
```

### Pause at Step

```php
$workflow = FakeWorkflow::make()
    ->pausesAt('approval-step', ['pending_approval' => true]);

$result = $workflow->run([]);

expect($result->isPaused())->toBeTrue();
```

## Assertions

### Assert Workflow Ran

```php
$workflow = FakeWorkflow::make()->succeedsWith([]);

$workflow->run(['key' => 'value']);

$workflow->assertRan(); // Passes
```

### Assert Not Ran

```php
$workflow = FakeWorkflow::make()->succeedsWith([]);

$workflow->assertNotRan(); // Passes - never executed
```

### Assert Run Count

```php
$workflow->run([]);
$workflow->run([]);
$workflow->run([]);

$workflow->assertRanTimes(3);
```

### Assert Ran With Input

```php
$workflow->run(['user_id' => '123', 'action' => 'process']);

$workflow->assertRanWith(['user_id' => '123', 'action' => 'process']);
```

### Assert Ran With Key

```php
$workflow->run(['important_key' => 'value', 'other' => 'data']);

$workflow->assertRanWithKey('important_key');
```

## Inspecting Runs

### Get All Runs

```php
$runs = $workflow->getRuns();

// [
//     ['input' => ['user_id' => '123']],
//     ['input' => ['user_id' => '456']],
// ]
```

### Get Last Run Input

```php
$workflow->run(['first' => 1]);
$workflow->run(['second' => 2]);

$input = $workflow->getLastRunInput();
// ['second' => 2]
```

## Team Scoping

```php
$workflow = FakeWorkflow::make()->succeedsWith([]);

$scopedWorkflow = $workflow->forTeam($team);

// Returns a cloned instance scoped to the team
```

## Resetting State

```php
$workflow->run(['a' => 1]);
$workflow->run(['b' => 2]);

$workflow->reset();

expect($workflow->getRuns())->toBeEmpty();
```

## Complete Test Example

```php
use AgenticOrchestrator\Testing\FakeWorkflow;

it('processes orders through workflow', function () {
    $workflow = FakeWorkflow::make()
        ->named('OrderProcessingWorkflow')
        ->succeedsWith([
            'order_id' => 'ORD-123',
            'status' => 'completed',
            'tracking' => 'TRK-456',
        ]);

    // Simulate order processing
    $result = $workflow->run([
        'customer_id' => 'CUST-789',
        'items' => [['sku' => 'WIDGET-1', 'qty' => 2]],
    ]);

    expect($result->isSuccess())->toBeTrue();
    expect($result->getOutput()['tracking'])->toBe('TRK-456');

    $workflow->assertRan();
    $workflow->assertRanWithKey('customer_id');
});

it('handles workflow failures', function () {
    $workflow = FakeWorkflow::make()
        ->fails('Payment declined', 'payment-step');

    $result = $workflow->run(['payment_method' => 'card']);

    expect($result->isFailed())->toBeTrue();
    expect($result->error)->toContain('declined');
});

it('handles human-in-the-loop workflows', function () {
    $workflow = FakeWorkflow::make()
        ->pausesAt('manager-approval', [
            'requires_approval' => true,
            'amount' => 5000,
        ]);

    $result = $workflow->run(['expense_id' => 'EXP-123']);

    expect($result->isPaused())->toBeTrue();
});

it('uses dynamic output based on input', function () {
    $workflow = FakeWorkflow::make()
        ->succeedsWith(fn ($input) => [
            'greeting' => "Hello, {$input['name']}!",
        ]);

    $result = $workflow->run(['name' => 'Alice']);

    expect($result->getOutput()['greeting'])->toBe('Hello, Alice!');
});
```

## WorkflowInterface Compliance

`FakeWorkflow` implements `WorkflowInterface`:

```php
interface WorkflowInterface
{
    public function definition(): WorkflowDefinition;
    public function forTeam(int|string|object $team): static;
}
```

The `run()` method is additional functionality for testing purposes.
