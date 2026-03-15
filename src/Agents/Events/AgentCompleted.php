<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Agents\Events;

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Agent Completed Event - Dispatched when an agent finishes processing.
 */
class AgentCompleted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new agent completed event.
     *
     * @param  string  $agentName  The agent name
     * @param  string  $conversationId  The conversation identifier
     * @param  string  $response  The agent's response content
     * @param  int  $inputTokens  Number of input tokens used
     * @param  int  $outputTokens  Number of output tokens used
     * @param  float  $duration  Processing duration in milliseconds
     * @param  TenantInterface|null  $tenant  The tenant context
     */
    public function __construct(
        public readonly string $agentName,
        public readonly string $conversationId,
        public readonly string $response,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly float $duration,
        public readonly ?TenantInterface $tenant = null,
    ) {}

    /**
     * Get the total tokens used.
     */
    public function getTotalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /**
     * Get the tenant key if available.
     */
    public function getTenantKey(): ?string
    {
        return $this->tenant?->getTenantKey();
    }
}
