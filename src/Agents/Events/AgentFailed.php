<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Agents\Events;

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Agent Failed Event - Dispatched when an agent encounters an error.
 */
class AgentFailed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new agent failed event.
     *
     * @param  string  $agentName  The agent name
     * @param  string  $conversationId  The conversation identifier
     * @param  Throwable  $exception  The exception that caused the failure
     * @param  string  $message  The message being processed when failure occurred
     * @param  TenantInterface|null  $tenant  The tenant context
     */
    public function __construct(
        public readonly string $agentName,
        public readonly string $conversationId,
        public readonly Throwable $exception,
        public readonly string $message,
        public readonly ?TenantInterface $tenant = null,
    ) {}

    /**
     * Get the error message.
     */
    public function getErrorMessage(): string
    {
        return $this->exception->getMessage();
    }

    /**
     * Get the exception class name.
     */
    public function getExceptionType(): string
    {
        return get_class($this->exception);
    }

    /**
     * Get the tenant key if available.
     */
    public function getTenantKey(): ?string
    {
        return $this->tenant?->getTenantKey();
    }
}
