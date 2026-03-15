<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Agents\Events;

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Agent Started Event - Dispatched when an agent begins processing a message.
 */
class AgentStarted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new agent started event.
     *
     * @param  string  $agentName  The agent name
     * @param  string  $conversationId  The conversation identifier
     * @param  string  $message  The user message being processed
     * @param  TenantInterface|null  $tenant  The tenant context
     */
    public function __construct(
        public readonly string $agentName,
        public readonly string $conversationId,
        public readonly string $message,
        public readonly ?TenantInterface $tenant = null,
    ) {}

    /**
     * Get the tenant key if available.
     */
    public function getTenantKey(): ?string
    {
        return $this->tenant?->getTenantKey();
    }
}
