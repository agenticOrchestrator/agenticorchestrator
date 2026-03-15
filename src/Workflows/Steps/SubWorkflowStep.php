<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Steps;

use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use Closure;

/**
 * Sub-Workflow Step - Executes a nested workflow within a parent workflow.
 *
 * Maps input from parent context to child context and
 * maps output from child context back to parent.
 */
class SubWorkflowStep extends Step
{
    /**
     * The workflow step(s) to execute as a sub-workflow.
     */
    protected StepInterface $workflow;

    /**
     * Input mapping from parent context keys to child context keys.
     *
     * @var array<string, string>|Closure(WorkflowContext): array<string, mixed>|null
     */
    protected array|Closure|null $inputMapping = null;

    /**
     * Output mapping from child result to parent context keys.
     *
     * @var array<string, string>|Closure(StepResult, WorkflowContext): void|null
     */
    protected array|Closure|null $outputMapping = null;

    /**
     * Whether to isolate child context from parent.
     */
    protected bool $isolated = false;

    /**
     * Create a new sub-workflow step.
     */
    public function __construct(StepInterface $workflow)
    {
        $this->workflow = $workflow;
    }

    /**
     * Create a sub-workflow step.
     */
    public static function make(StepInterface $workflow): static
    {
        return new static($workflow);
    }

    /**
     * Map parent context keys to child context keys.
     *
     * @param  array<string, string>|Closure(WorkflowContext): array<string, mixed>  $mapping
     */
    public function mapInput(array|Closure $mapping): static
    {
        $this->inputMapping = $mapping;

        return $this;
    }

    /**
     * Map child output to parent context.
     *
     * @param  array<string, string>|Closure(StepResult, WorkflowContext): void  $mapping
     */
    public function mapOutput(array|Closure $mapping): static
    {
        $this->outputMapping = $mapping;

        return $this;
    }

    /**
     * Run the sub-workflow in an isolated context.
     */
    public function isolated(): static
    {
        $this->isolated = true;

        return $this;
    }

    /**
     * Execute the sub-workflow.
     */
    protected function handle(WorkflowContext $context): mixed
    {
        // Build child context
        $childContext = $this->buildChildContext($context);

        // Execute the sub-workflow
        $result = $this->workflow->execute($childContext);

        // Map output back to parent
        if ($result->isSuccess() && $this->outputMapping !== null) {
            $this->applyOutputMapping($result, $context);
        }

        return $result;
    }

    /**
     * Build the child workflow context.
     */
    protected function buildChildContext(WorkflowContext $parentContext): WorkflowContext
    {
        if ($this->isolated) {
            $childContext = new WorkflowContext;
        } else {
            $childContext = new WorkflowContext(
                input: $parentContext->getInput(),
                metadata: $parentContext->getMetadata(),
            );

            // Copy data into child context
            foreach ($parentContext->getData() as $key => $value) {
                $childContext->set($key, $value);
            }
        }

        // Apply input mapping
        if ($this->inputMapping !== null) {
            if ($this->inputMapping instanceof Closure) {
                $mapped = ($this->inputMapping)($parentContext);
                foreach ($mapped as $key => $value) {
                    $childContext->set($key, $value);
                }
            } else {
                foreach ($this->inputMapping as $parentKey => $childKey) {
                    if ($parentContext->has($parentKey)) {
                        $childContext->set($childKey, $parentContext->get($parentKey));
                    }
                }
            }
        }

        // Preserve tenant and user
        if ($parentContext->getTenant() !== null) {
            $childContext->setTenant($parentContext->getTenant());
        }

        if ($parentContext->getUser() !== null) {
            $childContext->setUser($parentContext->getUser());
        }

        return $childContext;
    }

    /**
     * Apply output mapping from child result to parent context.
     */
    protected function applyOutputMapping(StepResult $result, WorkflowContext $parentContext): void
    {
        if ($this->outputMapping instanceof Closure) {
            ($this->outputMapping)($result, $parentContext);
        } elseif (is_array($this->outputMapping) && is_array($result->output)) {
            foreach ($this->outputMapping as $childKey => $parentKey) {
                if (isset($result->output[$childKey])) {
                    $parentContext->set($parentKey, $result->output[$childKey]);
                }
            }
        }
    }
}
