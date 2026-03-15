<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Throwable;

/**
 * Workflow Result - Represents the outcome of a workflow execution.
 *
 * @implements Arrayable<string, mixed>
 */
class WorkflowResult implements Arrayable, JsonSerializable
{
    /**
     * Create a new workflow result.
     *
     * @param  string  $executionId  Unique execution identifier
     * @param  string  $status  The execution status
     * @param  mixed  $output  The workflow output data
     * @param  WorkflowContext  $context  The execution context
     * @param  float  $duration  Execution duration in milliseconds
     * @param  array<string, mixed>  $metadata  Additional metadata
     * @param  string|null  $error  Error message if failed
     * @param  Throwable|null  $exception  Exception if failed
     */
    public function __construct(
        public readonly string $executionId,
        public readonly string $status,
        public readonly mixed $output,
        public readonly WorkflowContext $context,
        public readonly float $duration,
        public readonly array $metadata = [],
        public readonly ?string $error = null,
        public readonly ?Throwable $exception = null,
    ) {}

    /**
     * Check if the workflow completed successfully.
     */
    public function isSuccess(): bool
    {
        return $this->status === StepResult::STATUS_SUCCESS;
    }

    /**
     * Check if the workflow failed.
     */
    public function isFailed(): bool
    {
        return $this->status === StepResult::STATUS_FAILED;
    }

    /**
     * Check if the workflow is paused.
     */
    public function isPaused(): bool
    {
        return $this->status === StepResult::STATUS_WAITING
            || $this->status === StepResult::STATUS_PENDING;
    }

    /**
     * Get the output data.
     */
    public function getOutput(): mixed
    {
        return $this->output;
    }

    /**
     * Get a specific output key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (is_array($this->output)) {
            return $this->output[$key] ?? $default;
        }

        return $default;
    }

    /**
     * Get the workflow state for persistence/resumption.
     *
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        return $this->context->getState();
    }

    /**
     * Get completed step names.
     *
     * @return array<string>
     */
    public function getCompletedSteps(): array
    {
        return $this->context->getCompletedSteps();
    }

    /**
     * Get failed step info.
     *
     * @return array<string, array{message: string, exception?: string}>
     */
    public function getFailedSteps(): array
    {
        return $this->context->getFailedSteps();
    }

    /**
     * Get metadata value.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'execution_id' => $this->executionId,
            'status' => $this->status,
            'output' => $this->output,
            'duration_ms' => $this->duration,
            'metadata' => $this->metadata,
            'completed_steps' => $this->getCompletedSteps(),
            'failed_steps' => $this->getFailedSteps(),
            'error' => $this->error,
            'state' => $this->getState(),
        ];
    }

    /**
     * Serialize to JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
