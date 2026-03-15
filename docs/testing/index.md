# Testing

The Agent Orchestrator package provides a comprehensive testing framework designed to make it easy to write isolated, reliable tests for your AI agents, tools, memory systems, and workflows. The testing utilities follow Laravel's "fake" pattern, allowing you to replace real implementations with controllable test doubles.

## Overview

Testing AI agent applications presents unique challenges. Responses can be non-deterministic, API calls are expensive, and complex workflows involve multiple interacting components. The testing framework addresses these challenges by providing:

- **Fake implementations** that replace real agents, tools, memory, and workflows
- **Response sequences** that let you control exactly what responses are returned
- **Built-in assertions** that verify interactions and state changes
- **A base test case** with helper methods for common testing scenarios

## Available Test Doubles

| Class | Purpose | Key Features |
|-------|---------|--------------|
| [FakeAgent](fake-agent.md) | Replace agents with controllable responses | Response sequences, call tracking, message assertions |
| [FakeTool](fake-tool.md) | Replace tools with predetermined results | Return value sequences, failure simulation, argument assertions |
| [FakeMemory](fake-memory.md) | Replace memory drivers with in-memory storage | Key-value storage, conversation history, search simulation |
| [FakeWorkflow](fake-workflow.md) | Replace workflows with success/failure/pause modes | State control, input assertions, run tracking |
| [FakeResponse](fake-response.md) | Builder for creating test agent responses | Fluent API, token usage, tool calls |
| [AgentTestCase](agent-test-case.md) | Base test case with helper methods | Agent faking, tool faking, response assertions |

## Quick Start

### Basic Agent Test

```php
<?php

use AgenticOrchestrator\Testing\AgentTestCase;
use AgenticOrchestrator\Testing\FakeAgent;

class CustomerSupportAgentTest extends AgentTestCase
{
    public function test_agent_responds_to_greeting(): void
    {
        // Arrange: Create a fake agent with a predetermined response
        $agent = FakeAgent::make()
            ->respondWith('Hello! How can I help you today?');

        // Act: Send a message to the agent
        $response = $agent->respond('Hi there');

        // Assert: Verify the interaction
        $agent->assertCalled();
        $agent->assertReceivedMessage('Hi there');
        $this->assertStringContainsString('Hello', $response->content);
    }
}
```

### Testing Response Sequences

```php
public function test_multi_turn_conversation(): void
{
    $agent = FakeAgent::make()
        ->respondWith([
            'Hello! What would you like to know?',
            'The capital of France is Paris.',
            'Is there anything else I can help with?',
        ]);

    $response1 = $agent->respond('Hi');
    $response2 = $agent->respond('What is the capital of France?');
    $response3 = $agent->respond('Thanks');

    $this->assertEquals('Hello! What would you like to know?', $response1->content);
    $this->assertEquals('The capital of France is Paris.', $response2->content);
    $this->assertEquals('Is there anything else I can help with?', $response3->content);

    $agent->assertCalledTimes(3);
}
```

### Testing Tools

```php
use AgenticOrchestrator\Testing\FakeTool;

public function test_tool_execution(): void
{
    $tool = FakeTool::make('search')
        ->returns(['results' => ['item1', 'item2', 'item3']]);

    $result = $tool->execute(['query' => 'laravel testing']);

    $tool->assertCalled();
    $tool->assertCalledWith(['query' => 'laravel testing']);
    $this->assertTrue($result->isSuccess());
}
```

### Testing Memory

```php
use AgenticOrchestrator\Testing\FakeMemory;

public function test_memory_storage(): void
{
    $memory = FakeMemory::make();

    $memory->store('user_preference', 'dark_mode');
    $memory->store('last_query', 'weather forecast');

    $memory->assertHas('user_preference');
    $memory->assertStored('user_preference', 'dark_mode');
    $memory->assertCount(2);
}
```

### Testing Workflows

```php
use AgenticOrchestrator\Testing\FakeWorkflow;

public function test_workflow_execution(): void
{
    $workflow = FakeWorkflow::make()
        ->succeedsWith(['status' => 'completed', 'result' => 42]);

    $result = $workflow->run(['input' => 'data']);

    $workflow->assertRan();
    $workflow->assertRanWith(['input' => 'data']);
    $this->assertEquals('completed', $result->output['status']);
}
```

## The Fakes Pattern

The testing framework follows Laravel's established "fake" pattern. Test doubles implement the same interfaces as their real counterparts, making them drop-in replacements. This approach provides several benefits:

1. **Type safety**: Test doubles pass type hints and IDE autocompletion works correctly
2. **Contract compliance**: Fakes implement the same contracts as production classes
3. **Consistent API**: Developers familiar with Laravel testing patterns will feel at home
4. **Flexible behavior**: Configure returns, track calls, and make assertions

## Testing Philosophy

When testing AI agent applications, consider these guidelines:

### Test Deterministically

Replace non-deterministic components with fakes that return predictable responses. This makes tests reliable and fast.

```php
// Good: Predictable test
$agent = FakeAgent::make()->respondWith('Expected response');

// Avoid: Real API calls in unit tests
$agent = $this->app->make(RealAgent::class);
```

### Test Integration Points

Focus on testing how your application handles agent responses rather than testing the AI model itself.

```php
public function test_handles_tool_call_response(): void
{
    $agent = FakeAgent::make()->respondWith(
        FakeResponse::withTools('Searching...', [
            ['id' => 'call_1', 'name' => 'search', 'arguments' => ['q' => 'test']]
        ])
    );

    // Test your code that processes tool calls
}
```

### Test Edge Cases

Use fakes to simulate error conditions and edge cases that would be difficult to reproduce with real services.

```php
public function test_handles_tool_failure(): void
{
    $tool = FakeTool::make('api_call')
        ->shouldFail('Service unavailable');

    $result = $tool->execute(['endpoint' => '/users']);

    $this->assertFalse($result->isSuccess());
}
```

## Next Steps

- [FakeAgent](fake-agent.md) - Learn how to fake agent responses and make assertions
- [FakeTool](fake-tool.md) - Learn how to fake tool execution and simulate failures
- [FakeMemory](fake-memory.md) - Learn how to use in-memory storage for testing
- [FakeWorkflow](fake-workflow.md) - Learn how to fake workflow execution
- [FakeResponse](fake-response.md) - Learn how to build custom test responses
- [AgentTestCase](agent-test-case.md) - Learn about the base test case and its helpers
- [Examples](examples.md) - See complete testing examples for common scenarios
