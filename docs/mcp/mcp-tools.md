# McpTool

The `McpTool` class represents a tool provided by an MCP server. It implements the `ToolInterface`, making it fully compatible with the Agent Orchestrator tool system.

## Overview

MCP tools are automatically created when you connect to an MCP server. Each tool discovered from the server is wrapped in an `McpTool` instance that:

- Provides the tool's schema for LLM function calling
- Validates arguments before execution
- Executes the tool through the MCP client
- Returns results as `ToolResult` objects

## Getting MCP Tools

### From McpClient

```php
use AgenticOrchestrator\Mcp\McpClient;

$client = McpClient::url('http://localhost:3000')->connect();

// Get all tools
$tools = $client->getTools();

// Get a specific tool
$searchTool = $client->getTool('search_files');
```

### From McpToolProvider

```php
use AgenticOrchestrator\Mcp\McpToolProvider;

$provider = McpToolProvider::make()
    ->addServer('filesystem', ['url' => 'http://localhost:3001'])
    ->connect();

// Tools are prefixed with server name
$tool = $provider->getTool('filesystem:search_files');
```

## Tool Properties

### Name and Description

```php
$tool = $client->getTool('search_files');

echo $tool->getName();
// "search_files"

echo $tool->getDescription();
// "Search for files matching a pattern"
```

### Input Schema

The input schema defines the parameters the tool accepts:

```php
$schema = $tool->getInputSchema();

// Example schema:
// [
//     'type' => 'object',
//     'properties' => [
//         'pattern' => [
//             'type' => 'string',
//             'description' => 'File pattern to search for',
//         ],
//         'directory' => [
//             'type' => 'string',
//             'description' => 'Directory to search in',
//         ],
//         'recursive' => [
//             'type' => 'boolean',
//             'description' => 'Search subdirectories',
//         ],
//     ],
//     'required' => ['pattern'],
// ]
```

### Parameters

Get parameter definitions from the schema:

```php
$parameters = $tool->getParameters();

// [
//     'pattern' => ['type' => 'string', 'description' => '...'],
//     'directory' => ['type' => 'string', 'description' => '...'],
//     'recursive' => ['type' => 'boolean', 'description' => '...'],
// ]
```

## Executing Tools

### Basic Execution

```php
$result = $tool->execute([
    'pattern' => '*.php',
    'directory' => '/src',
]);

if ($result->isSuccess()) {
    $files = $result->result;
    foreach ($files as $file) {
        echo $file['path'] . "\n";
    }
} else {
    echo "Error: " . $result->error;
}
```

### Understanding ToolResult

The `execute()` method returns a `ToolResult` object:

```php
$result = $tool->execute(['query' => 'test']);

// Check status
$result->isSuccess();  // true if successful
$result->isFailure();  // true if failed

// Access data
$result->toolCallId;   // Unique ID for this execution
$result->name;         // Tool name
$result->arguments;    // Arguments passed
$result->result;       // The actual result (if successful)
$result->error;        // Error message (if failed)
$result->duration;     // Execution time in ms (may be null)
$result->cached;       // Whether result was cached (always false for MCP)

// Get content for LLM
$llmContent = $result->getContentForLlm();
// Returns JSON string of result or "Error: {message}"
```

### Handling Errors

Errors during execution are captured in the result:

```php
$result = $tool->execute(['invalid' => 'arguments']);

if ($result->isFailure()) {
    Log::error('Tool execution failed', [
        'tool' => $result->name,
        'error' => $result->error,
        'arguments' => $result->arguments,
    ]);
}
```

## Argument Validation

Validate arguments before execution:

```php
// Check if arguments are valid
if ($tool->validate(['pattern' => '*.php'])) {
    $result = $tool->execute(['pattern' => '*.php']);
}

// Validation checks required properties
$tool->validate([]);  // false - missing 'pattern'
$tool->validate(['pattern' => '*.php']);  // true
```

## Tool Schema for LLM

Generate an OpenAI-compatible function schema:

```php
$schema = $tool->toSchema();

// Returns:
// [
//     'type' => 'function',
//     'function' => [
//         'name' => 'search_files',
//         'description' => 'Search for files matching a pattern',
//         'parameters' => [
//             'type' => 'object',
//             'properties' => [...],
//             'required' => ['pattern'],
//         ],
//     ],
// ]
```

This schema is used when registering tools with the LLM for function calling.

## Tool Characteristics

### Parallel Execution

MCP tools can run in parallel by default:

```php
$tool->isParallel();  // true

// Multiple MCP tools can be executed concurrently
$results = collect($tools)->map(
    fn($tool) => $tool->execute($arguments[$tool->getName()])
);
```

### Caching

MCP tools are not cacheable by default since their results may change:

```php
$tool->isCacheable();  // false
$tool->getCacheTtl();  // 0
```

If you need caching, wrap the tool execution in your own caching layer:

```php
use Illuminate\Support\Facades\Cache;

$cacheKey = 'mcp:' . $tool->getName() . ':' . md5(json_encode($arguments));

$result = Cache::remember($cacheKey, 300, function () use ($tool, $arguments) {
    return $tool->execute($arguments);
});
```

## Creating Tools Manually

While tools are typically auto-discovered, you can create them manually:

```php
use AgenticOrchestrator\Mcp\McpTool;
use AgenticOrchestrator\Mcp\McpClient;

$client = McpClient::url('http://localhost:3000');

$tool = new McpTool(
    client: $client,
    name: 'custom_tool',
    description: 'A custom tool',
    inputSchema: [
        'type' => 'object',
        'properties' => [
            'input' => [
                'type' => 'string',
                'description' => 'The input value',
            ],
        ],
        'required' => ['input'],
    ],
);
```

## Using Tools with Agents

MCP tools integrate seamlessly with agents through the `HasMcp` trait:

```php
use AgenticOrchestrator\Agent;
use AgenticOrchestrator\Mcp\Concerns\HasMcp;

class ResearchAgent extends Agent
{
    use HasMcp;

    protected function getMcpServers(): array
    {
        return [
            'search' => [
                'url' => 'http://localhost:3001',
            ],
        ];
    }

    public function research(string $topic): string
    {
        // MCP tools are automatically available
        $tools = $this->getMcpTools();

        // Execute through the agent
        $result = $this->executeMcpTool('search:web_search', [
            'query' => $topic,
        ]);

        return $result->getContentForLlm();
    }
}
```

See the [HasMcp Trait](./has-mcp-trait.md) documentation for more details.

## Full Example

```php
use AgenticOrchestrator\Mcp\McpClient;
use Illuminate\Support\Facades\Log;

class CodeAnalyzer
{
    private McpClient $client;

    public function __construct(string $serverUrl)
    {
        $this->client = McpClient::url($serverUrl);
    }

    public function analyzeProject(string $directory): array
    {
        $this->client->connect();

        $results = [
            'files' => [],
            'issues' => [],
            'metrics' => [],
        ];

        // Find PHP files
        $searchTool = $this->client->getTool('search_files');
        if ($searchTool) {
            $searchResult = $searchTool->execute([
                'pattern' => '*.php',
                'directory' => $directory,
                'recursive' => true,
            ]);

            if ($searchResult->isSuccess()) {
                $results['files'] = $searchResult->result;
            }
        }

        // Analyze each file
        $analyzeTool = $this->client->getTool('analyze_code');
        if ($analyzeTool) {
            foreach ($results['files'] as $file) {
                $analyzeResult = $analyzeTool->execute([
                    'path' => $file['path'],
                    'checks' => ['complexity', 'style', 'security'],
                ]);

                if ($analyzeResult->isSuccess()) {
                    $results['issues'] = array_merge(
                        $results['issues'],
                        $analyzeResult->result['issues'] ?? []
                    );
                    $results['metrics'][] = $analyzeResult->result['metrics'] ?? [];
                }
            }
        }

        $this->client->disconnect();

        return $results;
    }
}
```

## Related

- [McpClient](./mcp-client.md): Connecting to MCP servers
- [McpToolProvider](./mcp-provider.md): Managing multiple servers
- [HasMcp Trait](./has-mcp-trait.md): Using MCP in agents
