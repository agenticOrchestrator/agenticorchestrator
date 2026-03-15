<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Events;

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Workflow Started Event - Dispatched when a workflow begins execution.
 */
class WorkflowStarted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new workflow started event.
     *
     * @param  string  $executionId  Unique execution identifier
     * @param  string  $workflowName  The workflow name
     * @param  array<string, mixed>  $input  The workflow input data
     * @param  TenantInterface|null  $tenant  The tenant context
     */
    public function __construct(
        public readonly string $executionId,
        public readonly string $workflowName,
        public readonly array $input = [],
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
