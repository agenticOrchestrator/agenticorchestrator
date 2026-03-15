<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Events\AgentEvents;

use AgenticOrchestrator\Contracts\AgentInterface;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an agent encounters an error.
 */
class AgentFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly AgentInterface $agent,
        public readonly \Throwable $exception,
    ) {}
}
