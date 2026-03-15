<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Agents\Events;

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tool Executed Event - Dispatched when an agent executes a tool.
 */
class ToolExecuted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new tool executed event.
     *
     * @param  string  $agentName  The agent that executed the tool
     * @param  string  $toolName  The tool name
     * @param  array<string, mixed>  $arguments  The tool arguments
     * @param  mixed  $result  The tool execution result
     * @param  bool  $success  Whether the tool executed successfully
     * @param  float  $duration  Execution duration in milliseconds
     * @param  TenantInterface|null  $tenant  The tenant context
     */
    public function __construct(
        public readonly string $agentName,
        public readonly string $toolName,
        public readonly array $arguments,
        public readonly mixed $result,
        public readonly bool $success,
        public readonly float $duration,
        public readonly ?TenantInterface $tenant = null,
    ) {}

    /**
     * Check if the tool execution failed.
     */
    public function failed(): bool
    {
        return ! $this->success;
    }

    /**
     * Get the tenant key if available.
     */
    public function getTenantKey(): ?string
    {
        return $this->tenant?->getTenantKey();
    }
}
