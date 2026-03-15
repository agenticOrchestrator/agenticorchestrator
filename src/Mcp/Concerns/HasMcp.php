<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Mcp\Concerns;

use AgenticOrchestrator\Contracts\ToolInterface;
use AgenticOrchestrator\Mcp\McpClient;
use AgenticOrchestrator\Mcp\McpToolProvider;
use AgenticOrchestrator\Tools\ToolResult;

/**
 * Has MCP - Trait for agents that use MCP tools.
 */
trait HasMcp
{
    protected ?McpToolProvider $mcpProvider = null;

    /** @var array<McpClient> */
    protected array $mcpClients = [];

    /**
     * Get MCP server configuration.
     *
     * Override in agent to configure MCP servers.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getMcpServers(): array
    {
        return [];
    }

    /**
     * Add an MCP server to the agent.
     *
     * @param  array<string, mixed>  $config
     */
    public function withMcpServer(string $name, array $config): static
    {
        $client = McpClient::make($config);
        $this->mcpClients[$name] = $client;

        return $this;
    }

    /**
     * Add an MCP client to the agent.
     */
    public function withMcpClient(string $name, McpClient $client): static
    {
        $this->mcpClients[$name] = $client;

        return $this;
    }

    /**
     * Connect to all MCP servers.
     */
    protected function connectMcpServers(): void
    {
        $this->mcpProvider = McpToolProvider::make();

        // Add configured servers
        foreach ($this->getMcpServers() as $name => $config) {
            $this->mcpProvider->addServer($name, $config);
        }

        // Add manually added clients
        foreach ($this->mcpClients as $name => $client) {
            $this->mcpProvider->addClient($name, $client);
        }

        // Connect and discover tools
        $this->mcpProvider->connect();
    }

    /**
     * Get MCP tools.
     *
     * @return array<ToolInterface>
     */
    protected function getMcpTools(): array
    {
        if ($this->mcpProvider === null) {
            $this->connectMcpServers();
        }

        return $this->mcpProvider?->getTools() ?? [];
    }

    /**
     * Get all tool schemas including MCP tools.
     *
     * @return array<array<string, mixed>>
     */
    protected function getAllToolSchemas(): array
    {
        $schemas = [];

        // Get regular tools
        if (method_exists($this, 'getToolSchemas')) {
            $schemas = array_merge($schemas, $this->getToolSchemas());
        }

        // Get MCP tools
        foreach ($this->getMcpTools() as $tool) {
            $schemas[] = $tool->toSchema();
        }

        return $schemas;
    }

    /**
     * Execute an MCP tool.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function executeMcpTool(string $name, array $arguments): ToolResult
    {
        if ($this->mcpProvider === null) {
            $this->connectMcpServers();
        }

        $tool = $this->mcpProvider?->getTool($name);

        if ($tool === null) {
            return ToolResult::failure(
                toolCallId: 'mcp-'.uniqid(),
                name: $name,
                arguments: $arguments,
                error: "MCP tool '{$name}' not found",
            );
        }

        return $tool->execute($arguments);
    }

    /**
     * Check if an MCP tool exists.
     */
    protected function hasMcpTool(string $name): bool
    {
        if ($this->mcpProvider === null) {
            $this->connectMcpServers();
        }

        return $this->mcpProvider?->hasTool($name) ?? false;
    }

    /**
     * Disconnect MCP servers.
     */
    protected function disconnectMcpServers(): void
    {
        $this->mcpProvider?->disconnect();
        $this->mcpProvider = null;
    }
}
