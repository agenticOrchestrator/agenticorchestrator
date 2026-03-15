# Model Context Protocol (MCP)

The Model Context Protocol (MCP) is an open standard for connecting AI agents to external data sources and tools. Agent Orchestrator provides first-class support for MCP, allowing your agents to leverage tools from any MCP-compliant server.

## What is MCP?

MCP is a protocol developed by Anthropic that standardizes how AI applications connect to external systems. It enables:

- **Tool Discovery**: Automatically discover available tools from MCP servers
- **Resource Access**: Read files, databases, and other resources through a unified interface
- **Prompt Templates**: Access pre-built prompts from servers
- **Standardized Communication**: Use HTTP-based or stdio transport for server communication

## Why Use MCP?

### Ecosystem of Tools

MCP has a growing ecosystem of servers providing tools for:

- File system operations
- Database queries
- Web browsing and scraping
- Code execution
- API integrations
- And much more

### Separation of Concerns

MCP servers encapsulate tool logic independently of your agent code, enabling:

- **Reusability**: Share tools across multiple agents
- **Maintainability**: Update tools without modifying agent code
- **Security**: Run tools in isolated environments
- **Specialization**: Use purpose-built servers for specific domains

### Language Agnostic

MCP servers can be written in any language (Python, TypeScript, Rust, etc.), giving you access to tools from the entire developer ecosystem.

## MCP in Agent Orchestrator

Agent Orchestrator provides several components for working with MCP:

| Component | Purpose |
|-----------|---------|
| [McpClient](./mcp-client.md) | Connect to individual MCP servers |
| [McpTool](./mcp-tools.md) | Represents tools provided by MCP servers |
| [McpToolProvider](./mcp-provider.md) | Manage multiple MCP servers |
| [HasMcp Trait](./has-mcp-trait.md) | Add MCP support to your agents |

## Quick Example

Connect to an MCP server and use its tools in your agent:

```php
use AgenticOrchestrator\Mcp\McpClient;
use AgenticOrchestrator\Mcp\McpToolProvider;

// Connect to a single server
$client = McpClient::url('http://localhost:3000')
    ->withApiKey('your-api-key')
    ->connect();

// Get available tools
$tools = $client->getTools();

// Execute a tool
$result = $client->executeTool('search_files', [
    'query' => '*.php',
    'directory' => '/src',
]);
```

Or manage multiple servers with the provider:

```php
$provider = McpToolProvider::make()
    ->addServer('filesystem', [
        'url' => 'http://localhost:3001',
    ])
    ->addServer('database', [
        'url' => 'http://localhost:3002',
    ])
    ->connect();

// Get all tools from all servers
$allTools = $provider->getTools();

// Tools are prefixed with server name: "filesystem:read_file"
$tool = $provider->getTool('filesystem:read_file');
```

## Architecture Overview

```
+------------------+     +------------------+
|    Your Agent    |     |   MCP Server 1   |
|                  |     |  (File System)   |
|  +------------+  |     +------------------+
|  | McpClient  |--|---->|                  |
|  +------------+  |     +------------------+
|                  |
|  +------------+  |     +------------------+
|  | McpClient  |--|---->|   MCP Server 2   |
|  +------------+  |     |   (Database)     |
|                  |     +------------------+
|  +------------+  |
|  | McpTool-   |  |
|  | Provider   |  |
|  +------------+  |
+------------------+
```

## Protocol Version

Agent Orchestrator implements MCP protocol version `2024-11-05`, which includes support for:

- Tools (discovery and execution)
- Resources (listing and reading)
- Prompts (listing and retrieval)

## Next Steps

- [McpClient](./mcp-client.md): Learn how to connect to MCP servers
- [McpTool](./mcp-tools.md): Understand MCP tool execution
- [McpToolProvider](./mcp-provider.md): Manage multiple servers
- [HasMcp Trait](./has-mcp-trait.md): Integrate MCP into your agents
- [Configuration](./configuration.md): Configure MCP servers in your application
