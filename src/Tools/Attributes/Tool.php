<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tools\Attributes;

use Attribute;

/**
 * Marks a method as a tool that can be called by an agent.
 *
 * Tools are functions that agents can invoke to perform actions
 * like database queries, API calls, or any other operations.
 *
 * @example
 * ```php
 * #[Tool('Get the current weather for a location')]
 * public function getWeather(string $location): array
 * {
 *     return ['temperature' => 22, 'condition' => 'sunny'];
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Tool
{
    /**
     * @param  string  $description  Description of what the tool does (shown to LLM)
     * @param  string|null  $name  Override the method name for the tool
     * @param  bool  $parallel  Whether this tool can be executed in parallel with others
     * @param  bool  $cacheable  Whether results can be cached
     * @param  int  $cacheTtl  Cache TTL in seconds (if cacheable)
     * @param  bool  $hidden  Whether to hide this tool from certain contexts
     */
    public function __construct(
        public readonly string $description,
        public readonly ?string $name = null,
        public readonly bool $parallel = true,
        public readonly bool $cacheable = false,
        public readonly int $cacheTtl = 300,
        public readonly bool $hidden = false,
    ) {}
}
