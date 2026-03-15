# Streaming

Stream agent responses in real-time for better user experience.

## Basic Streaming

```php
use App\Agents\MyAgent;

$agent = MyAgent::make();

foreach ($agent->stream('Write a long essay about AI') as $chunk) {
    echo $chunk->content;
    flush();
}
```

## Stream Response Structure

Each chunk contains:

```php
$chunk->content;    // The text content of this chunk
$chunk->isLast;     // Whether this is the final chunk
$chunk->index;      // Chunk index (0-based)
$chunk->delta;      // Raw delta from provider
```

## HTTP Streaming

### Controller Implementation

```php
use App\Agents\WriterAgent;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function stream(Request $request)
    {
        $message = $request->input('message');
        $agent = WriterAgent::make();

        return new StreamedResponse(function () use ($agent, $message) {
            foreach ($agent->stream($message) as $chunk) {
                echo "data: " . json_encode([
                    'content' => $chunk->content,
                    'done' => $chunk->isLast,
                ]) . "\n\n";

                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
```

### Server-Sent Events (SSE)

```php
public function sseStream(Request $request)
{
    $agent = MyAgent::make();

    return response()->stream(function () use ($agent, $request) {
        foreach ($agent->stream($request->message) as $chunk) {
            echo "event: message\n";
            echo "data: " . json_encode(['text' => $chunk->content]) . "\n\n";

            if (connection_aborted()) {
                break;
            }

            ob_flush();
            flush();
        }

        echo "event: done\n";
        echo "data: {}\n\n";
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
    ]);
}
```

## Laravel Livewire Integration

```php
use Livewire\Component;
use App\Agents\AssistantAgent;

class ChatComponent extends Component
{
    public string $message = '';
    public string $response = '';
    public bool $isStreaming = false;

    public function send()
    {
        $this->isStreaming = true;
        $this->response = '';

        $agent = AssistantAgent::make();

        foreach ($agent->stream($this->message) as $chunk) {
            $this->response .= $chunk->content;
            $this->stream('response', $this->response);
        }

        $this->isStreaming = false;
        $this->message = '';
    }
}
```

## JavaScript Client

### Fetch API with ReadableStream

```javascript
async function streamResponse(message) {
    const response = await fetch('/api/chat/stream', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message }),
    });

    const reader = response.body.getReader();
    const decoder = new TextDecoder();

    while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        const text = decoder.decode(value);
        const lines = text.split('\n');

        for (const line of lines) {
            if (line.startsWith('data: ')) {
                const data = JSON.parse(line.slice(6));
                updateUI(data.content);
            }
        }
    }
}
```

### EventSource API

```javascript
function connectSSE(message) {
    const eventSource = new EventSource(
        `/api/chat/sse?message=${encodeURIComponent(message)}`
    );

    eventSource.onmessage = (event) => {
        const data = JSON.parse(event.data);
        appendToChat(data.text);
    };

    eventSource.addEventListener('done', () => {
        eventSource.close();
    });

    eventSource.onerror = () => {
        eventSource.close();
    };
}
```

## Stream with Tool Calls

When streaming with tools, you receive both content and tool call chunks:

```php
foreach ($agent->stream($message) as $chunk) {
    if ($chunk->isToolCall) {
        // Handle tool call notification
        echo "🔧 Calling tool: {$chunk->toolName}\n";
    } else {
        // Handle content
        echo $chunk->content;
    }
}
```

## Stream Events

### Listening to Stream Events

```php
use AgenticOrchestrator\Events\AgentStreaming;

Event::listen(AgentStreaming::class, function ($event) {
    // Log streaming progress
    Log::debug('Stream chunk', [
        'agent' => $event->agent->getName(),
        'index' => $event->chunk->index,
        'content_length' => strlen($event->chunk->content),
    ]);
});
```

## Stream Options

### With Timeout

```php
$stream = $agent->stream($message, [
    'timeout' => 120, // seconds
]);
```

### With Abort Signal

```php
$abortController = new AbortController();

$stream = $agent->stream($message, [
    'signal' => $abortController->signal,
]);

// Later, to abort:
$abortController->abort();
```

## Collecting Streamed Response

If you need the full response after streaming:

```php
$fullContent = '';
$chunks = [];

foreach ($agent->stream($message) as $chunk) {
    echo $chunk->content;
    $fullContent .= $chunk->content;
    $chunks[] = $chunk;
}

// Now you have both real-time output and full response
$response = AgentResponse::fromStreamChunks($chunks);
```

## Error Handling

```php
try {
    foreach ($agent->stream($message) as $chunk) {
        echo $chunk->content;
        flush();
    }
} catch (StreamInterruptedException $e) {
    Log::warning('Stream interrupted', [
        'reason' => $e->getMessage(),
        'chunks_received' => $e->getChunksReceived(),
    ]);
} catch (ProviderException $e) {
    Log::error('Stream failed', ['error' => $e->getMessage()]);
}
```

## Testing Streams

```php
use AgenticOrchestrator\Testing\FakeAgent;

it('streams response', function () {
    $agent = FakeAgent::make()
        ->streamsWith(['Hello', ' ', 'World', '!']);

    $content = '';
    foreach ($agent->stream('Hi') as $chunk) {
        $content .= $chunk->content;
    }

    expect($content)->toBe('Hello World!');
});
```

## Best Practices

1. **Buffer appropriately** - Use `ob_flush()` and `flush()` together
2. **Handle disconnects** - Check `connection_aborted()` in loops
3. **Set proper headers** - Disable buffering with `X-Accel-Buffering: no`
4. **Implement timeouts** - Don't let streams run indefinitely
5. **Provide fallback** - Have non-streaming option for unsupported clients
6. **Log stream metrics** - Track completion rates and durations
