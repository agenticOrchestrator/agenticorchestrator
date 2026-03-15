# FakeTool

`FakeTool` is a test double for testing tool interactions without executing real tool logic.

## Overview

Use `FakeTool` to:

- Simulate tool responses in tests
- Test agent behavior with different tool results
- Verify tools are called with correct arguments
- Simulate tool failures

## Basic Usage

```php
use AgenticOrchestrator\Testing\FakeTool;

$fake = FakeTool::make('search_orders')
    ->returns(['orders' => [...]]);

$result = $fake->execute(['customer_id' => '123']);

expect($result->isSuccess())->toBeTrue();
expect($result->result)->toBe(['orders' => [...]]);
```

## Creating Fake Tools

### Simple Creation

```php
$fake = FakeTool::make('tool_name');
```

### With Description

```php
$fake = FakeTool::make('search')
    ->describedAs('Search for items in the database');
```

## Configuring Return Values

### Single Return Value

```php
$fake = FakeTool::make('get_user')
    ->returns(['id' => 1, 'name' => 'John']);
```

### Sequence of Values

```php
$fake = FakeTool::make('counter')
    ->returns([
        ['count' => 1],
        ['count' => 2],
        ['count' => 3],
    ]);

$fake->execute([]); // ['count' => 1]
$fake->execute([]); // ['count' => 2]
$fake->execute([]); // ['count' => 3]
$fake->execute([]); // ['count' => 3] (repeats last)
```

### Dynamic Values with Closure

```php
$fake = FakeTool::make('echo')
    ->returns(fn ($args) => ['echoed' => $args['message']]);

$result = $fake->execute(['message' => 'Hello']);
// ['echoed' => 'Hello']
```

## Simulating Failures

```php
$fake = FakeTool::make('risky_operation')
    ->shouldFail('Connection timeout');

$result = $fake->execute([]);

expect($result->isSuccess())->toBeFalse();
expect($result->error)->toBe('Connection timeout');
```

## Assertions

### Assert Called

```php
$fake = FakeTool::make('tool')->returns([]);

$fake->execute(['key' => 'value']);

$fake->assertCalled();
```

### Assert Not Called

```php
$fake = FakeTool::make('tool')->returns([]);

$fake->assertNotCalled(); // Passes - never executed
```

### Assert Call Count

```php
$fake->execute([]);
$fake->execute([]);

$fake->assertCalledTimes(2);
```

### Assert Called With Arguments

```php
$fake->execute(['user_id' => '123', 'action' => 'update']);

$fake->assertCalledWith(['user_id' => '123', 'action' => 'update']);
```

### Assert Called With Key

```php
$fake->execute(['important_key' => 'value']);

$fake->assertCalledWithKey('important_key');
```

## Inspecting Calls

### Get All Calls

```php
$calls = $fake->getCalls();

// [
//     ['arguments' => ['user_id' => '123']],
//     ['arguments' => ['user_id' => '456']],
// ]
```

### Get Last Call Arguments

```php
$args = $fake->getLastCallArguments();
// ['user_id' => '456']
```

## Resetting State

```php
$fake->execute(['a' => 1]);
$fake->execute(['b' => 2]);

$fake->reset();

expect($fake->getCalls())->toBeEmpty();
$fake->execute([]); // Returns first configured result again
```

## Complete Test Example

```php
use AgenticOrchestrator\Testing\FakeAgent;
use AgenticOrchestrator\Testing\FakeTool;

it('uses search tool correctly', function () {
    // Create fake tool
    $searchTool = FakeTool::make('search_products')
        ->returns([
            'products' => [
                ['id' => 1, 'name' => 'Widget'],
                ['id' => 2, 'name' => 'Gadget'],
            ],
        ]);

    // Create fake agent with the tool
    $agent = FakeAgent::make()
        ->withTools([$searchTool])
        ->respondWith('I found 2 products matching your search.');

    // Execute
    $response = $agent->respond('Find products like widget');

    // Verify tool was called
    $searchTool->assertCalled();
    $searchTool->assertCalledWithKey('query');
});

it('handles tool failure gracefully', function () {
    $tool = FakeTool::make('external_api')
        ->shouldFail('API unavailable');

    $result = $tool->execute(['endpoint' => '/users']);

    expect($result->isSuccess())->toBeFalse();
    expect($result->error)->toContain('unavailable');
});
```

## ToolInterface Compliance

`FakeTool` implements `ToolInterface`, so it can be used anywhere a real tool is expected:

```php
interface ToolInterface
{
    public function getName(): string;
    public function getDescription(): string;
    public function execute(array $arguments): mixed;
    public function toSchema(): array;
    public function isParallel(): bool;
    public function validate(array $arguments): bool;
    public function getParameters(): array;
    public function isCacheable(): bool;
    public function getCacheTtl(): int;
}
```

All methods have sensible defaults for testing purposes.
