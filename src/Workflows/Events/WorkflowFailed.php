<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Workflow Failed Event - Dispatched when a workflow fails.
 */
class WorkflowFailed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new workflow failed event.
     *
     * @param  string  $executionId  Unique execution identifier
     * @param  string  $error  The error message
     * @param  Throwable|null  $exception  The exception if available
     */
    public function __construct(
        public readonly string $executionId,
        public readonly string $error,
        public readonly ?Throwable $exception = null,
    ) {}

    /**
     * Get the exception class name if available.
     */
    public function getExceptionClass(): ?string
    {
        return $this->exception ? get_class($this->exception) : null;
    }

    /**
     * Get the exception trace if available.
     */
    public function getExceptionTrace(): ?string
    {
        return $this->exception?->getTraceAsString();
    }
}
