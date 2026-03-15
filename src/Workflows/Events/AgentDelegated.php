<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Agent Delegated Event - Dispatched when an agent delegates to another.
 */
class AgentDelegated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new agent delegated event.
     *
     * @param  string  $fromAgent  The delegating agent's name
     * @param  string  $toAgent  The target agent's name
     * @param  string  $message  The delegated message/task
     * @param  int  $depth  The delegation depth
     */
    public function __construct(
        public readonly string $fromAgent,
        public readonly string $toAgent,
        public readonly string $message,
        public readonly int $depth = 1,
    ) {}
}
