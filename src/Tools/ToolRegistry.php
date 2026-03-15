<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tools;

use AgenticOrchestrator\Contracts\ToolInterface;
use AgenticOrchestrator\Tools\Attributes\Tool;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;

/**
 * Tool Registry - Discovery and registration of tools.
 *
 * Manages tool classes and discovers tools from agent methods via attributes.
 */
class ToolRegistry
{
    /**
     * Registered tool classes.
     *
     * @var array<string, class-string<ToolInterface>>
     */
    protected array $tools = [];

    /**
     * Discovered tool methods from classes.
     *
     * @var array<string, array{class: string, method: string, attribute: Tool}>
     */
    protected array $discoveredMethods = [];

    /**
     * Tool schemas cache.
     *
     * @var array<string, array>
     */
    protected array $schemaCache = [];

    /**
     * Tool schema builder.
     */
    protected ToolSchemaBuilder $schemaBuilder;

    /**
     * Create a new tool registry instance.
     */
    public function __construct(
        protected Container $container,
    ) {
        $this->schemaBuilder = new ToolSchemaBuilder;
    }

    /**
     * Register a tool class.
     *
     * @param  class-string<ToolInterface>  $toolClass
     */
    public function register(string $toolClass, ?string $name = null): static
    {
        $this->validateToolClass($toolClass);

        $name = $name ?? $this->resolveToolName($toolClass);
        $this->tools[$name] = $toolClass;

        return $this;
    }

    /**
     * Register multiple tool classes.
     *
     * @param  array<class-string<ToolInterface>>  $toolClasses
     */
    public function registerMany(array $toolClasses): static
    {
        foreach ($toolClasses as $name => $toolClass) {
            if (is_int($name)) {
                $this->register($toolClass);
            } else {
                $this->register($toolClass, $name);
            }
        }

        return $this;
    }

    /**
     * Discover tool methods from a class using #[Tool] attribute.
     *
     * @return array<string, array{class: string, method: string, attribute: Tool, schema: array}>
     */
    public function discoverFromClass(string $class): array
    {
        $discovered = [];

        $reflection = new ReflectionClass($class);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $toolAttributes = $method->getAttributes(Tool::class);

            if (empty($toolAttributes)) {
                continue;
            }

            $toolAttr = $toolAttributes[0]->newInstance();
            $name = $toolAttr->name ?? $method->getName();

            $discovered[$name] = [
                'class' => $class,
                'method' => $method->getName(),
                'attribute' => $toolAttr,
                'schema' => $this->schemaBuilder->buildFromMethod($method, $toolAttr),
            ];

            // Store in registry for later lookup
            $this->discoveredMethods[$name] = [
                'class' => $class,
                'method' => $method->getName(),
                'attribute' => $toolAttr,
            ];
        }

        return $discovered;
    }

    /**
     * Discover tools from multiple classes.
     *
     * @param  array<string>  $classes
     * @return array<string, array>
     */
    public function discoverFromClasses(array $classes): array
    {
        $allDiscovered = [];

        foreach ($classes as $class) {
            $discovered = $this->discoverFromClass($class);
            $allDiscovered = array_merge($allDiscovered, $discovered);
        }

        return $allDiscovered;
    }

    /**
     * Resolve a tool instance.
     *
     * @throws InvalidArgumentException
     */
    public function make(string $name): ToolInterface
    {
        if (! $this->has($name)) {
            throw new InvalidArgumentException("Tool [{$name}] not found.");
        }

        $toolClass = $this->tools[$name];

        return $this->container->make($toolClass);
    }

    /**
     * Check if a tool is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]) || isset($this->discoveredMethods[$name]);
    }

    /**
     * Get all registered tools.
     *
     * @return Collection<string, class-string<ToolInterface>>
     */
    public function all(): Collection
    {
        return collect($this->tools);
    }

    /**
     * Get all discovered method tools.
     *
     * @return Collection<string, array>
     */
    public function discovered(): Collection
    {
        return collect($this->discoveredMethods);
    }

    /**
     * Get tool schema by name.
     */
    public function getSchema(string $name): ?array
    {
        if (isset($this->schemaCache[$name])) {
            return $this->schemaCache[$name];
        }

        // Check discovered methods
        if (isset($this->discoveredMethods[$name])) {
            $info = $this->discoveredMethods[$name];
            $method = new ReflectionMethod($info['class'], $info['method']);
            $schema = $this->schemaBuilder->buildFromMethod($method, $info['attribute']);
            $this->schemaCache[$name] = $schema;

            return $schema;
        }

        // Check registered tool classes
        if (isset($this->tools[$name])) {
            $tool = $this->make($name);
            $schema = $tool->toSchema();
            $this->schemaCache[$name] = $schema;

            return $schema;
        }

        return null;
    }

    /**
     * Get schemas for multiple tools.
     *
     * @param  array<string>  $names
     * @return array<array>
     */
    public function getSchemas(array $names): array
    {
        $schemas = [];

        foreach ($names as $name) {
            $schema = $this->getSchema($name);
            if ($schema !== null) {
                $schemas[] = $schema;
            }
        }

        return $schemas;
    }

    /**
     * Get all tool schemas.
     *
     * @return array<string, array>
     */
    public function getAllSchemas(): array
    {
        $schemas = [];

        // Get schemas from registered tool classes
        foreach ($this->tools as $name => $class) {
            $schemas[$name] = $this->getSchema($name);
        }

        // Get schemas from discovered methods
        foreach ($this->discoveredMethods as $name => $info) {
            if (! isset($schemas[$name])) {
                $schemas[$name] = $this->getSchema($name);
            }
        }

        return $schemas;
    }

    /**
     * Unregister a tool.
     */
    public function forget(string $name): static
    {
        unset($this->tools[$name]);
        unset($this->discoveredMethods[$name]);
        unset($this->schemaCache[$name]);

        return $this;
    }

    /**
     * Clear all registrations.
     */
    public function flush(): static
    {
        $this->tools = [];
        $this->discoveredMethods = [];
        $this->schemaCache = [];

        return $this;
    }

    /**
     * Get tool metadata for listing purposes.
     *
     * @return array<string, array{name: string, type: string, description: string|null, parallel: bool}>
     */
    public function getToolMetadata(): array
    {
        $metadata = [];

        // Metadata from registered tool classes
        foreach ($this->tools as $name => $class) {
            $metadata[$name] = [
                'name' => $name,
                'type' => 'class',
                'class' => $class,
                'description' => $this->getToolDescription($class),
                'parallel' => true, // Default for class-based tools
            ];
        }

        // Metadata from discovered methods
        foreach ($this->discoveredMethods as $name => $info) {
            $metadata[$name] = [
                'name' => $name,
                'type' => 'method',
                'class' => $info['class'],
                'method' => $info['method'],
                'description' => $info['attribute']->description,
                'parallel' => $info['attribute']->parallel,
            ];
        }

        return $metadata;
    }

    /**
     * Validate that a class implements ToolInterface.
     *
     * @param  class-string  $toolClass
     *
     * @throws InvalidArgumentException
     */
    protected function validateToolClass(string $toolClass): void
    {
        if (! class_exists($toolClass)) {
            throw new InvalidArgumentException(
                "Tool class [{$toolClass}] does not exist."
            );
        }

        if (! is_subclass_of($toolClass, ToolInterface::class)) {
            throw new InvalidArgumentException(
                "Tool class [{$toolClass}] must implement ToolInterface."
            );
        }
    }

    /**
     * Resolve tool name from class.
     *
     * @param  class-string<ToolInterface>  $toolClass
     */
    protected function resolveToolName(string $toolClass): string
    {
        // Check if the class has a getName method
        if (method_exists($toolClass, 'getName')) {
            $tool = $this->container->make($toolClass);

            return $tool->getName();
        }

        // Extract name from class name (e.g., LookupOrderTool -> lookup-order)
        $baseName = class_basename($toolClass);

        // Remove 'Tool' suffix if present
        $name = preg_replace('/Tool$/', '', $baseName);

        // Convert to snake_case (tool naming convention)
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }

    /**
     * Get tool description from class.
     *
     * @param  class-string<ToolInterface>  $toolClass
     */
    protected function getToolDescription(string $toolClass): ?string
    {
        // Try to get description from class docblock
        try {
            $reflection = new ReflectionClass($toolClass);
            $docComment = $reflection->getDocComment();

            if ($docComment) {
                // Extract @description annotation
                preg_match('/@description\s+(.+)$/m', $docComment, $matches);
                if (isset($matches[1])) {
                    return trim($matches[1]);
                }

                // Fall back to first non-tag line
                $lines = explode("\n", $docComment);
                foreach ($lines as $line) {
                    $line = trim($line, " \t\n\r\0\x0B/*");
                    if ($line && ! str_starts_with($line, '@')) {
                        return $line;
                    }
                }
            }
        } catch (\ReflectionException) {
            // Ignore reflection errors
        }

        return null;
    }
}
