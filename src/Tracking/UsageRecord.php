<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tracking;

use JsonSerializable;

/**
 * Usage Record - A single usage record for tracking.
 */
class UsageRecord implements JsonSerializable
{
    /**
     * Create a new usage record.
     */
    public function __construct(
        public readonly string $agentClass,
        public readonly string $model,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly float $cost,
        public readonly float $latencyMs,
        public readonly ?int $teamId = null,
        public readonly ?int $userId = null,
        public readonly ?string $requestId = null,
        public readonly array $metadata = [],
        public readonly ?\DateTimeInterface $timestamp = null,
    ) {}

    /**
     * Get total tokens.
     */
    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'agent_class' => $this->agentClass,
            'model' => $this->model,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'total_tokens' => $this->totalTokens(),
            'cost' => $this->cost,
            'latency_ms' => $this->latencyMs,
            'team_id' => $this->teamId,
            'user_id' => $this->userId,
            'request_id' => $this->requestId,
            'metadata' => $this->metadata,
            'timestamp' => $this->timestamp?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
