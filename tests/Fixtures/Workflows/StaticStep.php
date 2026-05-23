<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Fixtures\Workflows;

use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\Steps\Step;
use AgenticOrchestrator\Workflows\WorkflowContext;

/**
 * A serializable step for queue-driver tests.
 *
 * Unlike CallbackStep it holds no closures, so it survives job serialization.
 */
class StaticStep extends Step
{
    public function __construct(
        string $name,
        protected mixed $value = null,
        protected bool $shouldFail = false,
    ) {
        $this->name = $name;
    }

    protected function handle(WorkflowContext $context): mixed
    {
        if ($this->shouldFail) {
            return StepResult::failed("{$this->getName()} failed");
        }

        return $this->value;
    }
}
