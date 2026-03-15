<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Testing;

use AgenticOrchestrator\Contracts\ToolInterface;
use AgenticOrchestrator\Tools\ToolResult;
use Closure;
use PHPUnit\Framework\AssertionFailedError;

/**
 * Fake Tool - Test double for tool testing.
 *
 * @example
 * ```php
 * $fake = FakeTool::make('my_tool')
 *     ->returns(['result' => 'success']);
 *
 * $result = $fake->execute(['input' => 'test']);
 * $fake->assertCalled();
 * ```
 */
class FakeTool implements ToolInterface
{
    protected string $name;

    protected string $description = 'Fake tool for testing';

    /** @var array<ToolResult|Closure|array<string, mixed>> */
    protected array $results = [];

    protected int $resultIndex = 0;

    /** @var array<array{arguments: array<string, mixed>}> */
    protected array $calls = [];

    protected bool $shouldFail = false;

    protected string $failureMessage = 'Tool execution failed';

    /**
     * Create a new fake tool.
     */
    public function __construct(string $name = 'fake_tool')
    {
        $this->name = $name;
    }

    /**
     * Create a new fake tool.
     */
    public static function make(string $name = 'fake_tool'): static
    {
        return new static($name);
    }

    /**
     * Set the tool name.
     */
    public function named(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set the tool description.
     */
    public function describedAs(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set return value(s).
     *
     * @param  ToolResult|Closure|array<string, mixed>|array<ToolResult|Closure|array<string, mixed>>  $results
     */
    public function returns(ToolResult|Closure|array $results): static
    {
        // Check if it's a single result or sequence
        if ($results instanceof ToolResult || $results instanceof Closure) {
            $this->results[] = $results;
        } elseif (isset($results[0]) && ($results[0] instanceof ToolResult || $results[0] instanceof Closure || is_array($results[0]))) {
            // It's a sequence of results
            foreach ($results as $result) {
                $this->results[] = $result;
            }
        } else {
            // It's a single array result
            $this->results[] = $results;
        }

        return $this;
    }

    /**
     * Configure tool to fail.
     */
    public function shouldFail(string $message = 'Tool execution failed'): static
    {
        $this->shouldFail = true;
        $this->failureMessage = $message;

        return $this;
    }

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
     * Execute the tool.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function execute(array $arguments): mixed
    {
        $this->calls[] = ['arguments' => $arguments];

        if ($this->shouldFail) {
            return ToolResult::failure(
                toolCallId: 'fake-call-'.count($this->calls),
                name: $this->name,
                arguments: $arguments,
                error: $this->failureMessage,
            );
        }

        if (empty($this->results)) {
            return ToolResult::success(
                toolCallId: 'fake-call-'.count($this->calls),
                name: $this->name,
                arguments: $arguments,
                result: ['fake' => 'result'],
            );
        }

        $result = $this->results[$this->resultIndex] ?? $this->results[count($this->results) - 1];
        $this->resultIndex++;

        if ($result instanceof Closure) {
            $value = $result($arguments);

            if ($value instanceof ToolResult) {
                return $value;
            }

            return ToolResult::success(
                toolCallId: 'fake-call-'.count($this->calls),
                name: $this->name,
                arguments: $arguments,
                result: $value,
            );
        }

        if ($result instanceof ToolResult) {
            return $result;
        }

        return ToolResult::success(
            toolCallId: 'fake-call-'.count($this->calls),
            name: $this->name,
            arguments: $arguments,
            result: $result,
        );
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
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
        ];
    }

    /**
     * Whether tool can run in parallel.
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
        return true;
    }

    /**
     * Get parameter definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getParameters(): array
    {
        return [];
    }

    /**
     * Whether results can be cached.
     */
    public function isCacheable(): bool
    {
        return false;
    }

    /**
     * Get cache TTL.
     */
    public function getCacheTtl(): int
    {
        return 0;
    }

    /**
     * Assert tool was called.
     */
    public function assertCalled(): void
    {
        if (empty($this->calls)) {
            throw new AssertionFailedError(
                sprintf('Expected tool "%s" to be called, but it was not.', $this->name)
            );
        }
    }

    /**
     * Assert tool was not called.
     */
    public function assertNotCalled(): void
    {
        if (! empty($this->calls)) {
            throw new AssertionFailedError(
                sprintf('Expected tool "%s" not to be called, but it was called %d time(s).', $this->name, count($this->calls))
            );
        }
    }

    /**
     * Assert call count.
     */
    public function assertCalledTimes(int $count): void
    {
        $actual = count($this->calls);

        if ($actual !== $count) {
            throw new AssertionFailedError(
                sprintf('Expected tool "%s" to be called %d time(s), but it was called %d time(s).', $this->name, $count, $actual)
            );
        }
    }

    /**
     * Assert called with specific arguments.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function assertCalledWith(array $arguments): void
    {
        foreach ($this->calls as $call) {
            if ($call['arguments'] === $arguments) {
                return;
            }
        }

        throw new AssertionFailedError(
            sprintf('Expected tool "%s" to be called with %s, but it was not.', $this->name, json_encode($arguments))
        );
    }

    /**
     * Assert called with argument containing key.
     */
    public function assertCalledWithKey(string $key): void
    {
        foreach ($this->calls as $call) {
            if (array_key_exists($key, $call['arguments'])) {
                return;
            }
        }

        throw new AssertionFailedError(
            sprintf('Expected tool "%s" to be called with argument key "%s", but it was not.', $this->name, $key)
        );
    }

    /**
     * Get all calls.
     *
     * @return array<array{arguments: array<string, mixed>}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * Get the last call arguments.
     *
     * @return array<string, mixed>|null
     */
    public function getLastCallArguments(): ?array
    {
        $lastCall = $this->calls[count($this->calls) - 1] ?? null;

        return $lastCall ? $lastCall['arguments'] : null;
    }

    /**
     * Reset the fake tool state.
     */
    public function reset(): static
    {
        $this->calls = [];
        $this->resultIndex = 0;

        return $this;
    }
}
