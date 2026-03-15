# FakeResponse

`FakeResponse` is a builder class for creating `AgentResponse` objects in tests.

## Overview

Use `FakeResponse` to:

- Build custom agent responses for testing
- Simulate different response scenarios
- Control token usage in tests
- Test tool call handling

## Basic Usage

```php
use AgenticOrchestrator\Testing\FakeResponse;

$response = FakeResponse::make()
    ->content('Hello, world!')
    ->build();

expect($response->content)->toBe('Hello, world!');
```

## Builder Methods

### Setting Content

```php
$response = FakeResponse::make()
    ->content('This is the agent response')
    ->build();
```

### Setting Token Usage

```php
$response = FakeResponse::make()
    ->content('Response text')
    ->tokens(50, 100) // prompt tokens, completion tokens
    ->build();

expect($response->getPromptTokens())->toBe(50);
expect($response->getCompletionTokens())->toBe(100);
expect($response->getTotalTokens())->toBe(150);
```

### Setting Finish Reason

```php
// Normal completion
$response = FakeResponse::make()
    ->content('Complete response')
    ->finishReason('stop')
    ->build();

// Truncated due to length
$response = FakeResponse::make()
    ->content('Truncated...')
    ->finishReason('length')
    ->build();

expect($response->wasTruncated())->toBeTrue();
```

### Adding Tool Calls

```php
$response = FakeResponse::make()
    ->content('I found your order.')
    ->withToolCall(
        id: 'call_123',
        name: 'lookup_order',
        arguments: ['order_id' => '123'],
        result: ['status' => 'shipped']
    )
    ->build();

expect($response->hasToolCalls())->toBeTrue();
expect($response->getToolCalls())->toHaveCount(1);
```

### Multiple Tool Calls

```php
$response = FakeResponse::make()
    ->withToolCall('call_1', 'get_user', ['id' => '1'], ['name' => 'John'])
    ->withToolCall('call_2', 'get_orders', ['user_id' => '1'], ['orders' => [...]])
    ->build();

expect($response->getToolCalls())->toHaveCount(2);
```

### Setting Metadata

```php
$response = FakeResponse::make()
    ->content('Response')
    ->metadata(['model' => 'gpt-4', 'temperature' => 0.7])
    ->build();

expect($response->getMeta('model'))->toBe('gpt-4');
```

### Setting Latency

```php
$response = FakeResponse::make()
    ->content('Response')
    ->latency(150.5)
    ->build();

expect($response->getLatency())->toBe(150.5);
```

## Static Factory Methods

### Simple Text Response

```php
$response = FakeResponse::text('Hello, world!');

// Equivalent to:
// FakeResponse::make()->content('Hello, world!')->build();
```

### Response with Tool Calls

```php
$response = FakeResponse::withTools('Searching...', [
    ['id' => 'call_1', 'name' => 'search', 'arguments' => ['q' => 'test']],
    ['id' => 'call_2', 'name' => 'filter', 'arguments' => ['type' => 'user']],
]);

expect($response->hasToolCalls())->toBeTrue();
expect($response->finishReason)->toBe('tool_calls');
```

### Error Response

```php
$response = FakeResponse::error('Something went wrong');

expect($response->finishReason)->toBe('error');
```

### Truncated Response

```php
$response = FakeResponse::truncated('This response was cut off...');

expect($response->finishReason)->toBe('length');
```

## Complete Example

```php
use AgenticOrchestrator\Testing\FakeResponse;

it('handles complex agent responses', function () {
    $response = FakeResponse::make()
        ->content('I found 2 orders for you.')
        ->tokens(100, 50)
        ->finishReason('stop')
        ->withToolCall(
            id: 'call_abc',
            name: 'search_orders',
            arguments: ['customer_id' => 'CUST-123'],
            result: [
                ['id' => 'ORD-1', 'status' => 'shipped'],
                ['id' => 'ORD-2', 'status' => 'pending'],
            ]
        )
        ->metadata(['request_id' => 'req-abc'])
        ->latency(250.0)
        ->build();

    expect($response->content)->toBe('I found 2 orders for you.');
    expect($response->getTotalTokens())->toBe(150);
    expect($response->isSuccessful())->toBeTrue();
    expect($response->hasToolCalls())->toBeTrue();
    expect($response->getLatency())->toBe(250.0);
});
```

## Using with FakeAgent

`FakeResponse` is typically used with `FakeAgent`:

```php
use AgenticOrchestrator\Testing\FakeAgent;
use AgenticOrchestrator\Testing\FakeResponse;

// FakeAgent accepts string responses (wrapped internally)
$agent = FakeAgent::make()
    ->respondWith('Hello!');

// Or with custom response objects
$agent = FakeAgent::make()
    ->respondWith(
        FakeResponse::make()
            ->content('Custom response')
            ->withToolCall('call_1', 'tool', [], [])
            ->build()
    );
```

## Default Values

When not specified, `FakeResponse` uses these defaults:

| Property | Default Value |
|----------|---------------|
| content | `''` (empty string) |
| promptTokens | `10` |
| completionTokens | `20` |
| finishReason | `'stop'` |
| toolCalls | `[]` (empty array) |
| metadata | `[]` (empty array) |
| latency | `null` |

## Method Reference

| Method | Description |
|--------|-------------|
| `make()` | Create a new builder instance |
| `content(string $content)` | Set response content |
| `tokens(int $prompt, int $completion)` | Set token usage |
| `finishReason(?string $reason)` | Set finish reason |
| `latency(float $latency)` | Set latency in ms |
| `withToolCall(string $id, string $name, array $args, mixed $result)` | Add a tool call |
| `metadata(array $metadata)` | Set metadata |
| `build()` | Build the AgentResponse |
| `text(string $content)` | Static: create simple text response |
| `withTools(string $content, array $toolCalls)` | Static: create response with tools |
| `error(string $message)` | Static: create error response |
| `truncated(string $content)` | Static: create truncated response |
