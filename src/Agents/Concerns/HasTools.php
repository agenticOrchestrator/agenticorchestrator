<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Agents\Concerns;

use AgenticOrchestrator\Contracts\ToolInterface;
use AgenticOrchestrator\Tools\Attributes\Tool;
use AgenticOrchestrator\Tools\ToolResult;
use AgenticOrchestrator\Tools\ToolSchemaBuilder;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionMethod;

/**
 * Provides tool discovery and execution for agents.
 */
trait HasTools
{
    /**
     * Cached tool methods discovered from this class.
     *
     * @var array<string, array{method: ReflectionMethod, attribute: Tool}>|null
     */
    protected ?array $discoveredTools = null;

    /**
     * Get all available tools for this agent.
     *
     * @return Collection<int, array{name: string, description: string, method: ReflectionMethod, attribute: Tool}>
     */
    public function getTools(): Collection
    {
        $tools = collect();

        // Discover attribute-based tools on this class
        foreach ($this->discoverToolMethods() as $name => $tool) {
            $tools->push([
                'name' => $name,
                'description' => $tool['attribute']->description,
                'method' => $tool['method'],
                'attribute' => $tool['attribute'],
            ]);
        }

        // Add external tool classes
        foreach ($this->tools ?? [] as $toolClass) {
            if (is_string($toolClass) && class_exists($toolClass)) {
                $toolInstance = app($toolClass);
                if ($toolInstance instanceof ToolInterface) {
                    $tools->push([
                        'name' => $toolInstance->getName(),
                        'description' => $toolInstance->getDescription(),
                        'instance' => $toolInstance,
                    ]);
                }
            }
        }

        return $tools;
    }

    /**
     * Get tool schemas for LLM function calling.
     *
     * @return array<int, array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}>
     */
    public function getToolSchemas(): array
    {
        $schemas = [];
        $schemaBuilder = new ToolSchemaBuilder;

        foreach ($this->discoverToolMethods() as $tool) {
            $schemas[] = $schemaBuilder->buildFromMethod(
                $tool['method'],
                $tool['attribute']
            );
        }

        // Add external tool schemas
        foreach ($this->tools ?? [] as $toolClass) {
            if (is_string($toolClass) && class_exists($toolClass)) {
                $toolInstance = app($toolClass);
                if ($toolInstance instanceof ToolInterface) {
                    $schemas[] = $toolInstance->toSchema();
                }
            }
        }

        return $schemas;
    }

    /**
     * Execute a tool by name.
     *
     * @param  string  $toolCallId  The tool call ID from the LLM
     * @param  string  $name  The tool name
     * @param  array<string, mixed>  $arguments  The arguments to pass
     */
    public function executeTool(string $toolCallId, string $name, array $arguments): ToolResult
    {
        $startTime = microtime(true);

        try {
            // Check attribute-based tools first
            $tools = $this->discoverToolMethods();
            if (isset($tools[$name])) {
                $method = $tools[$name]['method'];
                $result = $this->invokeToolMethod($method, $arguments);

                return ToolResult::success(
                    toolCallId: $toolCallId,
                    name: $name,
                    arguments: $arguments,
                    result: $result,
                    duration: (microtime(true) - $startTime) * 1000,
                );
            }

            // Check external tool classes
            foreach ($this->tools ?? [] as $toolClass) {
                if (is_string($toolClass) && class_exists($toolClass)) {
                    $toolInstance = app($toolClass);
                    if ($toolInstance instanceof ToolInterface && $toolInstance->getName() === $name) {
                        $result = $toolInstance->execute($arguments);

                        return ToolResult::success(
                            toolCallId: $toolCallId,
                            name: $name,
                            arguments: $arguments,
                            result: $result,
                            duration: (microtime(true) - $startTime) * 1000,
                        );
                    }
                }
            }

            return ToolResult::failure(
                toolCallId: $toolCallId,
                name: $name,
                arguments: $arguments,
                error: "Tool '{$name}' not found",
                duration: (microtime(true) - $startTime) * 1000,
            );
        } catch (\Throwable $e) {
            return ToolResult::failure(
                toolCallId: $toolCallId,
                name: $name,
                arguments: $arguments,
                error: $e->getMessage(),
                duration: (microtime(true) - $startTime) * 1000,
            );
        }
    }

    /**
     * Execute multiple tool calls.
     *
     * @param  array<int, array{id: string, function: array{name: string, arguments: string}}>  $toolCalls
     * @return array<int, ToolResult>
     */
    public function executeToolCalls(array $toolCalls): array
    {
        $results = [];

        foreach ($toolCalls as $call) {
            $arguments = json_decode($call['function']['arguments'] ?? '{}', true);

            $results[] = $this->executeTool(
                toolCallId: $call['id'],
                name: $call['function']['name'],
                arguments: $arguments ?? [],
            );
        }

        return $results;
    }

    /**
     * Discover tool methods using reflection.
     *
     * @return array<string, array{method: ReflectionMethod, attribute: Tool}>
     */
    protected function discoverToolMethods(): array
    {
        if ($this->discoveredTools !== null) {
            return $this->discoveredTools;
        }

        $this->discoveredTools = [];
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(Tool::class);

            if (empty($attributes)) {
                continue;
            }

            /** @var Tool $toolAttribute */
            $toolAttribute = $attributes[0]->newInstance();

            // Skip hidden tools
            if ($toolAttribute->hidden) {
                continue;
            }

            $name = $toolAttribute->name ?? $method->getName();
            $this->discoveredTools[$name] = [
                'method' => $method,
                'attribute' => $toolAttribute,
            ];
        }

        return $this->discoveredTools;
    }

    /**
     * Invoke a tool method with arguments.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function invokeToolMethod(ReflectionMethod $method, array $arguments): mixed
    {
        $params = [];

        foreach ($method->getParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $arguments)) {
                $params[] = $arguments[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
            } elseif ($param->allowsNull()) {
                $params[] = null;
            } else {
                throw new \InvalidArgumentException(
                    "Missing required argument: {$name}"
                );
            }
        }

        return $method->invokeArgs($this, $params);
    }

    /**
     * Check if a tool exists.
     */
    public function hasTool(string $name): bool
    {
        $tools = $this->discoverToolMethods();
        if (isset($tools[$name])) {
            return true;
        }

        foreach ($this->tools ?? [] as $toolClass) {
            if (is_string($toolClass) && class_exists($toolClass)) {
                $toolInstance = app($toolClass);
                if ($toolInstance instanceof ToolInterface && $toolInstance->getName() === $name) {
                    return true;
                }
            }
        }

        return false;
    }
}
