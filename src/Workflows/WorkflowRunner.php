<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows;

use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Contracts\WorkflowInterface;
use AgenticOrchestrator\MultiTenancy\TenantManager;
use AgenticOrchestrator\Workflows\Events\WorkflowCompleted;
use AgenticOrchestrator\Workflows\Events\WorkflowFailed;
use AgenticOrchestrator\Workflows\Events\WorkflowPaused;
use AgenticOrchestrator\Workflows\Events\WorkflowStarted;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use Throwable;

/**
 * Workflow Runner - Workflow execution engine.
 *
 * Executes workflow definitions with support for:
 * - Sequential and parallel execution
 * - State persistence and resumption
 * - Human-in-the-loop approval
 * - Multi-tenancy scoping
 * - Event dispatching
 */
class WorkflowRunner
{
    /**
     * Workflow configuration.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * Event dispatcher.
     */
    protected ?Dispatcher $events = null;

    /**
     * Create a new workflow runner instance.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected Container $container,
        array $config,
    ) {
        $this->config = array_merge([
            'max_steps' => 50,
            'step_timeout' => 300,
            'persistence' => true,
        ], $config);

        if ($this->container->bound(Dispatcher::class)) {
            $this->events = $this->container->make(Dispatcher::class);
        }
    }

    /**
     * Run a workflow or definition.
     *
     * @param  WorkflowInterface|WorkflowDefinition|class-string<WorkflowInterface>  $workflow
     * @param  array<string, mixed>  $input
     */
    public function run(
        WorkflowInterface|WorkflowDefinition|string $workflow,
        array $input = [],
    ): WorkflowResult {
        $executionId = Str::uuid()->toString();
        $startTime = microtime(true);

        // Create context
        $context = new WorkflowContext($input, [
            'execution_id' => $executionId,
            'started_at' => now()->toISOString(),
        ]);

        // Apply tenant scope
        $this->applyTenantScope($context);

        try {
            // Resolve the executable steps
            $steps = $this->resolveSteps($workflow);

            // Dispatch started event
            $this->dispatchEvent(new WorkflowStarted(
                executionId: $executionId,
                workflowName: $this->getWorkflowName($workflow),
                input: $input,
                tenant: $context->getTenant(),
            ));

            // Execute steps
            $result = $this->executeSteps($steps, $context);

            $duration = (microtime(true) - $startTime) * 1000;

            // Create workflow result
            $workflowResult = new WorkflowResult(
                executionId: $executionId,
                status: $result->status,
                output: $result->output,
                context: $context,
                duration: $duration,
                metadata: [
                    'steps_completed' => count($context->getCompletedSteps()),
                    'steps_failed' => count($context->getFailedSteps()),
                ],
            );

            // Dispatch completion events
            if ($result->isSuccess()) {
                $this->dispatchEvent(new WorkflowCompleted(
                    executionId: $executionId,
                    output: $result->output,
                    duration: $duration,
                ));
            } elseif ($result->shouldPause()) {
                $this->dispatchEvent(new WorkflowPaused(
                    executionId: $executionId,
                    pausedAt: $result->getMeta('paused_at'),
                    reason: $result->message,
                    state: $context->getState(),
                ));
            } else {
                $this->dispatchEvent(new WorkflowFailed(
                    executionId: $executionId,
                    error: $result->message ?? 'Unknown error',
                    exception: $result->exception,
                ));
            }

            return $workflowResult;
        } catch (Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;

            $this->dispatchEvent(new WorkflowFailed(
                executionId: $executionId,
                error: $e->getMessage(),
                exception: $e,
            ));

            return new WorkflowResult(
                executionId: $executionId,
                status: StepResult::STATUS_FAILED,
                output: null,
                context: $context,
                duration: $duration,
                error: $e->getMessage(),
                exception: $e,
            );
        }
    }

    /**
     * Resume a paused workflow from state.
     *
     * @param  WorkflowInterface|WorkflowDefinition|class-string<WorkflowInterface>  $workflow
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $resumeData
     */
    public function resume(
        WorkflowInterface|WorkflowDefinition|string $workflow,
        array $state,
        array $resumeData = [],
    ): WorkflowResult {
        // Restore context from state
        $context = WorkflowContext::fromState($state);

        // Merge in resume data (e.g., approval decisions)
        $context->merge($resumeData);

        // Apply tenant scope
        $this->applyTenantScope($context);

        // Get the execution ID
        $executionId = $context->getMeta('execution_id') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        try {
            // Resolve steps
            $steps = $this->resolveSteps($workflow);

            // Continue execution from where we left off
            $result = $this->executeSteps($steps, $context);

            $duration = (microtime(true) - $startTime) * 1000;

            return new WorkflowResult(
                executionId: $executionId,
                status: $result->status,
                output: $result->output,
                context: $context,
                duration: $duration,
            );
        } catch (Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;

            return new WorkflowResult(
                executionId: $executionId,
                status: StepResult::STATUS_FAILED,
                output: null,
                context: $context,
                duration: $duration,
                error: $e->getMessage(),
                exception: $e,
            );
        }
    }

    /**
     * Run a workflow from a definition class.
     *
     * @param  class-string<WorkflowInterface>  $workflowClass
     * @param  array<string, mixed>  $input
     */
    public function runWorkflow(string $workflowClass, array $input = []): WorkflowResult
    {
        $workflow = $this->resolveWorkflowClass($workflowClass);

        return $this->run($workflow->definition(), $input);
    }

    /**
     * Execute steps with the context.
     *
     * @param  array<StepInterface>  $steps
     */
    protected function executeSteps(array $steps, WorkflowContext $context): StepResult
    {
        $stepsExecuted = 0;
        $maxSteps = $this->config['max_steps'];

        foreach ($steps as $step) {
            $stepName = $step->getName();

            // Skip completed steps
            if ($context->isStepCompleted($stepName)) {
                continue;
            }

            // Check step limit
            if ($stepsExecuted >= $maxSteps) {
                return StepResult::failed("Exceeded maximum step limit ({$maxSteps})");
            }

            // Execute the step
            $result = $step->execute($context);
            $stepsExecuted++;

            if ($result->isSuccess()) {
                $context->markStepCompleted($stepName);
            } elseif ($result->isFailed()) {
                $context->markStepFailed($stepName, $result->message ?? 'Unknown error');

                return $result;
            } elseif ($result->shouldPause()) {
                // Workflow needs to pause (human approval, async, etc.)
                return $result;
            }
        }

        // All steps completed
        return StepResult::success($context->getOutputs());
    }

    /**
     * Resolve workflow to executable steps.
     *
     * @return array<StepInterface>
     */
    protected function resolveSteps(
        WorkflowInterface|WorkflowDefinition|string $workflow
    ): array {
        if ($workflow instanceof WorkflowDefinition) {
            return $workflow->getSteps();
        }

        if ($workflow instanceof WorkflowInterface) {
            $definition = $workflow->definition();

            return $definition->getSteps();
        }

        // Resolve class
        $instance = $this->resolveWorkflowClass($workflow);
        $definition = $instance->definition();

        return $definition->getSteps();
    }

    /**
     * Resolve a workflow class.
     *
     * @param  class-string<WorkflowInterface>  $workflowClass
     */
    protected function resolveWorkflowClass(string $workflowClass): WorkflowInterface
    {
        return $this->container->make($workflowClass);
    }

    /**
     * Get workflow name for logging/events.
     */
    protected function getWorkflowName(
        WorkflowInterface|WorkflowDefinition|string $workflow
    ): string {
        if (is_string($workflow)) {
            return class_basename($workflow);
        }

        if ($workflow instanceof WorkflowDefinition) {
            return $workflow->getMetadata()['name'] ?? 'anonymous';
        }

        return class_basename($workflow);
    }

    /**
     * Apply tenant scope to context.
     */
    protected function applyTenantScope(WorkflowContext $context): void
    {
        if ($context->getTenant() !== null) {
            return;
        }

        if (! $this->container->bound(TenantManager::class)) {
            return;
        }

        $tenantManager = $this->container->make(TenantManager::class);
        $currentTenant = $tenantManager->current();

        if ($currentTenant) {
            $context->setTenant($currentTenant);
        }
    }

    /**
     * Dispatch an event.
     */
    protected function dispatchEvent(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
