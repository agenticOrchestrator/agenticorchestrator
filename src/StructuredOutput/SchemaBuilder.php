<?php

declare(strict_types=1);

namespace AgenticOrchestrator\StructuredOutput;

/**
 * Schema Builder - Fluent JSON Schema builder.
 *
 * Provides a developer-friendly API for building JSON schemas
 * for structured LLM output.
 */
class SchemaBuilder
{
    /**
     * Schema type constants.
     */
    public const TYPE_STRING = 'string';

    public const TYPE_NUMBER = 'number';

    public const TYPE_INTEGER = 'integer';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_ARRAY = 'array';

    public const TYPE_OBJECT = 'object';

    public const TYPE_NULL = 'null';

    /**
     * The schema definition.
     *
     * @var array<string, mixed>
     */
    protected array $schema = [];

    /**
     * Required properties for object type.
     *
     * @var array<string>
     */
    protected array $required = [];

    /**
     * Property definitions for object type.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $properties = [];

    /**
     * Create a new schema builder.
     */
    public function __construct(string $type = self::TYPE_OBJECT)
    {
        $this->schema['type'] = $type;
    }

    /**
     * Create an object schema.
     */
    public static function object(): static
    {
        return new static(self::TYPE_OBJECT);
    }

    /**
     * Create an array schema.
     */
    public static function array(): static
    {
        return new static(self::TYPE_ARRAY);
    }

    /**
     * Create a string schema.
     */
    public static function string(): static
    {
        return new static(self::TYPE_STRING);
    }

    /**
     * Create a number schema.
     */
    public static function number(): static
    {
        return new static(self::TYPE_NUMBER);
    }

    /**
     * Create an integer schema.
     */
    public static function integer(): static
    {
        return new static(self::TYPE_INTEGER);
    }

    /**
     * Create a boolean schema.
     */
    public static function boolean(): static
    {
        return new static(self::TYPE_BOOLEAN);
    }

    /**
     * Create from an existing schema array.
     *
     * @param  array<string, mixed>  $schema
     */
    public static function from(array $schema): static
    {
        $builder = new static($schema['type'] ?? self::TYPE_OBJECT);
        $builder->schema = $schema;

        if (isset($schema['properties'])) {
            $builder->properties = $schema['properties'];
        }

        if (isset($schema['required'])) {
            $builder->required = $schema['required'];
        }

        return $builder;
    }

    /**
     * Set schema title.
     */
    public function title(string $title): static
    {
        $this->schema['title'] = $title;

        return $this;
    }

    /**
     * Set schema description.
     */
    public function description(string $description): static
    {
        $this->schema['description'] = $description;

        return $this;
    }

    /**
     * Add a string property.
     */
    public function stringProperty(
        string $name,
        ?string $description = null,
        bool $required = false,
        ?array $enum = null,
        ?string $pattern = null,
        ?int $minLength = null,
        ?int $maxLength = null,
    ): static {
        $property = ['type' => self::TYPE_STRING];

        if ($description !== null) {
            $property['description'] = $description;
        }

        if ($enum !== null) {
            $property['enum'] = $enum;
        }

        if ($pattern !== null) {
            $property['pattern'] = $pattern;
        }

        if ($minLength !== null) {
            $property['minLength'] = $minLength;
        }

        if ($maxLength !== null) {
            $property['maxLength'] = $maxLength;
        }

        return $this->property($name, $property, $required);
    }

    /**
     * Add a number property.
     */
    public function numberProperty(
        string $name,
        ?string $description = null,
        bool $required = false,
        ?float $minimum = null,
        ?float $maximum = null,
    ): static {
        $property = ['type' => self::TYPE_NUMBER];

        if ($description !== null) {
            $property['description'] = $description;
        }

        if ($minimum !== null) {
            $property['minimum'] = $minimum;
        }

        if ($maximum !== null) {
            $property['maximum'] = $maximum;
        }

        return $this->property($name, $property, $required);
    }

    /**
     * Add an integer property.
     */
    public function integerProperty(
        string $name,
        ?string $description = null,
        bool $required = false,
        ?int $minimum = null,
        ?int $maximum = null,
    ): static {
        $property = ['type' => self::TYPE_INTEGER];

        if ($description !== null) {
            $property['description'] = $description;
        }

        if ($minimum !== null) {
            $property['minimum'] = $minimum;
        }

        if ($maximum !== null) {
            $property['maximum'] = $maximum;
        }

        return $this->property($name, $property, $required);
    }

    /**
     * Add a boolean property.
     */
    public function booleanProperty(
        string $name,
        ?string $description = null,
        bool $required = false,
    ): static {
        $property = ['type' => self::TYPE_BOOLEAN];

        if ($description !== null) {
            $property['description'] = $description;
        }

        return $this->property($name, $property, $required);
    }

    /**
     * Add an array property.
     *
     * @param  array<string, mixed>|SchemaBuilder  $items
     */
    public function arrayProperty(
        string $name,
        array|SchemaBuilder $items,
        ?string $description = null,
        bool $required = false,
        ?int $minItems = null,
        ?int $maxItems = null,
    ): static {
        $property = [
            'type' => self::TYPE_ARRAY,
            'items' => $items instanceof SchemaBuilder ? $items->build() : $items,
        ];

        if ($description !== null) {
            $property['description'] = $description;
        }

        if ($minItems !== null) {
            $property['minItems'] = $minItems;
        }

        if ($maxItems !== null) {
            $property['maxItems'] = $maxItems;
        }

        return $this->property($name, $property, $required);
    }

    /**
     * Add an object property.
     *
     * @param  array<string, mixed>|SchemaBuilder  $schema
     */
    public function objectProperty(
        string $name,
        array|SchemaBuilder $schema,
        ?string $description = null,
        bool $required = false,
    ): static {
        $property = $schema instanceof SchemaBuilder ? $schema->build() : $schema;

        if ($description !== null) {
            $property['description'] = $description;
        }

        return $this->property($name, $property, $required);
    }

    /**
     * Add an enum property.
     *
     * @param  array<string|int>  $values
     */
    public function enumProperty(
        string $name,
        array $values,
        ?string $description = null,
        bool $required = false,
    ): static {
        $property = [
            'type' => self::TYPE_STRING,
            'enum' => $values,
        ];

        if ($description !== null) {
            $property['description'] = $description;
        }

        return $this->property($name, $property, $required);
    }

    /**
     * Add a property with custom schema.
     *
     * @param  array<string, mixed>  $schema
     */
    public function property(string $name, array $schema, bool $required = false): static
    {
        $this->properties[$name] = $schema;

        if ($required && ! in_array($name, $this->required, true)) {
            $this->required[] = $name;
        }

        return $this;
    }

    /**
     * Mark properties as required.
     *
     * @param  string|array<string>  $names
     */
    public function required(string|array $names): static
    {
        $names = (array) $names;

        foreach ($names as $name) {
            if (! in_array($name, $this->required, true)) {
                $this->required[] = $name;
            }
        }

        return $this;
    }

    /**
     * Set array items schema.
     *
     * @param  array<string, mixed>|SchemaBuilder  $items
     */
    public function items(array|SchemaBuilder $items): static
    {
        $this->schema['items'] = $items instanceof SchemaBuilder ? $items->build() : $items;

        return $this;
    }

    /**
     * Set minimum items for array.
     */
    public function minItems(int $min): static
    {
        $this->schema['minItems'] = $min;

        return $this;
    }

    /**
     * Set maximum items for array.
     */
    public function maxItems(int $max): static
    {
        $this->schema['maxItems'] = $max;

        return $this;
    }

    /**
     * Disallow additional properties.
     */
    public function strict(): static
    {
        $this->schema['additionalProperties'] = false;

        return $this;
    }

    /**
     * Allow additional properties.
     *
     * @param  bool|array<string, mixed>  $allowed
     */
    public function additionalProperties(bool|array $allowed = true): static
    {
        $this->schema['additionalProperties'] = $allowed;

        return $this;
    }

    /**
     * Set default value.
     */
    public function default(mixed $value): static
    {
        $this->schema['default'] = $value;

        return $this;
    }

    /**
     * Set examples.
     *
     * @param  array<mixed>  $examples
     */
    public function examples(array $examples): static
    {
        $this->schema['examples'] = $examples;

        return $this;
    }

    /**
     * Build the schema array.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $schema = $this->schema;

        if (! empty($this->properties)) {
            $schema['properties'] = $this->properties;
        }

        if (! empty($this->required)) {
            $schema['required'] = array_values(array_unique($this->required));
        }

        return $schema;
    }

    /**
     * Convert to JSON string.
     */
    public function toJson(int $flags = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->build(), $flags);
    }

    /**
     * Convert to array (alias for build).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->build();
    }
}
