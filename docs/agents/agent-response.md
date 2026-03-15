# Agent Response

The `AgentResponse` class represents the output from an agent's response to a message.

## Overview

When you call `respond()` on an agent, you receive an `AgentResponse` object containing:

- The generated text content
- Any tool calls made
- Token usage statistics
- Response metadata
- Latency information

## Basic Usage

```php
use App\Agents\MyAgent;

$response = MyAgent::make()->respond('Hello, how can you help me?');

// Access the text content
echo $response->content;

// Check token usage
echo "Tokens used: " . $response->getTotalTokens();
```

## Properties

### Content

The main text response from the agent:

```php
$response->content; // string
```

### Tool Calls

If the agent used tools during the response:

```php
if ($response->hasToolCalls()) {
    foreach ($response->getToolCalls() as $toolCall) {
        echo "Tool: " . $toolCall['name'];
        echo "Arguments: " . json_encode($toolCall['arguments']);
        echo "Result: " . json_encode($toolCall['result']);
    }
}
```

### Usage Statistics

Token consumption for billing and monitoring:

```php
$response->getPromptTokens();     // Input tokens
$response->getCompletionTokens(); // Output tokens
$response->getTotalTokens();      // Total tokens
```

### Finish Reason

Why the response generation stopped:

```php
$response->finishReason; // 'stop', 'end_turn', 'length', 'error', etc.

// Check specific conditions
$response->isSuccessful();  // Completed normally (finish_reason is 'stop', 'end_turn', or null)
$response->wasTruncated();  // Hit token limit (finish_reason is 'length')
```

### Latency

Response time in milliseconds:

```php
$response->getLatency(); // float|null
```

### Metadata

Additional response metadata:

```php
$response->metadata;            // array
$response->getMeta('key');      // Get specific value
$response->getMeta('key', 'default'); // With default
```

## Creating Responses

### From Provider Response

```php
$response = AgentResponse::fromProviderResponse([
    'content' => 'Hello!',
    'usage' => [
        'prompt_tokens' => 10,
        'completion_tokens' => 5,
        'total_tokens' => 15,
    ],
    'finish_reason' => 'stop',
]);
```

### Empty/Error Response

```php
$response = AgentResponse::empty('An error occurred');
```

## Serialization

### To Array

```php
$array = $response->toArray();

// Returns:
[
    'content' => 'Response text',
    'tool_calls' => [...],
    'usage' => [
        'prompt_tokens' => 10,
        'completion_tokens' => 20,
        'total_tokens' => 30,
    ],
    'metadata' => [...],
    'latency' => 150.5,
    'finish_reason' => 'stop',
]
```

### To JSON

```php
$json = $response->toJson();
$json = (string) $response; // Same as content
```

## Tool Call Structure

Each tool call in the response contains:

```php
[
    'tool_call_id' => 'call_abc123',  // Unique call ID
    'name' => 'lookup_order',         // Tool name
    'arguments' => ['id' => '123'],   // Input arguments
    'result' => ['status' => 'ok'],   // Tool result
]
```

## Response in Controllers

```php
class ChatController extends Controller
{
    public function chat(Request $request)
    {
        $response = MyAgent::make()
            ->forTeam($request->user()->currentTeam)
            ->respond($request->input('message'));

        return response()->json([
            'message' => $response->content,
            'tokens' => $response->getTotalTokens(),
        ]);
    }
}
```

## Response with Streaming

Use the `stream()` method for real-time token streaming:

```php
$stream = MyAgent::make()->stream('Generate a long response');

foreach ($stream as $chunk) {
    echo $chunk->content;
    flush();
}
```

See the [Streaming documentation](../api-reference/streaming.md) for details on StreamResponse, StreamChunk, and StreamHandler.
