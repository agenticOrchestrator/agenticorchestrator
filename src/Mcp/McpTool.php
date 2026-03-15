<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Mcp;

use AgenticOrchestrator\Contracts\ToolInterface;
use AgenticOrchestrator\Tools\ToolResult;

/**
 * MCP Tool - A tool provided by an MCP server.
 */
class McpTool implements ToolInterface
{
    /**
     * Create a new MCP tool.
     *
     * @param  array<string, mixed>  $inputSchema
     */
    public function __construct(
        protected McpClient $client,
        protected string $name,
        protected string $description,
        protected array $inputSchema = [],
    ) {}

    /**
     * Get the tool name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the tool description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get the input schema.
     *
     * @return array<string, mixed>
     */
    public function getInputSchema(): array
    {
        return $this->inputSchema;
    }

    /**
     * Execute the tool.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function execute(array $arguments): mixed
    {
        try {
            $result = $this->client->executeTool($this->name, $arguments);

            return ToolResult::success(
                toolCallId: 'mcp-'.uniqid(),
                name: $this->name,
                arguments: $arguments,
                result: $result,
            );
        } catch (\Exception $e) {
            return ToolResult::failure(
                toolCallId: 'mcp-'.uniqid(),
                name: $this->name,
                arguments: $arguments,
                error: $e->getMessage(),
            );
        }
    }

    /**
     * Get the tool schema.
     *
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->inputSchema ?: [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
        ];
    }

    /**
     * Whether this tool can run in parallel.
     */
    public function isParallel(): bool
    {
        return true;
    }

    /**
     * Validate arguments.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function validate(array $arguments): bool
    {
        // Basic validation - check required properties
        if (isset($this->inputSchema['required'])) {
            foreach ($this->inputSchema['required'] as $required) {
                if (! array_key_exists($required, $arguments)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get parameter definitions from input schema.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getParameters(): array
    {
        return $this->inputSchema['properties'] ?? [];
    }

    /**
     * MCP tools are not cacheable by default.
     */
    public function isCacheable(): bool
    {
        return false;
    }

    /**
     * Get cache TTL (not applicable for MCP tools).
     */
    public function getCacheTtl(): int
    {
        return 0;
    }
}
