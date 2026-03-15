<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Step Completed Event - Dispatched when a workflow step finishes successfully.
 */
class StepCompleted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new step completed event.
     *
     * @param  string  $executionId  Workflow execution identifier
     * @param  string  $stepName  The step name
     * @param  mixed  $output  The step output
     * @param  float  $duration  Step duration in milliseconds
     */
    public function __construct(
        public readonly string $executionId,
        public readonly string $stepName,
        public readonly mixed $output,
        public readonly float $duration,
    ) {}
}
