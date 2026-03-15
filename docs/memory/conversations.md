# Conversation Memory

Manage conversation history to maintain context across agent interactions.

## Overview

Conversation memory allows agents to:

- Remember previous messages
- Maintain context across turns
- Reference earlier parts of the conversation
- Build coherent multi-turn dialogues

## Basic Usage

### Enabling Conversation Memory

```php
use AgenticOrchestrator\Agents\Agent;

class AssistantAgent extends Agent
{
    protected array $memory = [
        'driver' => 'cache',
        'ttl' => 3600, // 1 hour
    ];
}
```

### Using in Conversations

```php
$agent = AssistantAgent::make();

// First message
$response1 = $agent->respond('My name is John');
// "Nice to meet you, John!"

// Second message - agent remembers context
$response2 = $agent->respond('What is my name?');
// "Your name is John!"
```

## Message Types

### User Messages

```php
use AgenticOrchestrator\Conversations\Message;

$message = Message::user('Hello, how are you?');
```

### Assistant Messages

```php
$message = Message::assistant('I am doing well, thank you!');
```

### System Messages

```php
$message = Message::system('You are a helpful assistant.');
```

### Tool Messages

```php
$message = Message::tool(
    toolCallId: 'call_abc123',
    content: json_encode(['temperature' => 72]),
    metadata: ['name' => 'weather_lookup']
);
```

The `tool()` method signature is: `Message::tool(string $toolCallId, string $content, array $metadata = [])`

## Managing Conversation History

### Adding Messages

```php
$memory = $agent->getMemory();

$memory->addMessage(Message::user('Hello'));
$memory->addMessage(Message::assistant('Hi there!'));
```

### Getting History

```php
// Get all history
$history = $memory->getConversationHistory();

// Get last N messages
$recent = $memory->getConversationHistory(limit: 10);
```

### Clearing History

```php
// Clear all memory including conversation history
$memory->clear();
```

> **Note**: There is no separate `clearConversation()` method. The `clear()` method removes all memory including conversation history for the current namespace.

## Conversation Windowing

### Limit Context Size

```php
class MyAgent extends Agent
{
    protected array $memory = [
        'driver' => 'cache',
        'conversation_limit' => 20, // Keep last 20 messages
    ];
}
```

### Token-Based Windowing

> **Note**: The `getConversationHistory()` method only accepts a `limit` parameter for the number of messages. Token-based windowing must be implemented in the agent layer.

```php
class MyAgent extends Agent
{
    protected array $memory = [
        'driver' => 'cache',
        'max_tokens' => 4000, // Keep messages up to 4000 tokens
    ];

    protected function getConversationContext(): array
    {
        // Get messages and filter by token count in your agent logic
        $messages = $this->memory->getConversationHistory(limit: 100);

        // Implement token counting and filtering here
        return $this->filterByTokenLimit($messages, $this->memory['max_tokens']);
    }
}
```

## Session Management

### Session-Based Conversations

```php
$agent = MyAgent::make()
    ->withSession($request->session()->getId());

// Conversation is tied to user session
$response = $agent->respond($message);
```

### User-Based Conversations

```php
$agent = MyAgent::make()
    ->withUser($request->user());

// Conversation persists per user
$response = $agent->respond($message);
```

### Starting New Conversations

```php
// Start fresh conversation
$agent->resetConversation();

// Or create new session
$agent = MyAgent::make()
    ->withSession(Str::uuid());
```

## Conversation Context

### Adding Context to Messages

```php
$memory->addMessage(Message::user('Process this order', [
    'order_id' => 'ORD-123',
    'timestamp' => now(),
]));
```

### Accessing Message Context

```php
foreach ($memory->getConversationHistory() as $message) {
    echo "Role: {$message->role->value}\n";  // role is a MessageRole enum
    echo "Content: {$message->content}\n";
    echo "Context: " . json_encode($message->metadata) . "\n";
}
```

## Conversation Summarization

### Auto-Summarization

```php
class MyAgent extends Agent
{
    protected array $memory = [
        'driver' => 'cache',
        'summarize_after' => 20, // Summarize after 20 messages
    ];

    protected function summarizeConversation(): void
    {
        $history = $this->memory->getConversationHistory();

        $summary = $this->respond(
            'Summarize this conversation: ' . json_encode($history)
        );

        // Clear all memory and add summary as system message
        $this->memory->clear();
        $this->memory->addMessage(Message::system(
            "Previous conversation summary: {$summary->content}"
        ));
    }
}
```

### Manual Summarization

```php
// Get summary from agent
$summary = $agent->respond('Summarize our conversation so far');

// Store summary
$memory->store('conversation_summary', $summary->content);

// Clear all memory and add summary
$memory->clear();
$memory->addMessage(Message::system("Previous: {$summary->content}"));
```

## Multi-Agent Conversations

### Sharing Context

```php
use AgenticOrchestrator\Facades\Memory;

// Create a shared memory instance with the same namespace
$sharedMemory = Memory::driver('cache')
    ->forNamespace('shared-conversation');

$agent1 = ResearchAgent::make()->withMemory($sharedMemory);
$agent2 = WriterAgent::make()->withMemory($sharedMemory);

// Both agents share conversation history
$agent1->respond('Research AI trends');
$agent2->respond('Write an article based on the research');
```

### Agent Handoff

```php
// Get conversation from first agent
$history = $agent1->getMemory()->getConversationHistory();

// Transfer to second agent by adding messages one by one
foreach ($history as $message) {
    $agent2->getMemory()->addMessage($message);
}

// Continue conversation
$response = $agent2->respond('Continue from where we left off');
```

> **Note**: There is no `setConversationHistory()` method. To transfer history, iterate over messages and add them individually using `addMessage()`.

## Persistence

### Database Storage

```php
class MyAgent extends Agent
{
    protected array $memory = [
        'driver' => 'database',
        'table' => 'conversations',
    ];
}
```

### Exporting Conversations

```php
// Export to JSON
$history = $memory->getConversationHistory();
$json = json_encode($history);
file_put_contents('conversation.json', $json);

// Import from JSON
$data = json_decode(file_get_contents('conversation.json'), true); // true for associative array
foreach ($data as $msg) {
    $memory->addMessage(Message::fromArray($msg));
}
```

## Testing

```php
use AgenticOrchestrator\Testing\FakeMemory;

it('maintains conversation context', function () {
    $memory = FakeMemory::make();

    $memory->addMessage(Message::user('My name is Alice'));
    $memory->addMessage(Message::assistant('Hello Alice!'));

    $history = $memory->getConversationHistory();

    expect($history)->toHaveCount(2);
    expect($history[0]->content)->toContain('Alice');
});
```

## Best Practices

1. **Limit history size** - Prevent context overflow
2. **Use summarization** - For long conversations
3. **Clear appropriately** - Don't keep stale context
4. **Namespace sessions** - Prevent cross-user leakage
5. **Handle persistence** - Save important conversations
6. **Test conversation flows** - Verify context is maintained
