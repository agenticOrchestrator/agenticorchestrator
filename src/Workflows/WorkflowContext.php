<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows;

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Workflow Context - Shared state container for workflow execution.
 *
 * Holds input data, step outputs, and execution metadata.
 * Supports array access for convenient data manipulation.
 *
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 */
class WorkflowContext implements Arrayable, ArrayAccess, JsonSerializable
{
    /**
     * Input data provided when workflow started.
     *
     * @var array<string, mixed>
     */
    protected array $input = [];

    /**
     * Output data from completed steps.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Execution metadata.
     *
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    /**
     * Completed step names.
     *
     * @var array<string>
     */
    protected array $completedSteps = [];

    /**
     * Failed step names with error info.
     *
     * @var array<string, array{message: string, exception?: string}>
     */
    protected array $failedSteps = [];

    /**
     * The current tenant scope.
     */
    protected ?TenantInterface $tenant = null;

    /**
     * The user who initiated the workflow.
     */
    protected ?object $user = null;

    /**
     * Create a new workflow context.
     *
     * @param  array<string, mixed>  $input  Initial input data
     * @param  array<string, mixed>  $metadata  Initial metadata
     */
    public function __construct(array $input = [], array $metadata = [])
    {
        $this->input = $input;
        $this->metadata = $metadata;
    }

    /**
     * Create a context from array (for resumption).
     *
     * @param  array<string, mixed>  $state
     */
    public static function fromState(array $state): static
    {
        $context = new static(
            $state['input'] ?? [],
            $state['metadata'] ?? []
        );

        $context->data = $state['data'] ?? [];
        $context->completedSteps = $state['completed_steps'] ?? [];
        $context->failedSteps = $state['failed_steps'] ?? [];

        return $context;
    }

    /**
     * Get a value from the context.
     *
     * Checks data first, then input, then metadata.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        if (array_key_exists($key, $this->input)) {
            return $this->input[$key];
        }

        return $default;
    }

    /**
     * Set a value in the context data.
     */
    public function set(string $key, mixed $value): static
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Check if a key exists in the context.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data)
            || array_key_exists($key, $this->input);
    }

    /**
     * Remove a key from the context.
     */
    public function forget(string $key): static
    {
        unset($this->data[$key]);

        return $this;
    }

    /**
     * Get the original input data.
     *
     * @return array<string, mixed>
     */
    public function getInput(): array
    {
        return $this->input;
    }

    /**
     * Get all data (input + step outputs).
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return array_merge($this->input, $this->data);
    }

    /**
     * Get only the step output data.
     *
     * @return array<string, mixed>
     */
    public function getOutputs(): array
    {
        return $this->data;
    }

    /**
     * Get metadata value.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set metadata value.
     */
    public function setMeta(string $key, mixed $value): static
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Get all metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Mark a step as completed.
     */
    public function markStepCompleted(string $stepName): static
    {
        if (! in_array($stepName, $this->completedSteps)) {
            $this->completedSteps[] = $stepName;
        }

        return $this;
    }

    /**
     * Mark a step as failed.
     */
    public function markStepFailed(string $stepName, string $message, ?string $exception = null): static
    {
        $this->failedSteps[$stepName] = [
            'message' => $message,
            'exception' => $exception,
        ];

        return $this;
    }

    /**
     * Check if a step has completed.
     */
    public function isStepCompleted(string $stepName): bool
    {
        return in_array($stepName, $this->completedSteps);
    }

    /**
     * Check if a step has failed.
     */
    public function isStepFailed(string $stepName): bool
    {
        return isset($this->failedSteps[$stepName]);
    }

    /**
     * Get completed step names.
     *
     * @return array<string>
     */
    public function getCompletedSteps(): array
    {
        return $this->completedSteps;
    }

    /**
     * Get failed step info.
     *
     * @return array<string, array{message: string, exception?: string}>
     */
    public function getFailedSteps(): array
    {
        return $this->failedSteps;
    }

    /**
     * Set the tenant scope.
     */
    public function setTenant(?TenantInterface $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    /**
     * Get the tenant scope.
     */
    public function getTenant(): ?TenantInterface
    {
        return $this->tenant;
    }

    /**
     * Set the initiating user.
     */
    public function setUser(?object $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get the initiating user.
     */
    public function getUser(): ?object
    {
        return $this->user;
    }

    /**
     * Get the full state for persistence/resumption.
     *
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        return [
            'input' => $this->input,
            'data' => $this->data,
            'metadata' => $this->metadata,
            'completed_steps' => $this->completedSteps,
            'failed_steps' => $this->failedSteps,
            'tenant_id' => $this->tenant?->getTenantKey(),
            'user_id' => $this->user?->id ?? null,
        ];
    }

    /**
     * Merge additional data into the context.
     *
     * @param  array<string, mixed>  $data
     */
    public function merge(array $data): static
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    /**
     * Clone with additional data.
     *
     * @param  array<string, mixed>  $data
     */
    public function with(array $data): static
    {
        $clone = clone $this;
        $clone->data = array_merge($clone->data, $data);

        return $clone;
    }

    // ArrayAccess implementation

    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->forget($offset);
    }

    // Arrayable implementation

    public function toArray(): array
    {
        return $this->getData();
    }

    // JsonSerializable implementation

    public function jsonSerialize(): array
    {
        return $this->getState();
    }
}
