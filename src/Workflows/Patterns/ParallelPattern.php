<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Patterns;

use AgenticOrchestrator\Contracts\ParallelDriverInterface;
use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\Patterns\Drivers\SyncParallelDriver;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;

/**
 * Parallel Pattern - Executes independent branches with parallel semantics.
 *
 * The pattern describes *what* runs in parallel; a {@see ParallelDriverInterface}
 * decides *how*. The default {@see SyncParallelDriver} runs branches in-process
 * (sequentially, but with race mode, failure thresholds, and result merging).
 * The {@see Drivers\QueueParallelDriver} fans branches out across queue workers
 * for true concurrency.
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
     * The driver used to execute the branches (defaults to synchronous).
     */
    protected ?ParallelDriverInterface $driver = null;

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
     * Execute all branches through the configured driver.
     *
     * Defaults to the {@see SyncParallelDriver}. Call {@see useDriver()} or use
     * WorkflowDefinition::parallelQueued() to fan branches out across workers.
     */
    public function execute(WorkflowContext $context): StepResult
    {
        return $this->resolveDriver()->run($this->steps, $context, $this->options());
    }

    /**
     * Set the driver used to execute the branches.
     */
    public function useDriver(ParallelDriverInterface $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    /**
     * Resolve the execution driver, defaulting to synchronous in-process.
     */
    protected function resolveDriver(): ParallelDriverInterface
    {
        return $this->driver ??= new SyncParallelDriver;
    }

    /**
     * Build the immutable options snapshot handed to the driver.
     */
    protected function options(): ParallelOptions
    {
        return new ParallelOptions(
            name: $this->name,
            failureThreshold: $this->failureThreshold,
            waitForAll: $this->waitForAll,
            maxConcurrency: $this->maxConcurrency,
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
