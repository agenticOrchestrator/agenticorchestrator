<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Patterns;

use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;

/**
 * Sequential Pattern - Executes steps one after another.
 *
 * Steps are executed in order, with each step waiting for
 * the previous one to complete before starting.
 */
class SequentialPattern implements StepInterface
{
    /**
     * The steps to execute.
     *
     * @var array<StepInterface>
     */
    protected array $steps = [];

    /**
     * Pattern name.
     */
    protected string $name = 'sequential';

    /**
     * Whether to stop on first failure.
     */
    protected bool $stopOnFailure = true;

    /**
     * Create a new sequential pattern.
     *
     * @param  array<StepInterface>  $steps
     */
    public function __construct(array $steps = [])
    {
        $this->steps = $steps;
    }

    /**
     * Create a sequential pattern.
     *
     * @param  array<StepInterface>  $steps
     */
    public static function make(array $steps = []): static
    {
        return new static($steps);
    }

    /**
     * Add a step to the sequence.
     */
    public function addStep(StepInterface $step): static
    {
        $this->steps[] = $step;

        return $this;
    }

    /**
     * Add multiple steps.
     *
     * @param  array<StepInterface>  $steps
     */
    public function addSteps(array $steps): static
    {
        foreach ($steps as $step) {
            $this->addStep($step);
        }

        return $this;
    }

    /**
     * Continue execution even if a step fails.
     */
    public function continueOnFailure(): static
    {
        $this->stopOnFailure = false;

        return $this;
    }

    /**
     * Execute all steps sequentially.
     */
    public function execute(WorkflowContext $context): StepResult
    {
        $results = [];
        $hasFailure = false;
        $isPaused = false;

        foreach ($this->steps as $step) {
            $stepName = $step->getName();

            // Skip already completed steps (for resumption)
            if ($context->isStepCompleted($stepName)) {
                continue;
            }

            // Skip if step dependencies not met
            foreach ($step->getDependencies() as $dep) {
                if (! $context->has($dep)) {
                    $results[$stepName] = StepResult::skipped("Missing dependency: {$dep}");

                    continue 2;
                }
            }

            $result = $step->execute($context);
            $results[$stepName] = $result;

            if ($result->isSuccess()) {
                $context->markStepCompleted($stepName);
            } elseif ($result->isFailed()) {
                $hasFailure = true;
                $context->markStepFailed($stepName, $result->message ?? 'Unknown error');

                if ($this->stopOnFailure) {
                    return StepResult::failed(
                        "Step '{$stepName}' failed: ".($result->message ?? 'Unknown error'),
                        $result->exception,
                        ['step_results' => $results]
                    );
                }
            } elseif ($result->shouldPause()) {
                $isPaused = true;

                return StepResult::waiting(
                    $result->message ?? "Workflow paused at step: {$stepName}",
                    ['paused_at' => $stepName, 'step_result' => $result],
                    ['step_results' => $results]
                );
            }
        }

        if ($hasFailure) {
            return StepResult::failed(
                'One or more steps failed',
                metadata: ['step_results' => $results]
            );
        }

        return StepResult::success(
            $results,
            ['steps_completed' => count($results)]
        );
    }

    /**
     * Get the pattern name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the pattern name.
     */
    public function as(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the output key.
     */
    public function getOutputKey(): ?string
    {
        return null;
    }

    /**
     * Check if retryable.
     */
    public function isRetryable(): bool
    {
        return true;
    }

    /**
     * Get max retries.
     */
    public function getMaxRetries(): int
    {
        return 3;
    }

    /**
     * Get timeout.
     */
    public function getTimeout(): ?int
    {
        return null;
    }

    /**
     * Check if requires human approval.
     */
    public function requiresHumanApproval(): bool
    {
        return false;
    }

    /**
     * Get dependencies.
     *
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return [];
    }
}
