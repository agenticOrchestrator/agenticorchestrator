<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows;

use AgenticOrchestrator\Contracts\AgentInterface;
use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\Patterns\ParallelPattern;
use AgenticOrchestrator\Workflows\Patterns\SequentialPattern;
use AgenticOrchestrator\Workflows\Patterns\SupervisorPattern;
use AgenticOrchestrator\Workflows\Steps\AgentStep;
use AgenticOrchestrator\Workflows\Steps\CallbackStep;
use AgenticOrchestrator\Workflows\Steps\ConditionalStep;
use AgenticOrchestrator\Workflows\Steps\HumanApprovalStep;
use Closure;

/**
 * Workflow Definition - Fluent workflow step builder.
 *
 * Provides a declarative API for defining workflow steps,
 * patterns, and conditions.
 */
class WorkflowDefinition
{
    /**
     * The workflow steps in order.
     *
     * @var array<StepInterface>
     */
    protected array $steps = [];

    /**
     * Named steps for reference.
     *
     * @var array<string, StepInterface>
     */
    protected array $namedSteps = [];

    /**
     * Step dependencies.
     *
     * @var array<string, array<string>>
     */
    protected array $dependencies = [];

    /**
     * Workflow metadata.
     *
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    /**
     * Create a new workflow definition.
     */
    public static function create(): static
    {
        return new static;
    }

    /**
     * Add a step to the workflow.
     */
    public function addStep(string|StepInterface $nameOrStep, ?StepInterface $step = null): static
    {
        if ($nameOrStep instanceof StepInterface) {
            $this->steps[] = $nameOrStep;
            $name = $nameOrStep->getName();
        } else {
            if ($step === null) {
                throw new \InvalidArgumentException('Step instance required when name is provided');
            }
            $this->steps[] = $step;
            $name = $nameOrStep;
        }

        $actualStep = $step ?? $nameOrStep;
        $this->namedSteps[$name] = $actualStep;

        return $this;
    }

    /**
     * Add an agent step.
     */
    public function agent(
        string $name,
        AgentInterface|string $agent,
        string|Closure $message
    ): static {
        $step = AgentStep::make($agent, $message)->as($name);
        $this->addStep($name, $step);

        return $this;
    }

    /**
     * Add a callback step.
     *
     * @param  Closure(WorkflowContext): mixed  $callback
     */
    public function callback(string $name, Closure $callback): static
    {
        $step = CallbackStep::make($callback)->as($name);
        $this->addStep($name, $step);

        return $this;
    }

    /**
     * Add a human approval step.
     */
    public function approval(string $name, string|Closure $prompt): static
    {
        $step = HumanApprovalStep::make($prompt)->as($name);
        $this->addStep($name, $step);

        return $this;
    }

    /**
     * Add a conditional step.
     *
     * @param  Closure(WorkflowContext): bool  $condition
     */
    public function when(
        string $name,
        Closure $condition,
        StepInterface $thenStep,
        ?StepInterface $elseStep = null
    ): static {
        $step = ConditionalStep::when($condition, $thenStep);

        if ($elseStep) {
            $step->otherwise($elseStep);
        }

        $step->as($name);
        $this->addStep($name, $step);

        return $this;
    }

    /**
     * Add parallel steps.
     *
     * @param  array<StepInterface>  $steps
     */
    public function parallel(string $name, array $steps): static
    {
        $pattern = ParallelPattern::make($steps)->as($name);
        $this->addStep($name, $pattern);

        return $this;
    }

    /**
     * Add sequential steps as a group.
     *
     * @param  array<StepInterface>  $steps
     */
    public function sequential(string $name, array $steps): static
    {
        $pattern = SequentialPattern::make($steps)->as($name);
        $this->addStep($name, $pattern);

        return $this;
    }

    /**
     * Add a supervisor pattern.
     *
     * @param  array<string, AgentInterface|string>  $workers
     */
    public function supervisor(
        string $name,
        AgentInterface|string $supervisor,
        array $workers
    ): static {
        $pattern = SupervisorPattern::make($supervisor, $workers)->as($name);
        $this->addStep($name, $pattern);

        return $this;
    }

    /**
     * Define step dependencies.
     *
     * @param  array<string>  $dependencies
     */
    public function after(string $stepName, array $dependencies): static
    {
        $this->dependencies[$stepName] = $dependencies;

        return $this;
    }

    /**
     * Define that a step outputs to a key.
     */
    public function output(string $stepName, string $outputKey): static
    {
        if (isset($this->namedSteps[$stepName])) {
            $step = $this->namedSteps[$stepName];

            if (method_exists($step, 'outputAs')) {
                $step->outputAs($outputKey);
            }
        }

        return $this;
    }

    /**
     * Set workflow metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): static
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * Set the workflow name.
     */
    public function name(string $name): static
    {
        $this->metadata['name'] = $name;

        return $this;
    }

    /**
     * Set the workflow description.
     */
    public function description(string $description): static
    {
        $this->metadata['description'] = $description;

        return $this;
    }

    /**
     * Get all steps.
     *
     * @return array<StepInterface>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Get named steps.
     *
     * @return array<string, StepInterface>
     */
    public function getNamedSteps(): array
    {
        return $this->namedSteps;
    }

    /**
     * Get step dependencies.
     *
     * @return array<string, array<string>>
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * Get metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Check if a step exists.
     */
    public function hasStep(string $name): bool
    {
        return isset($this->namedSteps[$name]);
    }

    /**
     * Get a specific step by name.
     */
    public function getStep(string $name): ?StepInterface
    {
        return $this->namedSteps[$name] ?? null;
    }

    /**
     * Get step count.
     */
    public function count(): int
    {
        return count($this->steps);
    }

    /**
     * Build a sequential pattern from all steps.
     */
    public function build(): SequentialPattern
    {
        return SequentialPattern::make($this->steps);
    }
}
