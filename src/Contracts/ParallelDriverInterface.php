<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Contracts;

use AgenticOrchestrator\Workflows\Patterns\ParallelOptions;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;

/**
 * Interface for parallel execution drivers.
 *
 * A driver decides *how* the branches of a ParallelPattern are run:
 * synchronously in-process, or fanned out across queue workers.
 * The pattern itself stays driver-agnostic.
 */
interface ParallelDriverInterface
{
    /**
     * Execute the given branch steps and aggregate their results.
     *
     * @param  array<StepInterface>  $steps  The branch steps to run
     * @param  WorkflowContext  $context  The shared workflow context
     * @param  ParallelOptions  $options  Execution options (race, failure threshold, ...)
     */
    public function run(array $steps, WorkflowContext $context, ParallelOptions $options): StepResult;
}
