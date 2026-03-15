<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Exceptions;

use Throwable;

/**
 * Thrown when a workflow encounters an error.
 */
class WorkflowException extends AgentException
{
    protected ?string $workflowName = null;

    protected ?string $stepName = null;

    protected ?int $stepIndex = null;

    /**
     * Create a new workflow exception.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = '',
        ?string $workflowName = null,
        ?string $stepName = null,
        ?int $stepIndex = null,
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
    ) {
        $this->workflowName = $workflowName;
        $this->stepName = $stepName;
        $this->stepIndex = $stepIndex;

        $prefix = 'Workflow error';
        if ($workflowName) {
            $prefix = "Workflow '{$workflowName}'";
        }
        if ($stepName !== null || $stepIndex !== null) {
            $stepInfo = $stepName ?? "step {$stepIndex}";
            $prefix .= " at {$stepInfo}";
        }

        $fullMessage = "{$prefix}: {$message}";

        parent::__construct($fullMessage, $code, $previous, array_merge($context, [
            'workflow_name' => $workflowName,
            'step_name' => $stepName,
            'step_index' => $stepIndex,
        ]));
    }

    /**
     * Create for a step failure.
     */
    public static function stepFailed(
        string $message,
        ?string $workflowName = null,
        ?string $stepName = null,
        ?int $stepIndex = null,
        ?Throwable $previous = null,
    ): static {
        $exception = new static($message, $workflowName, $stepName, $stepIndex, 0, $previous);
        $exception->recoverable = true;

        return $exception;
    }

    /**
     * Create for timeout.
     */
    public static function timeout(
        int $timeoutSeconds,
        ?string $workflowName = null,
        ?string $stepName = null,
        ?int $stepIndex = null,
    ): static {
        $exception = new static(
            "Timed out after {$timeoutSeconds} seconds",
            $workflowName,
            $stepName,
            $stepIndex,
        );
        $exception->recoverable = true;

        return $exception;
    }

    /**
     * Create for invalid state.
     */
    public static function invalidState(
        string $expectedState,
        string $actualState,
        ?string $workflowName = null,
    ): static {
        return new static(
            "Invalid state: expected '{$expectedState}', got '{$actualState}'",
            $workflowName,
        );
    }

    /**
     * Create for resumption failure.
     */
    public static function cannotResume(
        string $reason,
        ?string $workflowName = null,
    ): static {
        return new static(
            "Cannot resume: {$reason}",
            $workflowName,
        );
    }

    /**
     * Get the workflow name.
     */
    public function getWorkflowName(): ?string
    {
        return $this->workflowName;
    }

    /**
     * Get the step name.
     */
    public function getStepName(): ?string
    {
        return $this->stepName;
    }

    /**
     * Get the step index.
     */
    public function getStepIndex(): ?int
    {
        return $this->stepIndex;
    }
}
