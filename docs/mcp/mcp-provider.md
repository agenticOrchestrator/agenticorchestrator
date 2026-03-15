# McpToolProvider

The `McpToolProvider` class manages multiple MCP server connections and aggregates tools from all connected servers.

## Overview

`McpToolProvider` allows agents to:

- Connect to multiple MCP servers simultaneously
- Access tools from all servers through a unified interface
- Prefix tools with server names to avoid naming conflicts
- Manage connections lifecycle (connect, disconnect, reconnect)

## Basic Usage

### Creating a Provider

```php
use AgenticOrchestrator\Mcp\McpToolProvider;

$provider = McpToolProvider::make()
    ->addServer('filesystem', [
        'url' => 'http://localhost:3001',
    ])
    ->addServer('database', [
        'url' => 'http://localhost:3002',
        'api_key' => 'secret-key',
    ])
    ->connect();
```

### Getting Tools

```php
// Get all tools from all servers
$allTools = $provider->getTools();

// Get a specific tool (prefixed with server name)
$tool = $provider->getTool('filesystem:read_file');

// Check if a tool exists
if ($provider->hasTool('database:query')) {
    // Tool is available
}
```

### Getting Tool Schemas

```php
// Get schemas for all tools (for LLM function calling)
$schemas = $provider->getSchemas();
```

## Configuration

### Environment Variables

```env
# MCP Server URLs
MCP_SERVER_URL=http://localhost:3000
MCP_SERVER_API_KEY=your-api-key

# Multiple servers
MCP_DATABASE_SERVER=http://localhost:3001
MCP_FILESYSTEM_SERVER=http://localhost:3002
```

### Config File

```php
// config/agent-orchestrator.php
'mcp' => [
    'enabled' => true,

    'servers' => [
        'default' => [
            'url' => env('MCP_SERVER_URL'),
            'api_key' => env('MCP_SERVER_API_KEY'),
            'timeout' => 30,
        ],

        'database' => [
            'url' => env('MCP_DATABASE_SERVER'),
            'api_key' => env('MCP_DATABASE_API_KEY'),
            'timeout' => 60,
        ],

        'filesystem' => [
            'url' => env('MCP_FILESYSTEM_SERVER'),
        ],
    ],
],
```

## Using with Agents

### HasMcp Trait

Add MCP support to your agents using the `HasMcp` trait:

```php
use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Mcp\Concerns\HasMcp;

class DatabaseAgent extends Agent
{
    use HasMcp;

    protected function getMcpServers(): array
    {
        return [
            'database' => [
                'url' => config('mcp.servers.database.url'),
                'api_key' => config('mcp.servers.database.api_key'),
            ],
        ];
    }

    public function instructions(): string
    {
        return 'You can query the database using available MCP tools.';
    }
}
```

### Trait Methods

The `HasMcp` trait provides these methods:

| Method | Description |
|--------|-------------|
| `getMcpServers()` | Override to define MCP servers |
| `withMcpServer(string $name, array $config)` | Add a server dynamically |
| `withMcpClient(string $name, McpClient $client)` | Add an existing client |
| `connectMcpServers()` | Connect to all configured servers |
| `getMcpTools()` | Get all MCP tools |
| `getAllToolSchemas()` | Get schemas for all tools |
| `executeMcpTool(string $name, array $args)` | Execute an MCP tool |
| `hasMcpTool(string $name)` | Check if tool exists |
| `disconnectMcpServers()` | Disconnect all servers |

## Managing Connections

### Adding Clients

```php
use AgenticOrchestrator\Mcp\McpClient;

// Add a server by configuration
$provider->addServer('search', [
    'url' => 'http://localhost:3003',
]);

// Add an existing client
$client = McpClient::url('http://localhost:3004')->connect();
$provider->addClient('custom', $client);
```

### Reconnecting

```php
// Disconnect and reconnect all servers
$provider->reconnect();

// Disconnect all servers
$provider->disconnect();
```

### Getting Server Info

```php
// Get all server names
$names = $provider->getServerNames();

// Get a specific client
$client = $provider->getClient('database');
```

## Tool Schema

MCP tools follow this schema:

```php
[
    'name' => 'query_database',
    'description' => 'Execute a SQL query',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'query' => [
                'type' => 'string',
                'description' => 'The SQL query to execute',
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum rows to return',
                'default' => 100,
            ],
        ],
        'required' => ['query'],
    ],
]
```

## Error Handling

```php
use AgenticOrchestrator\Exceptions\ToolExecutionException;

try {
    $provider->connect();
    $tool = $provider->getTool('database:query');
    $result = $tool->execute(['query' => 'SELECT * FROM users']);
} catch (ToolExecutionException $e) {
    Log::error('MCP tool failed', [
        'tool' => $e->getMessage(),
    ]);
} catch (\RuntimeException $e) {
    Log::error('MCP connection failed', [
        'error' => $e->getMessage(),
    ]);
}
```

## Complete Example

```php
use AgenticOrchestrator\Mcp\McpToolProvider;
use Illuminate\Support\Facades\Log;

class DataService
{
    private McpToolProvider $provider;

    public function __construct()
    {
        $this->provider = McpToolProvider::make()
            ->addServer('database', [
                'url' => config('mcp.servers.database.url'),
                'api_key' => config('mcp.servers.database.api_key'),
                'timeout' => 60,
            ])
            ->addServer('filesystem', [
                'url' => config('mcp.servers.filesystem.url'),
            ]);
    }

    public function connect(): void
    {
        $this->provider->connect();

        Log::info('MCP provider connected', [
            'servers' => $this->provider->getServerNames(),
            'tools' => count($this->provider->getTools()),
        ]);
    }

    public function queryDatabase(string $query): array
    {
        $tool = $this->provider->getTool('database:query');

        if (!$tool) {
            throw new \RuntimeException('Database query tool not available');
        }

        $result = $tool->execute(['query' => $query]);

        if ($result->isFailure()) {
            throw new \RuntimeException($result->error);
        }

        return $result->result;
    }

    public function readFile(string $path): ?string
    {
        $tool = $this->provider->getTool('filesystem:read_file');

        if (!$tool) {
            return null;
        }

        $result = $tool->execute(['path' => $path]);

        return $result->isSuccess() ? $result->result['content'] : null;
    }

    public function disconnect(): void
    {
        $this->provider->disconnect();
    }
}
```

## Related

- [McpClient](./mcp-client.md): Connecting to individual MCP servers
- [McpTool](./mcp-tools.md): Working with MCP tools
- [HasMcp Trait](./has-mcp-trait.md): Using MCP in agents
