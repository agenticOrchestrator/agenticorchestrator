<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Workflow Paused Event - Dispatched when a workflow pauses for human approval.
 */
class WorkflowPaused
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new workflow paused event.
     *
     * @param  string  $executionId  Unique execution identifier
     * @param  string|null  $pausedAt  The step name where workflow paused
     * @param  string|null  $reason  The reason for pausing
     * @param  array<string, mixed>  $state  The workflow state for resumption
     */
    public function __construct(
        public readonly string $executionId,
        public readonly ?string $pausedAt = null,
        public readonly ?string $reason = null,
        public readonly array $state = [],
    ) {}

    /**
     * Check if the workflow has resumable state.
     */
    public function hasState(): bool
    {
        return ! empty($this->state);
    }
}
