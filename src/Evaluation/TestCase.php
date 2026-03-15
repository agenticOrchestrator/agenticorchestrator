<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation;

use JsonSerializable;

/**
 * Test Case - Defines a single evaluation test for an agent.
 */
class TestCase implements JsonSerializable
{
    /**
     * Create a new test case.
     *
     * @param  string  $name  Unique name for the test case
     * @param  string  $input  The input message to send to the agent
     * @param  array<string, mixed>  $assertions  Assertions to validate response
     * @param  array<string, mixed>  $metrics  LLM-judged metrics to evaluate
     * @param  string|null  $expectedOutput  Expected output for comparison
     * @param  array<string, mixed>  $context  Additional context for the agent
     * @param  array<string, mixed>  $metadata  Additional test metadata
     * @param  int  $timeout  Timeout in seconds for the test
     */
    public function __construct(
        public readonly string $name,
        public readonly string $input,
        public readonly array $assertions = [],
        public readonly array $metrics = [],
        public readonly ?string $expectedOutput = null,
        public readonly array $context = [],
        public readonly array $metadata = [],
        public readonly int $timeout = 30,
    ) {}

    /**
     * Create from array.
     */
    public static function fromArray(array $data): static
    {
        return new static(
            name: $data['name'],
            input: $data['input'],
            assertions: $data['assertions'] ?? [],
            metrics: $data['metrics'] ?? [],
            expectedOutput: $data['expected_output'] ?? null,
            context: $data['context'] ?? [],
            metadata: $data['metadata'] ?? [],
            timeout: $data['timeout'] ?? 30,
        );
    }

    /**
     * Check if test has assertions.
     */
    public function hasAssertions(): bool
    {
        return ! empty($this->assertions);
    }

    /**
     * Check if test has metrics to evaluate.
     */
    public function hasMetrics(): bool
    {
        return ! empty($this->metrics);
    }

    /**
     * Check if test has expected output for comparison.
     */
    public function hasExpectedOutput(): bool
    {
        return $this->expectedOutput !== null;
    }

    /**
     * Get a specific assertion configuration.
     */
    public function getAssertion(string $name): mixed
    {
        return $this->assertions[$name] ?? null;
    }

    /**
     * Get a specific metric configuration.
     */
    public function getMetric(string $name): mixed
    {
        return $this->metrics[$name] ?? null;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'input' => $this->input,
            'assertions' => $this->assertions,
            'metrics' => $this->metrics,
            'expected_output' => $this->expectedOutput,
            'context' => $this->context,
            'metadata' => $this->metadata,
            'timeout' => $this->timeout,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
