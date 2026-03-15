<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Mcp;

use AgenticOrchestrator\Exceptions\ToolExecutionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MCP Client - Client for Model Context Protocol servers.
 *
 * Supports both stdio and SSE transports.
 */
class McpClient
{
    protected string $serverUrl;

    protected string $transport;

    protected ?string $apiKey = null;

    /** @var array<string, mixed> */
    protected array $headers = [];

    protected int $timeout = 30;

    /** @var array<string, mixed>|null */
    protected ?array $capabilities = null;

    /** @var array<McpTool> */
    protected array $tools = [];

    protected bool $isConnected = false;

    /**
     * Create a new MCP client.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->serverUrl = $config['url'] ?? 'http://localhost:3000';
        $this->transport = $config['transport'] ?? 'sse';
        $this->apiKey = $config['api_key'] ?? null;
        $this->timeout = $config['timeout'] ?? 30;
        $this->headers = $config['headers'] ?? [];
    }

    /**
     * Create a new MCP client.
     *
     * @param  array<string, mixed>  $config
     */
    public static function make(array $config = []): static
    {
        return new static($config);
    }

    /**
     * Connect to server via URL.
     */
    public static function url(string $url): static
    {
        return new static(['url' => $url]);
    }

    /**
     * Set the transport type.
     */
    public function transport(string $transport): static
    {
        $this->transport = $transport;

        return $this;
    }

    /**
     * Set the API key.
     */
    public function withApiKey(string $apiKey): static
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * Set custom headers.
     *
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * Set the timeout.
     */
    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Connect to the MCP server and initialize.
     */
    public function connect(): static
    {
        try {
            // Initialize connection
            $response = $this->request()->post('/initialize', [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [
                    'tools' => true,
                    'resources' => true,
                    'prompts' => true,
                ],
                'clientInfo' => [
                    'name' => 'agent-orchestrator',
                    'version' => '1.0.0',
                ],
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Failed to initialize MCP connection: '.$response->body());
            }

            $data = $response->json();
            $this->capabilities = $data['capabilities'] ?? [];
            $this->isConnected = true;

            // Discover tools
            $this->discoverTools();

            Log::debug('MCP client connected', [
                'server' => $this->serverUrl,
                'capabilities' => $this->capabilities,
            ]);

            return $this;
        } catch (\Exception $e) {
            Log::error('MCP connection failed', [
                'server' => $this->serverUrl,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Discover available tools from the server.
     */
    protected function discoverTools(): void
    {
        $response = $this->request()->post('/tools/list');

        if (! $response->successful()) {
            Log::warning('Failed to list MCP tools', [
                'server' => $this->serverUrl,
            ]);

            return;
        }

        $data = $response->json();
        $tools = $data['tools'] ?? [];

        foreach ($tools as $toolData) {
            $this->tools[$toolData['name']] = new McpTool(
                client: $this,
                name: $toolData['name'],
                description: $toolData['description'] ?? '',
                inputSchema: $toolData['inputSchema'] ?? [],
            );
        }
    }

    /**
     * Check if connected.
     */
    public function isConnected(): bool
    {
        return $this->isConnected;
    }

    /**
     * Get server capabilities.
     *
     * @return array<string, mixed>|null
     */
    public function getCapabilities(): ?array
    {
        return $this->capabilities;
    }

    /**
     * Get all available tools.
     *
     * @return array<McpTool>
     */
    public function getTools(): array
    {
        return array_values($this->tools);
    }

    /**
     * Get a specific tool by name.
     */
    public function getTool(string $name): ?McpTool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Check if server has a specific tool.
     */
    public function hasTool(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Execute a tool.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function executeTool(string $name, array $arguments = []): array
    {
        if (! $this->isConnected) {
            $this->connect();
        }

        try {
            $response = $this->request()->post('/tools/call', [
                'name' => $name,
                'arguments' => $arguments,
            ]);

            if (! $response->successful()) {
                throw new ToolExecutionException(
                    "MCP tool '{$name}' execution failed: ".$response->body()
                );
            }

            $data = $response->json();

            if (isset($data['error'])) {
                throw new ToolExecutionException(
                    "MCP tool '{$name}' returned error: ".($data['error']['message'] ?? 'Unknown error')
                );
            }

            return $data['content'] ?? $data;
        } catch (ToolExecutionException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ToolExecutionException(
                "MCP tool '{$name}' execution failed: ".$e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Get resources from the server.
     *
     * @return array<array<string, mixed>>
     */
    public function getResources(): array
    {
        if (! $this->isConnected) {
            $this->connect();
        }

        $response = $this->request()->post('/resources/list');

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json();

        return $data['resources'] ?? [];
    }

    /**
     * Read a resource.
     *
     * @return array<string, mixed>
     */
    public function readResource(string $uri): array
    {
        if (! $this->isConnected) {
            $this->connect();
        }

        $response = $this->request()->post('/resources/read', [
            'uri' => $uri,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to read resource: {$uri}");
        }

        $data = $response->json();

        return $data['contents'] ?? [];
    }

    /**
     * Get prompts from the server.
     *
     * @return array<array<string, mixed>>
     */
    public function getPrompts(): array
    {
        if (! $this->isConnected) {
            $this->connect();
        }

        $response = $this->request()->post('/prompts/list');

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json();

        return $data['prompts'] ?? [];
    }

    /**
     * Get a prompt by name.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function getPrompt(string $name, array $arguments = []): array
    {
        if (! $this->isConnected) {
            $this->connect();
        }

        $response = $this->request()->post('/prompts/get', [
            'name' => $name,
            'arguments' => $arguments,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to get prompt: {$name}");
        }

        return $response->json();
    }

    /**
     * Disconnect from the server.
     */
    public function disconnect(): void
    {
        $this->isConnected = false;
        $this->capabilities = null;
        $this->tools = [];
    }

    /**
     * Create the HTTP request.
     */
    protected function request(): PendingRequest
    {
        $request = Http::baseUrl($this->serverUrl)
            ->timeout($this->timeout)
            ->acceptJson();

        if ($this->apiKey) {
            $request->withToken($this->apiKey);
        }

        if (! empty($this->headers)) {
            $request->withHeaders($this->headers);
        }

        return $request;
    }
}
