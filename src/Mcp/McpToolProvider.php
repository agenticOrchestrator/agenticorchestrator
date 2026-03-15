<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Mcp;

use AgenticOrchestrator\Contracts\ToolInterface;
use Illuminate\Support\Facades\Log;

/**
 * MCP Tool Provider - Manages multiple MCP servers and their tools.
 */
class McpToolProvider
{
    /** @var array<string, McpClient> */
    protected array $clients = [];

    /** @var array<string, McpTool> */
    protected array $tools = [];

    /**
     * Create a new MCP tool provider.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Add an MCP server.
     *
     * @param  array<string, mixed>  $config
     */
    public function addServer(string $name, array $config): static
    {
        $client = McpClient::make($config);
        $this->clients[$name] = $client;

        return $this;
    }

    /**
     * Add an MCP client directly.
     */
    public function addClient(string $name, McpClient $client): static
    {
        $this->clients[$name] = $client;

        return $this;
    }

    /**
     * Connect all servers and discover tools.
     */
    public function connect(): static
    {
        foreach ($this->clients as $name => $client) {
            try {
                $client->connect();

                // Register tools with server prefix
                foreach ($client->getTools() as $tool) {
                    $prefixedName = "{$name}:{$tool->getName()}";
                    $this->tools[$prefixedName] = $tool;
                }

                Log::debug('MCP server connected', [
                    'server' => $name,
                    'tools' => count($client->getTools()),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to connect MCP server', [
                    'server' => $name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this;
    }

    /**
     * Get all available tools.
     *
     * @return array<ToolInterface>
     */
    public function getTools(): array
    {
        return array_values($this->tools);
    }

    /**
     * Get a specific tool.
     */
    public function getTool(string $name): ?ToolInterface
    {
        // Try prefixed name first
        if (isset($this->tools[$name])) {
            return $this->tools[$name];
        }

        // Try finding by tool name across all servers
        foreach ($this->tools as $tool) {
            if ($tool->getName() === $name) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * Check if a tool exists.
     */
    public function hasTool(string $name): bool
    {
        return $this->getTool($name) !== null;
    }

    /**
     * Get tools from a specific server.
     *
     * @return array<ToolInterface>
     */
    public function getToolsFromServer(string $serverName): array
    {
        $prefix = "{$serverName}:";
        $tools = [];

        foreach ($this->tools as $name => $tool) {
            if (str_starts_with($name, $prefix)) {
                $tools[] = $tool;
            }
        }

        return $tools;
    }

    /**
     * Get all tool schemas.
     *
     * @return array<array<string, mixed>>
     */
    public function getSchemas(): array
    {
        return array_map(
            fn (ToolInterface $tool) => $tool->toSchema(),
            $this->getTools()
        );
    }

    /**
     * Get a specific client.
     */
    public function getClient(string $name): ?McpClient
    {
        return $this->clients[$name] ?? null;
    }

    /**
     * Get all connected server names.
     *
     * @return array<string>
     */
    public function getServerNames(): array
    {
        return array_keys($this->clients);
    }

    /**
     * Disconnect all servers.
     */
    public function disconnect(): void
    {
        foreach ($this->clients as $client) {
            $client->disconnect();
        }

        $this->tools = [];
    }

    /**
     * Reconnect all servers.
     */
    public function reconnect(): static
    {
        $this->disconnect();

        return $this->connect();
    }
}
