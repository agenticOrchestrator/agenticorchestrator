# McpClient

The `McpClient` class provides a connection to a single MCP server. It handles server initialization, tool discovery, and tool execution.

## Creating a Client

### From Configuration Array

Create a client with a configuration array:

```php
use AgenticOrchestrator\Mcp\McpClient;

$client = McpClient::make([
    'url' => 'http://localhost:3000',
    'transport' => 'sse',
    'api_key' => 'your-api-key',
    'timeout' => 30,
    'headers' => [
        'X-Custom-Header' => 'value',
    ],
]);
```

### From URL

Create a client directly from a URL:

```php
$client = McpClient::url('http://localhost:3000');
```

### Using Fluent Methods

Configure the client using fluent methods:

```php
$client = McpClient::make()
    ->transport('sse')
    ->withApiKey('your-api-key')
    ->withHeaders(['X-Tenant-Id' => 'tenant-123'])
    ->timeout(60);
```

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `url` | string | `http://localhost:3000` | The MCP server URL |
| `transport` | string | `sse` | Transport type: `sse` or `stdio` |
| `api_key` | string | `null` | API key for authentication |
| `timeout` | int | `30` | Request timeout in seconds |
| `headers` | array | `[]` | Custom HTTP headers |

## Connecting to the Server

Call `connect()` to initialize the connection and discover available tools:

```php
$client = McpClient::url('http://localhost:3000')
    ->withApiKey('secret-key')
    ->connect();

// Check connection status
if ($client->isConnected()) {
    echo "Connected successfully!";
}

// Get server capabilities
$capabilities = $client->getCapabilities();
// ['tools' => true, 'resources' => true, 'prompts' => true]
```

The `connect()` method:

1. Sends an initialization request to the server
2. Negotiates protocol version and capabilities
3. Automatically discovers available tools
4. Returns the client instance for method chaining

## Working with Tools

### Discovering Tools

After connecting, tools are automatically discovered:

```php
$client->connect();

// Get all available tools
$tools = $client->getTools();

foreach ($tools as $tool) {
    echo $tool->getName() . ': ' . $tool->getDescription() . "\n";
}
```

### Getting a Specific Tool

```php
// Get a tool by name
$tool = $client->getTool('search_files');

if ($tool) {
    $schema = $tool->toSchema();
}

// Check if a tool exists
if ($client->hasTool('search_files')) {
    // Tool is available
}
```

### Executing Tools

Execute a tool directly through the client:

```php
$result = $client->executeTool('search_files', [
    'query' => '*.php',
    'directory' => '/src',
    'recursive' => true,
]);

// $result contains the tool output
print_r($result);
```

Tool execution automatically connects if not already connected:

```php
$client = McpClient::url('http://localhost:3000');

// Will auto-connect before executing
$result = $client->executeTool('read_file', [
    'path' => '/etc/config.json',
]);
```

## Working with Resources

MCP servers can expose resources (files, database records, etc.) that can be listed and read:

### Listing Resources

```php
$resources = $client->getResources();

foreach ($resources as $resource) {
    echo $resource['uri'] . ': ' . $resource['name'] . "\n";
}
```

### Reading a Resource

```php
$contents = $client->readResource('file:///path/to/document.txt');

// $contents is an array of content items
foreach ($contents as $content) {
    echo $content['text'];
}
```

## Working with Prompts

MCP servers can provide pre-built prompts for common tasks:

### Listing Prompts

```php
$prompts = $client->getPrompts();

foreach ($prompts as $prompt) {
    echo $prompt['name'] . ': ' . $prompt['description'] . "\n";
}
```

### Getting a Prompt

```php
$prompt = $client->getPrompt('code_review', [
    'language' => 'php',
    'code' => '<?php echo "Hello";',
]);

// Use the prompt messages in your agent
$messages = $prompt['messages'];
```

## Connection Management

### Disconnecting

Release server resources when done:

```php
$client->disconnect();

// Client state is reset
echo $client->isConnected(); // false
echo count($client->getTools()); // 0
```

### Connection Status

```php
$client = McpClient::make();

echo $client->isConnected(); // false

$client->connect();

echo $client->isConnected(); // true
```

## Error Handling

The client throws exceptions for connection and execution errors:

```php
use AgenticOrchestrator\Exceptions\ToolExecutionException;

try {
    $client = McpClient::url('http://invalid-server:3000')
        ->timeout(5)
        ->connect();
} catch (\RuntimeException $e) {
    // Connection failed
    Log::error('MCP connection failed: ' . $e->getMessage());
}

try {
    $result = $client->executeTool('unknown_tool', []);
} catch (ToolExecutionException $e) {
    // Tool execution failed
    Log::error('Tool execution failed: ' . $e->getMessage());
}
```

## Logging

The client logs connection and error events:

```php
// Successful connection logs (debug level)
// "MCP client connected" with server URL and capabilities

// Failed connection logs (error level)
// "MCP connection failed" with server URL and error message

// Failed tool discovery logs (warning level)
// "Failed to list MCP tools" with server URL
```

## Full Example

```php
use AgenticOrchestrator\Mcp\McpClient;
use AgenticOrchestrator\Exceptions\ToolExecutionException;
use Illuminate\Support\Facades\Log;

class FileSystemService
{
    private McpClient $client;

    public function __construct()
    {
        $this->client = McpClient::make([
            'url' => config('mcp.servers.filesystem.url'),
            'api_key' => config('mcp.servers.filesystem.api_key'),
            'timeout' => 60,
        ]);
    }

    public function connect(): void
    {
        $this->client->connect();

        Log::info('Connected to filesystem MCP server', [
            'tools' => array_map(
                fn($t) => $t->getName(),
                $this->client->getTools()
            ),
        ]);
    }

    public function searchFiles(string $pattern, string $directory): array
    {
        try {
            return $this->client->executeTool('search_files', [
                'pattern' => $pattern,
                'directory' => $directory,
                'recursive' => true,
            ]);
        } catch (ToolExecutionException $e) {
            Log::error('File search failed', [
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function readFile(string $path): ?string
    {
        try {
            $result = $this->client->executeTool('read_file', [
                'path' => $path,
            ]);

            return $result['content'] ?? null;
        } catch (ToolExecutionException $e) {
            return null;
        }
    }

    public function disconnect(): void
    {
        $this->client->disconnect();
    }
}
```

## Related

- [McpTool](./mcp-tools.md): Working with MCP tools
- [McpToolProvider](./mcp-provider.md): Managing multiple MCP servers
- [Configuration](./configuration.md): Configuring MCP servers
