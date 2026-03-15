<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Agents\Concerns;

use AgenticOrchestrator\StructuredOutput\SchemaBuilder;
use AgenticOrchestrator\StructuredOutput\StructuredResponse;
use InvalidArgumentException;

/**
 * HasStructuredOutput - Adds structured output capability to agents.
 *
 * Enables agents to return typed, validated JSON responses
 * following a defined schema.
 */
trait HasStructuredOutput
{
    /**
     * The output schema for structured responses.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $outputSchema = null;

    /**
     * Whether to validate output against schema.
     */
    protected bool $validateOutput = true;

    /**
     * Whether to include schema in prompt.
     */
    protected bool $includeSchemaInPrompt = true;

    /**
     * Set the output schema.
     *
     * @param  array<string, mixed>|SchemaBuilder  $schema
     */
    public function withSchema(array|SchemaBuilder $schema): static
    {
        $this->outputSchema = $schema instanceof SchemaBuilder
            ? $schema->build()
            : $schema;

        return $this;
    }

    /**
     * Clear the output schema.
     */
    public function withoutSchema(): static
    {
        $this->outputSchema = null;

        return $this;
    }

    /**
     * Enable output validation.
     */
    public function validateOutput(bool $validate = true): static
    {
        $this->validateOutput = $validate;

        return $this;
    }

    /**
     * Skip output validation.
     */
    public function skipValidation(): static
    {
        return $this->validateOutput(false);
    }

    /**
     * Include schema in prompt.
     */
    public function includeSchemaInPrompt(bool $include = true): static
    {
        $this->includeSchemaInPrompt = $include;

        return $this;
    }

    /**
     * Get the current schema.
     *
     * @return array<string, mixed>|null
     */
    public function getSchema(): ?array
    {
        return $this->outputSchema;
    }

    /**
     * Check if structured output is enabled.
     */
    public function hasSchema(): bool
    {
        return $this->outputSchema !== null;
    }

    /**
     * Send a message and get a structured response.
     *
     * @param  string  $message  The user message
     * @param  array<string, mixed>  $context  Additional context
     */
    public function respondStructured(string $message, array $context = []): StructuredResponse
    {
        if ($this->outputSchema === null) {
            throw new InvalidArgumentException(
                'No schema defined. Use withSchema() to define the output schema.'
            );
        }

        // Add schema instruction to message if configured
        $enhancedMessage = $this->includeSchemaInPrompt
            ? $this->addSchemaToMessage($message)
            : $message;

        // Get the response
        $response = $this->respond($enhancedMessage, array_merge($context, [
            '__structured' => true,
            '__schema' => $this->outputSchema,
        ]));

        // Parse as structured response
        $content = $response->content ?? '';

        // Extract JSON from response if wrapped in markdown
        $json = $this->extractJson($content);

        $structured = new StructuredResponse(
            $json,
            $this->validateOutput ? $this->outputSchema : null
        );

        if ($this->validateOutput && ! $structured->isValid()) {
            throw new InvalidArgumentException(
                'Response validation failed: '.implode(', ', $structured->getErrors())
            );
        }

        return $structured;
    }

    /**
     * Add schema instruction to message.
     */
    protected function addSchemaToMessage(string $message): string
    {
        $schemaJson = json_encode($this->outputSchema, JSON_PRETTY_PRINT);

        return <<<PROMPT
{$message}

Respond with valid JSON matching this schema:
```json
{$schemaJson}
```

Important: Return ONLY valid JSON, no additional text or explanation.
PROMPT;
    }

    /**
     * Extract JSON from response text.
     */
    protected function extractJson(string $content): string
    {
        // Try to extract JSON from markdown code blocks
        if (preg_match('/```(?:json)?\s*\n?([\s\S]*?)\n?```/', $content, $matches)) {
            return trim($matches[1]);
        }

        // Try to find JSON object or array
        if (preg_match('/(\{[\s\S]*\}|\[[\s\S]*\])/', $content, $matches)) {
            return trim($matches[1]);
        }

        // Return as-is and let JSON parser handle it
        return trim($content);
    }

    /**
     * Create a common schema for specific output types.
     */
    public function withListSchema(string $itemDescription = 'item'): static
    {
        return $this->withSchema(
            SchemaBuilder::object()
                ->arrayProperty(
                    'items',
                    SchemaBuilder::object()->strict()->build(),
                    "List of {$itemDescription}s",
                    required: true
                )
                ->integerProperty('count', 'Number of items', required: true)
                ->strict()
        );
    }

    /**
     * Create a boolean decision schema.
     */
    public function withDecisionSchema(string $description = 'decision'): static
    {
        return $this->withSchema(
            SchemaBuilder::object()
                ->booleanProperty('decision', $description, required: true)
                ->stringProperty('reasoning', 'Explanation for the decision', required: true)
                ->numberProperty('confidence', 'Confidence score (0-1)', required: true)
                ->strict()
        );
    }

    /**
     * Create a classification schema.
     *
     * @param  array<string>  $categories
     */
    public function withClassificationSchema(array $categories): static
    {
        return $this->withSchema(
            SchemaBuilder::object()
                ->enumProperty('category', $categories, 'The classified category', required: true)
                ->stringProperty('reasoning', 'Explanation for classification', required: true)
                ->numberProperty('confidence', 'Confidence score (0-1)', required: true)
                ->strict()
        );
    }

    /**
     * Create an extraction schema with specified fields.
     *
     * @param  array<string, array{type: string, description?: string, required?: bool}>  $fields
     */
    public function withExtractionSchema(array $fields): static
    {
        $schema = SchemaBuilder::object();

        foreach ($fields as $name => $config) {
            $type = $config['type'] ?? 'string';
            $description = $config['description'] ?? null;
            $required = $config['required'] ?? false;

            match ($type) {
                'string' => $schema->stringProperty($name, $description, $required),
                'number' => $schema->numberProperty($name, $description, $required),
                'integer' => $schema->integerProperty($name, $description, $required),
                'boolean' => $schema->booleanProperty($name, $description, $required),
                default => $schema->property($name, ['type' => $type], $required),
            };
        }

        return $this->withSchema($schema->strict());
    }
}
