<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Step Failed Event - Dispatched when a workflow step fails.
 */
class StepFailed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new step failed event.
     *
     * @param  string  $executionId  Workflow execution identifier
     * @param  string  $stepName  The step name
     * @param  string  $error  The error message
     * @param  Throwable|null  $exception  The exception if available
     */
    public function __construct(
        public readonly string $executionId,
        public readonly string $stepName,
        public readonly string $error,
        public readonly ?Throwable $exception = null,
    ) {}
}
