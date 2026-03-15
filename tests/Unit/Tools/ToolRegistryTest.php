<?php

declare(strict_types=1);

use AgenticOrchestrator\Contracts\ToolInterface;
use AgenticOrchestrator\Tools\Attributes\Tool;
use AgenticOrchestrator\Tools\Attributes\ToolParameter;
use AgenticOrchestrator\Tools\ToolRegistry;
use AgenticOrchestrator\Tools\ToolResult;
use Illuminate\Container\Container;

// Test tool class
class TestToolClass implements ToolInterface
{
    public function getName(): string
    {
        return 'test_tool';
    }

    public function getDescription(): string
    {
        return 'A test tool';
    }

    public function getParameters(): array
    {
        return [];
    }

    public function execute(array $arguments): ToolResult
    {
        return ToolResult::success(['result' => 'success']);
    }

    public function toSchema(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'test_tool',
                'description' => 'A test tool',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
        ];
    }

    public function isParallel(): bool
    {
        return true;
    }

    public function isCacheable(): bool
    {
        return false;
    }

    public function getCacheTtl(): int
    {
        return 0;
    }

    public function validate(array $arguments): bool
    {
        return true;
    }
}

// Class with tool methods
class ClassWithToolMethods
{
    #[Tool('Search for items')]
    public function search(
        #[ToolParameter('The search query')]
        string $query,
    ): array {
        return ['results' => []];
    }

    #[Tool('Get item details', name: 'get_details')]
    public function getItemDetails(
        #[ToolParameter('The item ID')]
        string $id,
    ): array {
        return ['id' => $id];
    }

    public function notATool(): void
    {
        // This method has no Tool attribute
    }
}

beforeEach(function () {
    $this->container = new Container;
    $this->registry = new ToolRegistry($this->container);
});

test('registers a tool class', function () {
    $this->registry->register(TestToolClass::class, 'test_tool');

    expect($this->registry->has('test_tool'))->toBeTrue();
    expect($this->registry->all())->toHaveKey('test_tool');
});

test('discovers tools from class methods', function () {
    $discovered = $this->registry->discoverFromClass(ClassWithToolMethods::class);

    expect($discovered)->toHaveCount(2);
    expect($discovered)->toHaveKey('search');
    expect($discovered)->toHaveKey('get_details');
});

test('ignores methods without Tool attribute', function () {
    $discovered = $this->registry->discoverFromClass(ClassWithToolMethods::class);

    expect($discovered)->not->toHaveKey('notATool');
});

test('builds schema for discovered tools', function () {
    $discovered = $this->registry->discoverFromClass(ClassWithToolMethods::class);

    expect($discovered['search'])->toHaveKey('schema');
    expect($discovered['search']['schema']['function']['name'])->toBe('search');
    expect($discovered['search']['schema']['function']['description'])->toBe('Search for items');
});

test('gets tool schema by name', function () {
    $this->registry->register(TestToolClass::class, 'test_tool');

    $schema = $this->registry->getSchema('test_tool');

    expect($schema)->toBeArray();
    expect($schema['type'])->toBe('function');
    expect($schema['function']['name'])->toBe('test_tool');
});

test('gets schemas for multiple tools', function () {
    $this->registry->register(TestToolClass::class, 'test_tool');
    $this->registry->discoverFromClass(ClassWithToolMethods::class);

    $schemas = $this->registry->getSchemas(['test_tool', 'search']);

    expect($schemas)->toHaveCount(2);
});

test('forgets a tool', function () {
    $this->registry->register(TestToolClass::class, 'test_tool');
    expect($this->registry->has('test_tool'))->toBeTrue();

    $this->registry->forget('test_tool');
    expect($this->registry->has('test_tool'))->toBeFalse();
});

test('flushes all tools', function () {
    $this->registry->register(TestToolClass::class, 'test_tool');
    $this->registry->discoverFromClass(ClassWithToolMethods::class);

    $this->registry->flush();

    expect($this->registry->all())->toBeEmpty();
    expect($this->registry->discovered())->toBeEmpty();
});

test('gets tool metadata', function () {
    $this->registry->register(TestToolClass::class, 'test_tool');
    $this->registry->discoverFromClass(ClassWithToolMethods::class);

    $metadata = $this->registry->getToolMetadata();

    expect($metadata)->toHaveKey('test_tool');
    expect($metadata['test_tool']['type'])->toBe('class');

    expect($metadata)->toHaveKey('search');
    expect($metadata['search']['type'])->toBe('method');
});

test('validates tool class implements interface', function () {
    $this->registry->register(stdClass::class, 'invalid');
})->throws(InvalidArgumentException::class);

test('validates tool class exists', function () {
    $this->registry->register('NonExistentClass', 'invalid');
})->throws(InvalidArgumentException::class);

test('registers multiple tool classes', function () {
    $this->registry->registerMany([
        'test_tool' => TestToolClass::class,
    ]);

    expect($this->registry->has('test_tool'))->toBeTrue();
});

test('returns null schema for non-existent tool', function () {
    $schema = $this->registry->getSchema('non_existent');

    expect($schema)->toBeNull();
});

test('caches schemas', function () {
    $this->registry->register(TestToolClass::class, 'test_tool');

    // First call should build and cache
    $schema1 = $this->registry->getSchema('test_tool');

    // Second call should return cached
    $schema2 = $this->registry->getSchema('test_tool');

    expect($schema1)->toBe($schema2);
});
