<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation;

use JsonSerializable;

/**
 * Assertion Result - Result from a single assertion check.
 */
class AssertionResult implements JsonSerializable
{
    /**
     * Create a new assertion result.
     *
     * @param  string  $name  The assertion name
     * @param  bool  $passed  Whether the assertion passed
     * @param  string  $message  Human-readable result message
     * @param  mixed  $expected  Expected value
     * @param  mixed  $actual  Actual value
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $passed,
        public readonly string $message = '',
        public readonly mixed $expected = null,
        public readonly mixed $actual = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a passed assertion result.
     */
    public static function pass(
        string $name,
        string $message = '',
        mixed $expected = null,
        mixed $actual = null,
        array $metadata = [],
    ): static {
        return new static(
            name: $name,
            passed: true,
            message: $message ?: "Assertion '{$name}' passed",
            expected: $expected,
            actual: $actual,
            metadata: $metadata,
        );
    }

    /**
     * Create a failed assertion result.
     */
    public static function fail(
        string $name,
        string $message = '',
        mixed $expected = null,
        mixed $actual = null,
        array $metadata = [],
    ): static {
        return new static(
            name: $name,
            passed: false,
            message: $message ?: "Assertion '{$name}' failed",
            expected: $expected,
            actual: $actual,
            metadata: $metadata,
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'passed' => $this->passed,
            'message' => $this->message,
            'expected' => $this->expected,
            'actual' => $this->actual,
            'metadata' => $this->metadata,
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
