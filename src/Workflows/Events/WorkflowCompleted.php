<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Workflow Completed Event - Dispatched when a workflow finishes successfully.
 */
class WorkflowCompleted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new workflow completed event.
     *
     * @param  string  $executionId  Unique execution identifier
     * @param  mixed  $output  The workflow output data
     * @param  float  $duration  Execution duration in milliseconds
     */
    public function __construct(
        public readonly string $executionId,
        public readonly mixed $output,
        public readonly float $duration,
    ) {}

    /**
     * Get the duration in seconds.
     */
    public function getDurationInSeconds(): float
    {
        return $this->duration / 1000;
    }
}
