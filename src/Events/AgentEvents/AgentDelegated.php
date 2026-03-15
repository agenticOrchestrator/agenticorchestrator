<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Events\AgentEvents;

use AgenticOrchestrator\Contracts\AgentInterface;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an agent delegates a task to another agent.
 */
class AgentDelegated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly AgentInterface $fromAgent,
        public readonly AgentInterface $toAgent,
        public readonly string $task,
    ) {}
}
