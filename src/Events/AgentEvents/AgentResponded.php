<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Events\AgentEvents;

use AgenticOrchestrator\Agents\AgentResponse;
use AgenticOrchestrator\Contracts\AgentInterface;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an agent completes a response.
 */
class AgentResponded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly AgentInterface $agent,
        public readonly AgentResponse $response,
    ) {}
}
