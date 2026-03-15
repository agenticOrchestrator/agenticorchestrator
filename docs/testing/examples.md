# Testing Examples

Comprehensive examples for testing agents, tools, workflows, and memory.

## Agent Testing

### Basic Agent Response

```php
use AgenticOrchestrator\Testing\FakeAgent;

it('responds to user messages', function () {
    $agent = FakeAgent::make()
        ->respondWith('Hello! I am here to help.');

    $response = $agent->respond('Hi!');

    expect($response->content)->toBe('Hello! I am here to help.');
    expect($response->isSuccessful())->toBeTrue();
});
```

### Sequential Responses

```php
it('gives different responses over time', function () {
    $agent = FakeAgent::make()
        ->respondWith([
            'First response',
            'Second response',
            'Third response',
        ]);

    expect($agent->respond('msg 1')->content)->toBe('First response');
    expect($agent->respond('msg 2')->content)->toBe('Second response');
    expect($agent->respond('msg 3')->content)->toBe('Third response');
});
```

### Dynamic Responses

```php
it('generates responses based on input', function () {
    $agent = FakeAgent::make()
        ->respondWith(fn ($message) => "You said: {$message}");

    $response = $agent->respond('Hello world');

    expect($response->content)->toBe('You said: Hello world');
});
```

### Agent with Tools

```php
use AgenticOrchestrator\Testing\FakeAgent;
use AgenticOrchestrator\Testing\FakeTool;

it('uses tools to answer questions', function () {
    $weatherTool = FakeTool::make('get_weather')
        ->returns(['temperature' => 72, 'conditions' => 'sunny']);

    $agent = FakeAgent::make()
        ->withTools([$weatherTool])
        ->respondWith('The weather is sunny and 72 degrees.');

    $response = $agent->respond('What is the weather?');

    expect($response->content)->toContain('sunny');
    $weatherTool->assertCalled();
});
```

## Tool Testing

### Basic Tool Execution

```php
use AgenticOrchestrator\Testing\FakeTool;

it('returns configured values', function () {
    $tool = FakeTool::make('calculate_total')
        ->returns(['total' => 99.99]);

    $result = $tool->execute(['items' => [...]]);

    expect($result->isSuccess())->toBeTrue();
    expect($result->result['total'])->toBe(99.99);
});
```

### Tool Failure

```php
it('handles tool failures gracefully', function () {
    $tool = FakeTool::make('external_api')
        ->shouldFail('Service unavailable');

    $result = $tool->execute(['endpoint' => '/users']);

    expect($result->isSuccess())->toBeFalse();
    expect($result->error)->toBe('Service unavailable');
});
```

### Verifying Tool Arguments

```php
it('receives correct arguments', function () {
    $tool = FakeTool::make('send_email')
        ->returns(['sent' => true]);

    $tool->execute([
        'to' => 'user@example.com',
        'subject' => 'Hello',
        'body' => 'Message content',
    ]);

    $tool->assertCalledWith([
        'to' => 'user@example.com',
        'subject' => 'Hello',
        'body' => 'Message content',
    ]);
});
```

### Tool Call Sequence

```php
it('tracks all calls in order', function () {
    $tool = FakeTool::make('counter')
        ->returns([
            ['count' => 1],
            ['count' => 2],
            ['count' => 3],
        ]);

    $tool->execute([]);
    $tool->execute([]);
    $tool->execute([]);

    $tool->assertCalledTimes(3);

    $calls = $tool->getCalls();
    expect($calls)->toHaveCount(3);
});
```

## Memory Testing

### Key-Value Storage

```php
use AgenticOrchestrator\Testing\FakeMemory;

it('stores and retrieves values', function () {
    $memory = FakeMemory::make();

    $memory->store('user_name', 'John');
    $memory->store('preferences', ['theme' => 'dark']);

    expect($memory->recall('user_name'))->toBe('John');
    expect($memory->recall('preferences')['theme'])->toBe('dark');
});
```

### Seeding Test Data

```php
it('starts with seeded data', function () {
    $memory = FakeMemory::make()->seed([
        'session_id' => 'abc123',
        'user_role' => 'admin',
    ]);

    expect($memory->has('session_id'))->toBeTrue();
    expect($memory->recall('user_role'))->toBe('admin');
});
```

### Conversation History

```php
use AgenticOrchestrator\Conversations\Message;

it('maintains conversation context', function () {
    $memory = FakeMemory::make();

    $memory->addMessage(Message::user('What is 2+2?'));
    $memory->addMessage(Message::assistant('2+2 equals 4.'));
    $memory->addMessage(Message::user('And what about 3+3?'));

    $history = $memory->getConversationHistory();

    expect($history)->toHaveCount(3);
    expect($history[0]->role)->toBe('user');
    expect($history[1]->role)->toBe('assistant');
});
```

### Memory Search

```php
it('searches stored content', function () {
    $memory = FakeMemory::make();

    $memory->store('doc1', 'Laravel is a PHP framework');
    $memory->store('doc2', 'React is a JavaScript library');
    $memory->store('doc3', 'PHP runs on the server');

    $results = $memory->search('PHP');

    expect($results)->toHaveCount(2);
});
```

## Workflow Testing

### Successful Workflow

```php
use AgenticOrchestrator\Testing\FakeWorkflow;

it('completes workflow successfully', function () {
    $workflow = FakeWorkflow::make()
        ->succeedsWith([
            'order_id' => 'ORD-123',
            'status' => 'completed',
        ]);

    $result = $workflow->run(['customer_id' => 'CUST-456']);

    expect($result->isSuccess())->toBeTrue();
    expect($result->getOutput()['order_id'])->toBe('ORD-123');
});
```

### Failed Workflow

```php
it('handles workflow failures', function () {
    $workflow = FakeWorkflow::make()
        ->fails('Payment processing failed', 'payment-step');

    $result = $workflow->run(['amount' => 100]);

    expect($result->isFailed())->toBeTrue();
    expect($result->error)->toContain('Payment');
});
```

### Paused Workflow (Human-in-the-Loop)

```php
it('pauses for human approval', function () {
    $workflow = FakeWorkflow::make()
        ->pausesAt('manager-approval', [
            'pending' => true,
            'amount' => 5000,
        ]);

    $result = $workflow->run(['expense_id' => 'EXP-789']);

    expect($result->isPaused())->toBeTrue();
});
```

### Dynamic Workflow Results

```php
it('generates results based on input', function () {
    $workflow = FakeWorkflow::make()
        ->succeedsWith(fn ($input) => [
            'processed' => true,
            'customer' => $input['customer_id'],
            'timestamp' => now()->toIso8601String(),
        ]);

    $result = $workflow->run(['customer_id' => 'CUST-999']);

    expect($result->getOutput()['customer'])->toBe('CUST-999');
});
```

## Integration Testing

### Complete Agent Scenario

```php
use AgenticOrchestrator\Testing\FakeAgent;
use AgenticOrchestrator\Testing\FakeTool;
use AgenticOrchestrator\Testing\FakeMemory;

it('handles complete customer support interaction', function () {
    // Setup memory with customer context
    $memory = FakeMemory::make()->seed([
        'customer_id' => 'CUST-123',
        'tier' => 'premium',
    ]);

    // Setup tools
    $orderTool = FakeTool::make('lookup_order')
        ->returns([
            'id' => 'ORD-456',
            'status' => 'shipped',
            'tracking' => 'TRK-789',
        ]);

    $refundTool = FakeTool::make('process_refund')
        ->returns(['refund_id' => 'REF-001', 'status' => 'approved']);

    // Setup agent
    $agent = FakeAgent::make()
        ->withMemory($memory)
        ->withTools([$orderTool, $refundTool])
        ->respondWith([
            'I found your order. It shipped yesterday!',
            'I have processed your refund request.',
        ]);

    // First interaction - order inquiry
    $response1 = $agent->respond('Where is my order?');
    expect($response1->content)->toContain('shipped');
    $orderTool->assertCalled();

    // Second interaction - refund request
    $response2 = $agent->respond('I want a refund');
    expect($response2->content)->toContain('refund');
    $refundTool->assertCalled();

    // Verify interaction count
    $agent->assertRespondedTimes(2);
});
```

### Testing with Team Scoping

```php
it('scopes operations to team', function () {
    $team = (object)['id' => 1, 'name' => 'Acme Corp'];

    $agent = FakeAgent::make()
        ->respondWith('Team-specific response');

    $scopedAgent = $agent->forTeam($team);

    $response = $scopedAgent->respond('Hello');

    expect($response->content)->toBe('Team-specific response');
});
```

### Testing Error Recovery

```php
it('recovers from transient failures', function () {
    $tool = FakeTool::make('flaky_api')
        ->returns([
            null, // First call fails
            null, // Second call fails
            ['success' => true], // Third call succeeds
        ]);

    $agent = FakeAgent::make()
        ->withTools([$tool])
        ->respondWith([
            'Trying again...',
            'Trying again...',
            'Success!',
        ]);

    // Simulate retry logic
    for ($i = 0; $i < 3; $i++) {
        $response = $agent->respond('Try the operation');
    }

    $tool->assertCalledTimes(3);
});
```

## Best Practices

### 1. Test One Thing at a Time

```php
// Good - focused test
it('validates order ID format', function () {
    $tool = FakeTool::make('lookup_order')
        ->shouldFail('Invalid order ID format');

    $result = $tool->execute(['order_id' => 'invalid']);

    expect($result->error)->toContain('Invalid');
});

// Bad - testing too much
it('validates and processes and sends email', function () {
    // Too many concerns in one test
});
```

### 2. Use Descriptive Test Names

```php
// Good
it('returns error when order not found', function () { });
it('sends confirmation email after successful payment', function () { });

// Bad
it('test1', function () { });
it('works', function () { });
```

### 3. Reset State Between Tests

```php
beforeEach(function () {
    $this->tool = FakeTool::make('api')->returns([]);
    $this->memory = FakeMemory::make();
});

afterEach(function () {
    $this->tool->reset();
    $this->memory->reset();
});
```

### 4. Test Edge Cases

```php
it('handles empty input', function () {
    $agent = FakeAgent::make()->respondWith('Please provide input.');

    $response = $agent->respond('');

    expect($response->content)->toContain('provide input');
});

it('handles very long input', function () {
    $longInput = str_repeat('word ', 10000);

    $agent = FakeAgent::make()->respondWith('Input received.');

    $response = $agent->respond($longInput);

    expect($response->isSuccessful())->toBeTrue();
});
```
