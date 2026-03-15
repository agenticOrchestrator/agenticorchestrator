<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tools\Attributes;

use Attribute;

/**
 * Describes a parameter for a tool method.
 *
 * Use this attribute to provide additional information about
 * tool parameters that the LLM needs to understand how to use them.
 *
 * @example
 * ```php
 * #[Tool('Get weather')]
 * public function getWeather(
 *     #[ToolParameter('The city name or coordinates')]
 *     string $location,
 *     #[ToolParameter('Temperature unit', enum: ['celsius', 'fahrenheit'])]
 *     string $unit = 'celsius'
 * ): array {
 *     // ...
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class ToolParameter
{
    /**
     * @param  string  $description  Description of the parameter (shown to LLM)
     * @param  bool|null  $required  Whether the parameter is required (auto-detected if null)
     * @param  array<int, string>|null  $enum  List of allowed values
     * @param  mixed  $default  Default value (auto-detected from method signature)
     * @param  string|null  $format  Format hint (e.g., 'email', 'uri', 'date')
     * @param  int|null  $minLength  Minimum string length
     * @param  int|null  $maxLength  Maximum string length
     * @param  int|float|null  $minimum  Minimum numeric value
     * @param  int|float|null  $maximum  Maximum numeric value
     * @param  string|null  $pattern  Regex pattern for validation
     */
    public function __construct(
        public readonly string $description,
        public readonly ?bool $required = null,
        public readonly ?array $enum = null,
        public readonly mixed $default = null,
        public readonly ?string $format = null,
        public readonly ?int $minLength = null,
        public readonly ?int $maxLength = null,
        public readonly int|float|null $minimum = null,
        public readonly int|float|null $maximum = null,
        public readonly ?string $pattern = null,
    ) {}
}
