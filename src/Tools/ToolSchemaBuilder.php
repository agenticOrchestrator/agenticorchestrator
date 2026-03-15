<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tools;

use AgenticOrchestrator\Tools\Attributes\Tool;
use AgenticOrchestrator\Tools\Attributes\ToolParameter;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Builds OpenAI-compatible tool schemas from method definitions.
 */
class ToolSchemaBuilder
{
    /**
     * Build a tool schema from a reflection method.
     *
     * @return array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}
     */
    public function buildFromMethod(ReflectionMethod $method, Tool $toolAttribute): array
    {
        $parameters = $this->buildParameters($method);

        return [
            'type' => 'function',
            'function' => [
                'name' => $toolAttribute->name ?? $method->getName(),
                'description' => $toolAttribute->description,
                'parameters' => $parameters,
            ],
        ];
    }

    /**
     * Build parameters schema from method parameters.
     *
     * @return array{type: string, properties: array<string, mixed>, required: array<int, string>}
     */
    protected function buildParameters(ReflectionMethod $method): array
    {
        $properties = [];
        $required = [];

        foreach ($method->getParameters() as $param) {
            $paramSchema = $this->buildParameterSchema($param);
            $paramName = $param->getName();

            $properties[$paramName] = $paramSchema;

            // Check if required
            $toolParamAttr = $this->getToolParameterAttribute($param);
            $isRequired = $toolParamAttr?->required ?? ! $param->isDefaultValueAvailable();

            if ($isRequired) {
                $required[] = $paramName;
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }

    /**
     * Build schema for a single parameter.
     *
     * @return array<string, mixed>
     */
    protected function buildParameterSchema(ReflectionParameter $param): array
    {
        $schema = [];
        $toolParamAttr = $this->getToolParameterAttribute($param);

        // Determine type
        $type = $param->getType();
        $schema['type'] = $this->mapPhpTypeToJsonType($type);

        // Add description
        if ($toolParamAttr?->description) {
            $schema['description'] = $toolParamAttr->description;
        }

        // Add enum if specified
        if ($toolParamAttr?->enum) {
            $schema['enum'] = $toolParamAttr->enum;
        }

        // Add format if specified
        if ($toolParamAttr?->format) {
            $schema['format'] = $toolParamAttr->format;
        }

        // Add constraints
        if ($toolParamAttr?->minLength !== null) {
            $schema['minLength'] = $toolParamAttr->minLength;
        }
        if ($toolParamAttr?->maxLength !== null) {
            $schema['maxLength'] = $toolParamAttr->maxLength;
        }
        if ($toolParamAttr?->minimum !== null) {
            $schema['minimum'] = $toolParamAttr->minimum;
        }
        if ($toolParamAttr?->maximum !== null) {
            $schema['maximum'] = $toolParamAttr->maximum;
        }
        if ($toolParamAttr?->pattern !== null) {
            $schema['pattern'] = $toolParamAttr->pattern;
        }

        // Add default if available
        if ($param->isDefaultValueAvailable()) {
            $default = $param->getDefaultValue();
            if ($default !== null) {
                $schema['default'] = $default;
            }
        }

        return $schema;
    }

    /**
     * Get ToolParameter attribute from a parameter.
     */
    protected function getToolParameterAttribute(ReflectionParameter $param): ?ToolParameter
    {
        $attributes = $param->getAttributes(ToolParameter::class);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Map PHP type to JSON Schema type.
     */
    protected function mapPhpTypeToJsonType(?\ReflectionType $type): string
    {
        if ($type === null) {
            return 'string';
        }

        if (! $type instanceof ReflectionNamedType) {
            return 'string';
        }

        return match ($type->getName()) {
            'int', 'integer' => 'integer',
            'float', 'double' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            'object', 'stdClass' => 'object',
            default => 'string',
        };
    }

    /**
     * Build schemas for multiple methods.
     *
     * @param  array<int, array{method: ReflectionMethod, attribute: Tool}>  $tools
     * @return array<int, array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}>
     */
    public function buildFromMethods(array $tools): array
    {
        $schemas = [];

        foreach ($tools as $tool) {
            $schemas[] = $this->buildFromMethod($tool['method'], $tool['attribute']);
        }

        return $schemas;
    }
}
