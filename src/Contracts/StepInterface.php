<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Contracts;

use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;

/**
 * Interface for workflow steps.
 *
 * Steps are the building blocks of workflows. Each step
 * performs a discrete operation and can read from and
 * write to the workflow context.
 */
interface StepInterface
{
    /**
     * Get the step's name.
     *
     * Used for identification in logs and debugging.
     */
    public function getName(): string;

    /**
     * Execute the step.
     *
     * Performs the step's operation and returns a result
     * that may include output data for the context.
     *
     * @param  WorkflowContext  $context  The shared workflow context
     * @return StepResult The execution result
     */
    public function execute(WorkflowContext $context): StepResult;

    /**
     * Get the output key for storing results.
     *
     * If set, the step's output will be stored in the
     * workflow context under this key.
     *
     * @return string|null The key, or null if no output
     */
    public function getOutputKey(): ?string;

    /**
     * Check if this step can be retried on failure.
     *
     * Some steps may have side effects that prevent
     * safe retry.
     */
    public function isRetryable(): bool;

    /**
     * Get the maximum retry attempts.
     *
     * Only applicable if isRetryable() returns true.
     */
    public function getMaxRetries(): int;

    /**
     * Get the step timeout in seconds.
     *
     * Returns null to use the workflow default.
     */
    public function getTimeout(): ?int;

    /**
     * Check if this step requires human approval.
     *
     * If true, the workflow will pause and wait for
     * human input before continuing.
     */
    public function requiresHumanApproval(): bool;

    /**
     * Get the step's dependencies.
     *
     * Returns context keys that must exist before
     * this step can execute.
     *
     * @return array<int, string>
     */
    public function getDependencies(): array;
}
