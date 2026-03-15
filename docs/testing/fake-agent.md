# FakeAgent

The `FakeAgent` class is a test double that implements `AgentInterface`, allowing you to replace real agents with controllable, predictable behavior in your tests.

## Overview

`FakeAgent` provides:

- Predetermined response sequences
- Call tracking and inspection
- Message content assertions
- Memory and tool configuration
- Team and user scoping simulation

## Creating a Fake Agent

### Basic Creation

```php
use AgenticOrchestrator\Testing\FakeAgent;

// Using the static factory method
$agent = FakeAgent::make();

// With a name
$agent = FakeAgent::make()->named('customer-support');

// With a specific model
$agent = FakeAgent::make()->usingModel('gpt-4-turbo', 'openai');
```

### Configuring Responses

#### Single Response

```php
// String response (automatically wrapped in FakeResponse)
$agent = FakeAgent::make()
    ->respondWith('Hello, how can I help?');

// AgentResponse object
$agent = FakeAgent::make()
    ->respondWith(FakeResponse::text('Hello, how can I help?'));

// Custom AgentResponse with metadata
$agent = FakeAgent::make()
    ->respondWith(
        FakeResponse::make()
            ->content('Hello!')
            ->tokens(50, 100)
            ->build()
    );
```

#### Response Sequences

When you need different responses for consecutive calls:

```php
$agent = FakeAgent::make()
    ->respondWith([
        'First response',
        'Second response',
        'Third response',
    ]);

$agent->respond('Message 1'); // Returns "First response"
$agent->respond('Message 2'); // Returns "Second response"
$agent->respond('Message 3'); // Returns "Third response"
$agent->respond('Message 4'); // Returns "Third response" (repeats last)
```

#### Dynamic Responses with Closures

For conditional responses based on input:

```php
$agent = FakeAgent::make()
    ->respondWith(function (string $message, array $context) {
        if (str_contains($message, 'hello')) {
            return 'Hi there!';
        }

        if (str_contains($message, 'bye')) {
            return 'Goodbye!';
        }

        return 'I did not understand that.';
    });

$agent->respond('hello');  // Returns "Hi there!"
$agent->respond('bye');    // Returns "Goodbye!"
$agent->respond('asdf');   // Returns "I did not understand that."
```

Closures can also return `AgentResponse` objects:

```php
$agent = FakeAgent::make()
    ->respondWith(function (string $message, array $context) {
        return FakeResponse::make()
            ->content("Processed: {$message}")
            ->tokens(10, strlen($message))
            ->build();
    });
```

## Configuring Memory and Tools

```php
use AgenticOrchestrator\Testing\FakeMemory;
use AgenticOrchestrator\Testing\FakeTool;

$memory = FakeMemory::make();
$tools = [
    FakeTool::make('search'),
    FakeTool::make('calculator'),
];

$agent = FakeAgent::make()
    ->withMemory($memory)
    ->withTools($tools)
    ->respondWith('Response');
```

## Team and User Scoping

```php
// Scope to a team
$teamAgent = $agent->forTeam(123);

// Scope to a user
$userAgent = $agent->forUser('user_456');

// Chained scoping
$scopedAgent = $agent->forTeam(123)->forUser('user_456');
```

Note: `forTeam()` and `forUser()` return cloned instances to prevent mutation of the original.

## Sending Messages

```php
// Basic message
$response = $agent->respond('Hello');

// With context
$response = $agent->respond('Process this order', [
    'order_id' => 12345,
    'customer' => ['name' => 'John Doe'],
]);

// Streaming (returns StreamResponse)
$stream = $agent->stream('Generate a report');
foreach ($stream as $chunk) {
    echo $chunk;
}
```

## Delegation

```php
$mainAgent = FakeAgent::make()->respondWith('Main agent response');
$delegateAgent = FakeAgent::make()->respondWith('Delegate response');

// Delegate a task
$response = $mainAgent->delegate($delegateAgent, 'Handle this subtask', [
    'context' => 'data',
]);
```

## Assertions

### Call Assertions

| Method | Description |
|--------|-------------|
| `assertCalled()` | Assert the agent was called at least once |
| `assertNotCalled()` | Assert the agent was never called |
| `assertCalledTimes(int $count)` | Assert the agent was called exactly N times |

```php
$agent = FakeAgent::make()->respondWith('Response');

$agent->respond('Message');
$agent->respond('Another message');

$agent->assertCalled();
$agent->assertCalledTimes(2);
```

### Message Assertions

| Method | Description |
|--------|-------------|
| `assertReceivedMessage(string $message)` | Assert exact message was received |
| `assertReceivedMessageContaining(string $substring)` | Assert message containing substring was received |

```php
$agent = FakeAgent::make()->respondWith('Response');

$agent->respond('Hello, can you help me with Laravel?');

$agent->assertReceivedMessage('Hello, can you help me with Laravel?');
$agent->assertReceivedMessageContaining('Laravel');
```

## Inspecting Calls

### Getting Call History

```php
$agent = FakeAgent::make()->respondWith('Response');
$agent->respond('First', ['key' => 'value1']);
$agent->respond('Second', ['key' => 'value2']);

// Get all calls
$calls = $agent->getCalls();
// Returns:
// [
//     ['message' => 'First', 'context' => ['key' => 'value1']],
//     ['message' => 'Second', 'context' => ['key' => 'value2']],
// ]

// Get the last call
$lastCall = $agent->getLastCall();
// Returns: ['message' => 'Second', 'context' => ['key' => 'value2']]
```

### Resetting State

```php
$agent = FakeAgent::make()->respondWith(['First', 'Second']);
$agent->respond('Message');
$agent->assertCalledTimes(1);

$agent->reset();

$agent->assertNotCalled();
$agent->respond('New message'); // Returns "First" again
```

## Interface Methods

`FakeAgent` implements all methods of `AgentInterface`:

| Method | Return Type | Description |
|--------|-------------|-------------|
| `getId()` | `string` | Returns the agent ID (default: `fake-agent-id`) |
| `getName()` | `string` | Returns the agent name (default: `fake-agent`) |
| `getDescription()` | `string` | Returns `'Fake agent for testing'` |
| `getModel()` | `string` | Returns the model (default: `gpt-4`) |
| `getProvider()` | `string` | Returns the provider (default: `openai`) |
| `instructions()` | `string` | Returns `'Fake agent for testing'` |
| `getTools()` | `Collection` | Returns configured tools |
| `getMemory()` | `MemoryInterface` | Returns memory (creates FakeMemory if not set) |
| `getConfig()` | `array` | Returns configuration array |
| `canBeDelegate()` | `bool` | Returns `true` |

## Complete Example

```php
<?php

namespace Tests\Unit;

use AgenticOrchestrator\Testing\AgentTestCase;
use AgenticOrchestrator\Testing\FakeAgent;
use AgenticOrchestrator\Testing\FakeMemory;
use AgenticOrchestrator\Testing\FakeResponse;
use AgenticOrchestrator\Testing\FakeTool;

class CustomerSupportAgentTest extends AgentTestCase
{
    public function test_handles_greeting(): void
    {
        $agent = FakeAgent::make()
            ->named('support')
            ->respondWith('Hello! How can I assist you today?');

        $response = $agent->respond('Hi there!');

        $agent->assertCalled();
        $agent->assertReceivedMessageContaining('Hi');
        $this->assertEquals('Hello! How can I assist you today?', $response->content);
    }

    public function test_handles_multi_turn_conversation(): void
    {
        $agent = FakeAgent::make()
            ->respondWith([
                'What is your order number?',
                'I found your order. It will arrive tomorrow.',
                'You are welcome! Is there anything else?',
            ]);

        $response1 = $agent->respond('Where is my order?');
        $response2 = $agent->respond('Order #12345');
        $response3 = $agent->respond('Thank you!');

        $agent->assertCalledTimes(3);
        $this->assertStringContainsString('order number', $response1->content);
        $this->assertStringContainsString('tomorrow', $response2->content);
    }

    public function test_uses_tools_correctly(): void
    {
        $searchTool = FakeTool::make('order_search')
            ->returns(['order_id' => '12345', 'status' => 'shipped']);

        $agent = FakeAgent::make()
            ->withTools([$searchTool])
            ->respondWith(
                FakeResponse::withTools('Searching for your order...', [
                    ['id' => 'call_1', 'name' => 'order_search', 'arguments' => ['id' => '12345']]
                ])
            );

        $response = $agent->respond('Find order 12345');

        $this->assertTrue($response->hasToolCalls());
        $this->assertCount(1, $response->getToolCalls());
    }

    public function test_dynamic_response_based_on_context(): void
    {
        $agent = FakeAgent::make()
            ->respondWith(function (string $message, array $context) {
                $userId = $context['user_id'] ?? 'unknown';
                return "Hello user {$userId}! Your message was: {$message}";
            });

        $response = $agent->respond('Help me', ['user_id' => 'user_123']);

        $this->assertEquals(
            'Hello user user_123! Your message was: Help me',
            $response->content
        );
    }
}
```

## Best Practices

1. **Use meaningful names** when creating fake agents to make tests self-documenting
2. **Reset state** between tests or use a fresh fake in each test
3. **Prefer response sequences** over dynamic closures when order matters
4. **Use closures** when responses depend on input or context
5. **Assert both calls and responses** for thorough test coverage
