<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Steps;

use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use Throwable;

/**
 * Abstract Step - Base class for workflow steps.
 *
 * Provides common functionality and sensible defaults.
 */
abstract class Step implements StepInterface
{
    /**
     * Step name (auto-generated from class if not set).
     */
    protected ?string $name = null;

    /**
     * Output key for storing results in context.
     */
    protected ?string $outputKey = null;

    /**
     * Whether this step can be retried on failure.
     */
    protected bool $retryable = true;

    /**
     * Maximum retry attempts.
     */
    protected int $maxRetries = 3;

    /**
     * Timeout in seconds.
     */
    protected ?int $timeout = null;

    /**
     * Whether this step requires human approval.
     */
    protected bool $requiresApproval = false;

    /**
     * Context keys required before this step can run.
     *
     * @var array<string>
     */
    protected array $dependencies = [];

    /**
     * Get the step name.
     */
    public function getName(): string
    {
        if ($this->name !== null) {
            return $this->name;
        }

        // Generate from class name
        $class = class_basename(static::class);
        $name = preg_replace('/Step$/', '', $class);

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }

    /**
     * Execute the step.
     */
    public function execute(WorkflowContext $context): StepResult
    {
        try {
            // Check dependencies
            foreach ($this->dependencies as $dep) {
                if (! $context->has($dep)) {
                    return StepResult::failed("Missing dependency: {$dep}");
                }
            }

            // Execute the step's logic
            $result = $this->handle($context);

            // Wrap raw data in success result
            if (! $result instanceof StepResult) {
                $result = StepResult::success($result);
            }

            // Store output if key is set
            if ($result->isSuccess() && $this->outputKey && $result->output !== null) {
                $context->set($this->outputKey, $result->output);
            }

            return $result;
        } catch (Throwable $e) {
            return StepResult::failed($e->getMessage(), $e);
        }
    }

    /**
     * The step's actual logic.
     *
     * Implement this in subclasses.
     *
     * @return StepResult|mixed Return StepResult or data to wrap in success
     */
    abstract protected function handle(WorkflowContext $context): mixed;

    /**
     * Get the output key.
     */
    public function getOutputKey(): ?string
    {
        return $this->outputKey;
    }

    /**
     * Set the output key.
     */
    public function outputAs(string $key): static
    {
        $this->outputKey = $key;

        return $this;
    }

    /**
     * Check if retryable.
     */
    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    /**
     * Get max retries.
     */
    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * Set retry configuration.
     */
    public function retry(int $attempts = 3): static
    {
        $this->retryable = true;
        $this->maxRetries = $attempts;

        return $this;
    }

    /**
     * Disable retries.
     */
    public function noRetry(): static
    {
        $this->retryable = false;

        return $this;
    }

    /**
     * Get timeout.
     */
    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    /**
     * Set timeout.
     */
    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Check if requires human approval.
     */
    public function requiresHumanApproval(): bool
    {
        return $this->requiresApproval;
    }

    /**
     * Mark as requiring human approval.
     */
    public function requireApproval(): static
    {
        $this->requiresApproval = true;

        return $this;
    }

    /**
     * Get dependencies.
     *
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * Set dependencies.
     *
     * @param  array<string>  $keys
     */
    public function dependsOn(array $keys): static
    {
        $this->dependencies = $keys;

        return $this;
    }

    /**
     * Set the step name.
     */
    public function as(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
