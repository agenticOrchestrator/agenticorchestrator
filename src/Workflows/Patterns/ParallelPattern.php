<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Patterns;

use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Parallel Pattern - Executes steps concurrently.
 *
 * All steps are executed in parallel (using PHP's concurrent execution),
 * and the pattern waits for all to complete before continuing.
 */
class ParallelPattern implements StepInterface
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
    protected string $name = 'parallel';

    /**
     * How many failures are acceptable.
     */
    protected int $failureThreshold = 0;

    /**
     * Maximum concurrent executions.
     */
    protected int $maxConcurrency = 10;

    /**
     * Whether to wait for all steps or just the first success.
     */
    protected bool $waitForAll = true;

    /**
     * Create a new parallel pattern.
     *
     * @param  array<StepInterface>  $steps
     */
    public function __construct(array $steps = [])
    {
        $this->steps = $steps;
    }

    /**
     * Create a parallel pattern.
     *
     * @param  array<StepInterface>  $steps
     */
    public static function make(array $steps = []): static
    {
        return new static($steps);
    }

    /**
     * Add a step to the parallel execution.
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
     * Set the failure threshold.
     */
    public function allowFailures(int $count): static
    {
        $this->failureThreshold = $count;

        return $this;
    }

    /**
     * Set maximum concurrency.
     */
    public function maxConcurrency(int $max): static
    {
        $this->maxConcurrency = $max;

        return $this;
    }

    /**
     * Return on first success (race mode).
     */
    public function race(): static
    {
        $this->waitForAll = false;

        return $this;
    }

    /**
     * Execute all steps in parallel.
     *
     * Note: True parallelism requires PHP extensions like parallel or amphp.
     * This implementation uses synchronous execution with parallel semantics.
     * For true async execution, override with async driver.
     */
    public function execute(WorkflowContext $context): StepResult
    {
        if (empty($this->steps)) {
            return StepResult::success([]);
        }

        $results = [];
        $failures = 0;
        $successes = 0;

        // In a real parallel implementation, use Fibers, ReactPHP, or amphp
        // This implementation processes sequentially but with parallel semantics
        foreach ($this->steps as $step) {
            $stepName = $step->getName();

            // Skip already completed steps (for resumption)
            if ($context->isStepCompleted($stepName)) {
                $successes++;

                continue;
            }

            try {
                $result = $step->execute($context);
                $results[$stepName] = $result;

                if ($result->isSuccess()) {
                    $successes++;
                    $context->markStepCompleted($stepName);

                    // Race mode: return on first success
                    if (! $this->waitForAll) {
                        return StepResult::success(
                            [$stepName => $result->output],
                            ['winner' => $stepName, 'partial_results' => $results]
                        );
                    }
                } elseif ($result->isFailed()) {
                    $failures++;
                    $context->markStepFailed($stepName, $result->message ?? 'Unknown error');
                } elseif ($result->shouldPause()) {
                    // A step is waiting for approval - pause entire parallel execution
                    return StepResult::waiting(
                        "Parallel step '{$stepName}' requires approval",
                        ['paused_at' => $stepName, 'step_result' => $result],
                        ['partial_results' => $results]
                    );
                }
            } catch (Throwable $e) {
                $failures++;
                $results[$stepName] = StepResult::failed($e->getMessage(), $e);
                $context->markStepFailed($stepName, $e->getMessage(), get_class($e));

                Log::error("Parallel step '{$stepName}' threw exception", [
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        // Check failure threshold
        if ($failures > $this->failureThreshold) {
            return StepResult::failed(
                "Too many parallel step failures ({$failures} > {$this->failureThreshold})",
                metadata: ['step_results' => $results, 'failures' => $failures, 'successes' => $successes]
            );
        }

        // Collect successful outputs
        $outputs = [];
        foreach ($results as $stepName => $result) {
            if ($result instanceof StepResult && $result->isSuccess()) {
                $outputs[$stepName] = $result->output;
            }
        }

        return StepResult::success(
            $outputs,
            [
                'steps_completed' => $successes,
                'steps_failed' => $failures,
                'step_results' => $results,
            ]
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
