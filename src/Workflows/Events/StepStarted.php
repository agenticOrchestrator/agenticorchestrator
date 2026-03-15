<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Step Started Event - Dispatched when a workflow step begins execution.
 */
class StepStarted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new step started event.
     *
     * @param  string  $executionId  Workflow execution identifier
     * @param  string  $stepName  The step name
     * @param  int  $stepIndex  The step index in the workflow
     */
    public function __construct(
        public readonly string $executionId,
        public readonly string $stepName,
        public readonly int $stepIndex = 0,
    ) {}
}
