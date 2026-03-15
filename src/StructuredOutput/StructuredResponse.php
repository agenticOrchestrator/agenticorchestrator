<?php

declare(strict_types=1);

namespace AgenticOrchestrator\StructuredOutput;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use JsonSerializable;
use stdClass;

/**
 * Structured Response - Typed wrapper for LLM JSON responses.
 *
 * Provides type-safe access to structured LLM output with
 * validation and transformation capabilities.
 *
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 */
class StructuredResponse implements Arrayable, ArrayAccess, Jsonable, JsonSerializable
{
    /**
     * The raw response data.
     *
     * @var array<string, mixed>
     */
    protected array $data;

    /**
     * The schema used for validation.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $schema;

    /**
     * Validation errors.
     *
     * @var array<string>
     */
    protected array $errors = [];

    /**
     * Create a new structured response.
     *
     * @param  array<string, mixed>|string  $data
     * @param  array<string, mixed>|null  $schema
     */
    public function __construct(array|string $data, ?array $schema = null)
    {
        if (is_string($data)) {
            $decoded = json_decode($data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException(
                    'Invalid JSON: '.json_last_error_msg()
                );
            }

            $data = $decoded;
        }

        $this->data = $data;
        $this->schema = $schema;

        if ($schema !== null) {
            $this->validate();
        }
    }

    /**
     * Create from JSON string.
     *
     * @param  array<string, mixed>|null  $schema
     */
    public static function fromJson(string $json, ?array $schema = null): static
    {
        return new static($json, $schema);
    }

    /**
     * Create from array.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $schema
     */
    public static function fromArray(array $data, ?array $schema = null): static
    {
        return new static($data, $schema);
    }

    /**
     * Get a value by key with dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data, $key, $default);
    }

    /**
     * Check if a key exists.
     */
    public function has(string $key): bool
    {
        return Arr::has($this->data, $key);
    }

    /**
     * Get a string value.
     */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_string($value) ? $value : $default;
    }

    /**
     * Get an integer value.
     */
    public function integer(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Get a float value.
     */
    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * Get a boolean value.
     */
    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes'], true);
        }

        return (bool) $value;
    }

    /**
     * Get an array value.
     *
     * @param  array<mixed>  $default
     * @return array<mixed>
     */
    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : $default;
    }

    /**
     * Get a nested object as StructuredResponse.
     */
    public function object(string $key): ?StructuredResponse
    {
        $value = $this->get($key);

        if (! is_array($value)) {
            return null;
        }

        return new static($value);
    }

    /**
     * Get array items as StructuredResponse objects.
     *
     * @return array<int, StructuredResponse>
     */
    public function items(string $key): array
    {
        $array = $this->array($key);

        return array_map(
            fn ($item) => is_array($item) ? new static($item) : $item,
            $array
        );
    }

    /**
     * Map array items through a callback.
     *
     * @template T
     *
     * @param  callable(StructuredResponse|mixed, int): T  $callback
     * @return array<int, T>
     */
    public function map(string $key, callable $callback): array
    {
        $items = $this->items($key);

        return array_map($callback, $items, array_keys($items));
    }

    /**
     * Pluck values from array items.
     *
     * @return array<mixed>
     */
    public function pluck(string $key, string $valueKey, ?string $keyBy = null): array
    {
        $array = $this->array($key);

        if ($keyBy !== null) {
            return Arr::pluck($array, $valueKey, $keyBy);
        }

        return Arr::pluck($array, $valueKey);
    }

    /**
     * Validate the data against the schema.
     */
    public function validate(): bool
    {
        if ($this->schema === null) {
            return true;
        }

        $this->errors = [];

        return $this->validateValue($this->data, $this->schema, '');
    }

    /**
     * Validate a value against a schema.
     *
     * @param  array<string, mixed>  $schema
     */
    protected function validateValue(mixed $value, array $schema, string $path): bool
    {
        $valid = true;

        // Type checking
        if (isset($schema['type'])) {
            $types = (array) $schema['type'];
            $actualType = $this->getJsonType($value);

            if (! in_array($actualType, $types, true)) {
                $this->errors[] = sprintf(
                    '%s: Expected type %s, got %s',
                    $path ?: 'root',
                    implode('|', $types),
                    $actualType
                );
                $valid = false;
            }
        }

        // Required properties
        if (isset($schema['required']) && is_array($value)) {
            foreach ($schema['required'] as $required) {
                if (! array_key_exists($required, $value)) {
                    $this->errors[] = sprintf(
                        '%s: Missing required property "%s"',
                        $path ?: 'root',
                        $required
                    );
                    $valid = false;
                }
            }
        }

        // Enum validation
        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $this->errors[] = sprintf(
                '%s: Value must be one of: %s',
                $path ?: 'root',
                implode(', ', $schema['enum'])
            );
            $valid = false;
        }

        // Nested properties
        if (isset($schema['properties']) && is_array($value)) {
            foreach ($schema['properties'] as $propName => $propSchema) {
                if (array_key_exists($propName, $value)) {
                    $propPath = $path ? "{$path}.{$propName}" : $propName;
                    if (! $this->validateValue($value[$propName], $propSchema, $propPath)) {
                        $valid = false;
                    }
                }
            }
        }

        // Array items
        if (isset($schema['items']) && is_array($value)) {
            foreach ($value as $index => $item) {
                $itemPath = $path ? "{$path}[{$index}]" : "[{$index}]";
                if (! $this->validateValue($item, $schema['items'], $itemPath)) {
                    $valid = false;
                }
            }
        }

        return $valid;
    }

    /**
     * Get the JSON type of a value.
     */
    protected function getJsonType(mixed $value): string
    {
        return match (true) {
            is_null($value) => 'null',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_string($value) => 'string',
            is_array($value) && array_is_list($value) => 'array',
            is_array($value) => 'object',
            $value instanceof stdClass => 'object',
            default => 'unknown',
        };
    }

    /**
     * Check if validation passed.
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * Get validation errors.
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all data.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Get only specific keys.
     *
     * @param  array<string>  $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        return Arr::only($this->data, $keys);
    }

    /**
     * Get all except specific keys.
     *
     * @param  array<string>  $keys
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        return Arr::except($this->data, $keys);
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Convert to JSON string.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->data, $options);
    }

    /**
     * JSON serialize.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    /**
     * ArrayAccess: offsetExists
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * ArrayAccess: offsetGet
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * ArrayAccess: offsetSet
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        Arr::set($this->data, $offset, $value);
    }

    /**
     * ArrayAccess: offsetUnset
     */
    public function offsetUnset(mixed $offset): void
    {
        Arr::forget($this->data, $offset);
    }

    /**
     * Magic getter.
     */
    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    /**
     * Magic isset.
     */
    public function __isset(string $name): bool
    {
        return $this->has($name);
    }
}
