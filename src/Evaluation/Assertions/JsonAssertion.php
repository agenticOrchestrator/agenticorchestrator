<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation\Assertions;

use AgenticOrchestrator\Evaluation\AssertionResult;
use AgenticOrchestrator\Evaluation\Contracts\AssertionInterface;
use AgenticOrchestrator\Evaluation\TestCase;

/**
 * JSON Assertion - Checks if output is valid JSON and optionally validates structure.
 */
class JsonAssertion implements AssertionInterface
{
    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'json';
    }

    /**
     * {@inheritDoc}
     */
    public function evaluate(string $actualOutput, mixed $config, TestCase $testCase): AssertionResult
    {
        // Extract JSON from output (might be wrapped in markdown code blocks)
        $json = $this->extractJson($actualOutput);

        if ($json === null) {
            return AssertionResult::fail(
                name: $this->name(),
                message: 'Output is not valid JSON',
                expected: 'Valid JSON',
                actual: 'Invalid or no JSON found',
            );
        }

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return AssertionResult::fail(
                name: $this->name(),
                message: 'JSON parse error: '.json_last_error_msg(),
                expected: 'Valid JSON',
                actual: json_last_error_msg(),
            );
        }

        // If config is true or empty, just validate that it's valid JSON
        if ($config === true || empty($config)) {
            return AssertionResult::pass(
                name: $this->name(),
                message: 'Output is valid JSON',
                expected: 'Valid JSON',
                actual: 'Valid JSON',
            );
        }

        // If config has required keys
        if (isset($config['has_keys']) || isset($config['required'])) {
            $requiredKeys = $config['has_keys'] ?? $config['required'] ?? [];
            $missing = [];

            foreach ($requiredKeys as $key) {
                if (! $this->hasKey($decoded, $key)) {
                    $missing[] = $key;
                }
            }

            if (! empty($missing)) {
                return AssertionResult::fail(
                    name: $this->name(),
                    message: 'JSON missing required keys: '.implode(', ', $missing),
                    expected: $requiredKeys,
                    actual: array_keys($decoded),
                    metadata: ['missing' => $missing],
                );
            }
        }

        // If config has type validation
        if (isset($config['type'])) {
            $expectedType = $config['type'];
            $actualType = gettype($decoded);

            // Map PHP types to JSON types
            $typeMap = [
                'array' => 'array',
                'object' => 'array',  // Associative arrays are "objects" in JSON
                'string' => 'string',
                'integer' => 'integer',
                'double' => 'number',
                'boolean' => 'boolean',
                'NULL' => 'null',
            ];

            $jsonType = $typeMap[$actualType] ?? $actualType;

            if ($expectedType !== $jsonType && ! ($expectedType === 'object' && is_array($decoded))) {
                return AssertionResult::fail(
                    name: $this->name(),
                    message: "Expected JSON type '{$expectedType}', got '{$jsonType}'",
                    expected: $expectedType,
                    actual: $jsonType,
                );
            }
        }

        return AssertionResult::pass(
            name: $this->name(),
            message: 'JSON validation passed',
            expected: $config,
            actual: $decoded,
        );
    }

    /**
     * Extract JSON from output that might be wrapped in markdown.
     */
    protected function extractJson(string $output): ?string
    {
        // Try to extract from markdown code block
        if (preg_match('/```(?:json)?\s*\n?([\s\S]*?)\n?```/', $output, $matches)) {
            return trim($matches[1]);
        }

        // Try the raw output
        $trimmed = trim($output);
        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            return $trimmed;
        }

        return null;
    }

    /**
     * Check if array has a key (supports dot notation).
     */
    protected function hasKey(array $data, string $key): bool
    {
        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            $current = $data;

            foreach ($keys as $k) {
                if (! is_array($current) || ! array_key_exists($k, $current)) {
                    return false;
                }
                $current = $current[$k];
            }

            return true;
        }

        return array_key_exists($key, $data);
    }
}
