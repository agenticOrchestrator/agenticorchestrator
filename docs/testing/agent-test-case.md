# AgentTestCase

`AgentTestCase` provides a base test class with helper methods for testing agents.

## Overview

Extend `AgentTestCase` to get:

- Pre-configured testing environment
- Helper methods for common assertions
- Easy fake setup and teardown
- Integration with Laravel's testing tools

## Basic Usage

```php
use AgenticOrchestrator\Testing\AgentTestCase;
use App\Agents\CustomerSupportAgent;

class CustomerSupportAgentTest extends AgentTestCase
{
    public function test_agent_responds_to_greeting(): void
    {
        $agent = $this->createFakeAgent(CustomerSupportAgent::class)
            ->respondWith('Hello! How can I help you today?');

        $response = $agent->respond('Hi there!');

        $this->assertResponseContains($response, 'Hello');
    }
}
```

## Setup Methods

### Creating Fake Agents

```php
// Create a fake version of any agent
$agent = $this->createFakeAgent(MyAgent::class);

// With pre-configured response
$agent = $this->createFakeAgent(MyAgent::class)
    ->respondWith('Expected response');

// With custom tools
$agent = $this->createFakeAgent(MyAgent::class)
    ->withTools([$fakeTool1, $fakeTool2]);
```

### Creating Fake Tools

```php
$tool = $this->createFakeTool('search_orders')
    ->returns(['orders' => [...]]);

$failingTool = $this->createFakeTool('risky_operation')
    ->shouldFail('Connection timeout');
```

### Creating Fake Memory

```php
$memory = $this->createFakeMemory()
    ->seed([
        'user_preference' => 'dark_mode',
        'last_order' => 'ORD-123',
    ]);
```

### Creating Fake Workflows

```php
$workflow = $this->createFakeWorkflow()
    ->succeedsWith(['result' => 'completed']);
```

## Assertion Methods

### Response Assertions

```php
// Assert response contains text
$this->assertResponseContains($response, 'expected text');

// Assert response is successful
$this->assertResponseSuccessful($response);

// Assert response was truncated
$this->assertResponseTruncated($response);

// Assert response has tool calls
$this->assertResponseHasToolCalls($response);

// Assert specific tool was called
$this->assertToolWasCalled($response, 'tool_name');
```

### Token Assertions

```php
// Assert token usage is within limits
$this->assertTokensWithinLimit($response, 1000);

// Assert specific token counts
$this->assertPromptTokens($response, 50);
$this->assertCompletionTokens($response, 100);
```

### Agent Assertions

```php
// Assert agent was called
$this->assertAgentResponded($agent);

// Assert agent was called with specific input
$this->assertAgentRespondedWith($agent, 'expected input');

// Assert agent used specific tool
$this->assertAgentUsedTool($agent, 'tool_name');
```

## Complete Example

```php
use AgenticOrchestrator\Testing\AgentTestCase;
use App\Agents\OrderAgent;

class OrderAgentTest extends AgentTestCase
{
    protected OrderAgent $agent;
    protected $orderTool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderTool = $this->createFakeTool('lookup_order')
            ->returns([
                'id' => 'ORD-123',
                'status' => 'shipped',
                'tracking' => 'TRK-456',
            ]);

        $this->agent = $this->createFakeAgent(OrderAgent::class)
            ->withTools([$this->orderTool])
            ->respondWith('Your order ORD-123 has been shipped!');
    }

    public function test_looks_up_order_status(): void
    {
        $response = $this->agent->respond('Where is my order ORD-123?');

        $this->assertResponseSuccessful($response);
        $this->assertResponseContains($response, 'shipped');
        $this->orderTool->assertCalled();
        $this->orderTool->assertCalledWith(['order_id' => 'ORD-123']);
    }

    public function test_handles_missing_order(): void
    {
        $this->orderTool->shouldFail('Order not found');

        $response = $this->agent->respond('Where is order INVALID?');

        $this->assertResponseContains($response, 'not found');
    }

    public function test_respects_token_limits(): void
    {
        $response = $this->agent->respond('Give me order details');

        $this->assertTokensWithinLimit($response, 500);
    }
}
```

## Integration with Pest

For Pest-style tests, use the helper functions directly:

```php
use AgenticOrchestrator\Testing\FakeAgent;
use AgenticOrchestrator\Testing\FakeTool;

beforeEach(function () {
    $this->orderTool = FakeTool::make('lookup_order')
        ->returns(['status' => 'shipped']);

    $this->agent = FakeAgent::make()
        ->withTools([$this->orderTool])
        ->respondWith('Order shipped!');
});

it('looks up orders', function () {
    $response = $this->agent->respond('Where is my order?');

    expect($response->content)->toContain('shipped');
    $this->orderTool->assertCalled();
});
```

## Traits

### WithFakeAgent

Include in any test class:

```php
use AgenticOrchestrator\Testing\Concerns\WithFakeAgent;

class MyTest extends TestCase
{
    use WithFakeAgent;

    public function test_something(): void
    {
        $agent = $this->fakeAgent()
            ->respondWith('response');

        // ...
    }
}
```

### WithFakeMemory

```php
use AgenticOrchestrator\Testing\Concerns\WithFakeMemory;

class MyTest extends TestCase
{
    use WithFakeMemory;

    public function test_memory_operations(): void
    {
        $memory = $this->fakeMemory()
            ->seed(['key' => 'value']);

        // ...
    }
}
```

## Best Practices

1. **Isolate tests** - Each test should set up its own fakes
2. **Reset state** - Call `reset()` on fakes between tests if reusing
3. **Test edge cases** - Use `shouldFail()` to test error handling
4. **Verify interactions** - Use assertions to verify tools were called correctly
5. **Keep responses realistic** - Use responses similar to what real agents produce
