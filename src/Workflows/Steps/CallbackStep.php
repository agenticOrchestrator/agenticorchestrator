<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Steps;

use AgenticOrchestrator\Workflows\WorkflowContext;
use Closure;

/**
 * Callback Step - Executes a custom callback function.
 *
 * Allows arbitrary logic to be integrated into workflows.
 */
class CallbackStep extends Step
{
    /**
     * Create a new callback step.
     *
     * @param  Closure(WorkflowContext): mixed  $callback
     */
    public function __construct(
        protected Closure $callback,
    ) {}

    /**
     * Create a callback step.
     *
     * @param  Closure(WorkflowContext): mixed  $callback
     */
    public static function make(Closure $callback): static
    {
        return new static($callback);
    }

    /**
     * Execute the callback.
     */
    protected function handle(WorkflowContext $context): mixed
    {
        return ($this->callback)($context);
    }
}
