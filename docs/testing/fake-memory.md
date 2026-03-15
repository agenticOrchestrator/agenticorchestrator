# FakeMemory

`FakeMemory` is an in-memory test double for the memory system, implementing `MemoryInterface`.

## Overview

Use `FakeMemory` to:

- Test agents with memory without external dependencies
- Verify memory operations (store, recall, search)
- Seed test data for conversations
- Assert memory state in tests

## Basic Usage

```php
use AgenticOrchestrator\Testing\FakeMemory;

$memory = FakeMemory::make();

// Store values
$memory->store('user_preference', 'dark_mode');

// Recall values
$value = $memory->recall('user_preference'); // 'dark_mode'

// Check existence
$memory->has('user_preference'); // true
```

## Key-Value Storage

### Storing Values

```php
$memory = FakeMemory::make();

// Simple value
$memory->store('key', 'value');

// With metadata
$memory->store('key', 'value', ['source' => 'user_input']);

// Complex values
$memory->store('user', ['id' => 1, 'name' => 'John']);
```

### Recalling Values

```php
$value = $memory->recall('key');       // Returns value or null
$user = $memory->recall('user');       // Returns array
$missing = $memory->recall('unknown'); // Returns null
```

### Checking Existence

```php
$memory->has('key');     // true
$memory->has('missing'); // false
```

### Forgetting Values

```php
$memory->store('key', 'value');
$memory->forget('key');
$memory->has('key'); // false
```

### Clearing All Storage

```php
$memory->clear(); // Removes all stored values and messages
```

## Conversation History

### Adding Messages

```php
use AgenticOrchestrator\Conversations\Message;

$memory->addMessage(Message::user('Hello'));
$memory->addMessage(Message::assistant('Hi there!'));
$memory->addMessage(Message::user('How are you?'));
```

### Getting History

```php
$history = $memory->getConversationHistory();
// Returns array of Message objects

$recent = $memory->getConversationHistory(limit: 10);
// Returns last 10 messages
```

### Getting All Messages

```php
$messages = $memory->getMessages();
```

## Searching

Search stored values by content:

```php
$memory->store('doc1', 'The quick brown fox');
$memory->store('doc2', 'Lazy dog sleeping');
$memory->store('doc3', 'Fox hunting prey');

$results = $memory->search('fox');

// Returns Collection with matching items:
// [
//     ['key' => 'doc1', 'content' => 'The quick brown fox', 'score' => 1.0, 'metadata' => []],
//     ['key' => 'doc3', 'content' => 'Fox hunting prey', 'score' => 1.0, 'metadata' => []],
// ]
```

With limit:

```php
$results = $memory->search('fox', limit: 1);
```

## Seeding Data

### Seed Key-Value Data

```php
$memory = FakeMemory::make()->seed([
    'user_name' => 'John',
    'user_email' => 'john@example.com',
    'preferences' => ['theme' => 'dark'],
]);

$memory->recall('user_name'); // 'John'
```

## Assertions

### Assert Has Key

```php
$memory->store('exists', 'value');

$memory->assertHas('exists'); // Passes

$memory->assertHas('missing'); // Throws AssertionFailedError
```

### Assert Stored Value

```php
$memory->store('key', 'expected_value');

$memory->assertStored('key', 'expected_value'); // Passes
$memory->assertStored('key', 'wrong_value');    // Throws
```

### Assert Missing Key

```php
$memory->assertMissing('nonexistent'); // Passes

$memory->store('exists', 'value');
$memory->assertMissing('exists'); // Throws
```

### Assert Count

```php
$memory->store('a', 1);
$memory->store('b', 2);

$memory->assertCount(2); // Passes
```

### Assert Empty

```php
$memory = FakeMemory::make();
$memory->assertEmpty(); // Passes

$memory->store('key', 'value');
$memory->assertEmpty(); // Throws
```

### Assert Message Count

```php
$memory->addMessage(Message::user('Hello'));
$memory->addMessage(Message::assistant('Hi'));

$memory->assertMessageCount(2); // Passes
```

### Assert Search Finds Results

```php
$memory->store('doc', 'Contains searchable content');

$memory->assertSearchFinds('searchable'); // Passes
$memory->assertSearchFinds('missing');    // Throws
```

## Utility Methods

### Get All Keys

```php
$keys = $memory->getKeys();
// ['key1', 'key2', 'key3']
```

### Get All Values

```php
$all = $memory->getAll();
// ['key1' => 'value1', 'key2' => 'value2']
```

### Get Namespace

```php
$memory->getNamespace(); // 'fake'
```

### Set Namespace

```php
$memory->setNamespace('team-123');
```

### Reset

```php
$memory->reset(); // Clears storage and messages, returns $this
```

## Complete Test Example

```php
use AgenticOrchestrator\Testing\FakeAgent;
use AgenticOrchestrator\Testing\FakeMemory;
use AgenticOrchestrator\Conversations\Message;

it('maintains conversation context', function () {
    $memory = FakeMemory::make();

    // Seed with previous conversation
    $memory->addMessage(Message::user('My name is John'));
    $memory->addMessage(Message::assistant('Nice to meet you, John!'));

    // Create agent with memory
    $agent = FakeAgent::make()
        ->withMemory($memory)
        ->respondWith('Of course, John! How can I help?');

    $response = $agent->respond('Do you remember my name?');

    expect($response->content)->toContain('John');
    $memory->assertMessageCount(4); // 2 seeded + 2 new
});

it('stores user preferences', function () {
    $memory = FakeMemory::make();

    $memory->store('theme', 'dark');
    $memory->store('language', 'en');

    $memory->assertHas('theme');
    $memory->assertStored('theme', 'dark');
    $memory->assertCount(2);

    $memory->forget('theme');
    $memory->assertMissing('theme');
});
```

## MemoryInterface Compliance

`FakeMemory` implements `MemoryInterface`:

```php
interface MemoryInterface
{
    public function store(string $key, mixed $value, array $metadata = []): void;
    public function recall(string $key): mixed;
    public function has(string $key): bool;
    public function search(string $query, int $limit = 5): Collection;
    public function forget(string $key): void;
    public function clear(): void;
    public function getConversationHistory(int $limit = 50): array;
    public function addMessage(Message $message): void;
    public function getDriver(): string;
    public function getNamespace(): string;
    public function setNamespace(string $namespace): static;
}
```
