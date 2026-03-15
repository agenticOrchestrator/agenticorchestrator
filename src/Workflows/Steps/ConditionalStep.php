<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Steps;

use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use Closure;

/**
 * Conditional Step - Executes a step only if a condition is met.
 *
 * Supports if/else branching in workflows.
 */
class ConditionalStep extends Step
{
    /**
     * The condition to evaluate.
     *
     * @var Closure(WorkflowContext): bool
     */
    protected Closure $condition;

    /**
     * Step to execute if condition is true.
     */
    protected StepInterface $thenStep;

    /**
     * Step to execute if condition is false (optional).
     */
    protected ?StepInterface $elseStep = null;

    /**
     * Create a new conditional step.
     *
     * @param  Closure(WorkflowContext): bool  $condition
     */
    public function __construct(Closure $condition, StepInterface $thenStep)
    {
        $this->condition = $condition;
        $this->thenStep = $thenStep;
    }

    /**
     * Create a conditional step.
     *
     * @param  Closure(WorkflowContext): bool  $condition
     */
    public static function when(Closure $condition, StepInterface $thenStep): static
    {
        return new static($condition, $thenStep);
    }

    /**
     * Create a conditional step based on a context key.
     */
    public static function ifHas(string $key, StepInterface $thenStep): static
    {
        return new static(
            fn (WorkflowContext $ctx) => $ctx->has($key),
            $thenStep
        );
    }

    /**
     * Create a conditional step based on a context value.
     */
    public static function ifEquals(string $key, mixed $value, StepInterface $thenStep): static
    {
        return new static(
            fn (WorkflowContext $ctx) => $ctx->get($key) === $value,
            $thenStep
        );
    }

    /**
     * Set the else branch.
     */
    public function otherwise(StepInterface $step): static
    {
        $this->elseStep = $step;

        return $this;
    }

    /**
     * Execute the conditional logic.
     */
    protected function handle(WorkflowContext $context): mixed
    {
        $conditionMet = ($this->condition)($context);

        if ($conditionMet) {
            return $this->thenStep->execute($context);
        }

        if ($this->elseStep !== null) {
            return $this->elseStep->execute($context);
        }

        return StepResult::skipped('Condition not met and no else branch');
    }

    /**
     * Get the output key (from the active step).
     */
    public function getOutputKey(): ?string
    {
        // Conditional step doesn't have its own output key
        // The inner steps handle their own output
        return null;
    }
}
