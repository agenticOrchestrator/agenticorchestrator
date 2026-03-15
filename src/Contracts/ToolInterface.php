<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Contracts;

use AgenticOrchestrator\Exceptions\ToolExecutionException;
use AgenticOrchestrator\Exceptions\ValidationException;

/**
 * Interface for agent tools.
 *
 * Tools are callable functions that agents can invoke to
 * perform actions like database queries, API calls, or
 * any other side effects.
 */
interface ToolInterface
{
    /**
     * Get the tool's unique name.
     *
     * This name is used by the LLM to identify and call
     * the tool. Should be a valid function name format.
     */
    public function getName(): string;

    /**
     * Get the tool's description for the LLM.
     *
     * A clear description helps the LLM understand when
     * and how to use this tool effectively.
     */
    public function getDescription(): string;

    /**
     * Get the parameter definitions.
     *
     * Returns an array of parameter definitions including
     * name, type, description, required status, etc.
     *
     * @return array<string, array{
     *     type: string,
     *     description: string,
     *     required?: bool,
     *     enum?: array<string>,
     *     default?: mixed,
     *     format?: string
     * }>
     */
    public function getParameters(): array;

    /**
     * Execute the tool with the given arguments.
     *
     * @param  array<string, mixed>  $arguments  The arguments passed by the LLM
     * @return mixed The result to return to the LLM
     *
     * @throws ToolExecutionException
     */
    public function execute(array $arguments): mixed;

    /**
     * Generate OpenAI-compatible function schema.
     *
     * Returns a schema that can be passed to the LLM
     * for function calling.
     *
     * @return array{
     *     type: string,
     *     function: array{
     *         name: string,
     *         description: string,
     *         parameters: array<string, mixed>
     *     }
     * }
     */
    public function toSchema(): array;

    /**
     * Check if this tool can be executed in parallel.
     *
     * Some tools may have side effects that require
     * sequential execution.
     */
    public function isParallel(): bool;

    /**
     * Check if this tool's results can be cached.
     *
     * For idempotent tools, caching can improve
     * performance and reduce costs.
     */
    public function isCacheable(): bool;

    /**
     * Get the cache TTL in seconds.
     *
     * Only applicable if isCacheable() returns true.
     */
    public function getCacheTtl(): int;

    /**
     * Validate the arguments before execution.
     *
     * @param  array<string, mixed>  $arguments  The arguments to validate
     * @return bool True if valid
     *
     * @throws ValidationException
     */
    public function validate(array $arguments): bool;
}
