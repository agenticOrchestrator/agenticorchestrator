<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Agents\Concerns;

use AgenticOrchestrator\Tracking\UsageRecord;
use AgenticOrchestrator\Tracking\UsageTracker;

/**
 * Tracks Usage - Trait for automatic usage tracking in agents.
 */
trait TracksUsage
{
    /**
     * Whether usage tracking is enabled.
     */
    protected bool $trackingEnabled = true;

    /**
     * The usage tracker instance.
     */
    protected ?UsageTracker $usageTracker = null;

    /**
     * Last usage record from a request.
     */
    protected ?UsageRecord $lastUsageRecord = null;

    /**
     * Enable usage tracking.
     */
    public function enableTracking(): static
    {
        $this->trackingEnabled = true;

        return $this;
    }

    /**
     * Disable usage tracking.
     */
    public function disableTracking(): static
    {
        $this->trackingEnabled = false;

        return $this;
    }

    /**
     * Set the usage tracker.
     */
    public function setUsageTracker(UsageTracker $tracker): static
    {
        $this->usageTracker = $tracker;

        return $this;
    }

    /**
     * Get the usage tracker.
     */
    public function getUsageTracker(): UsageTracker
    {
        if ($this->usageTracker === null) {
            $this->usageTracker = app(UsageTracker::class);
        }

        return $this->usageTracker;
    }

    /**
     * Track usage for a request.
     */
    protected function trackUsage(
        string $model,
        int $inputTokens,
        int $outputTokens,
        float $latencyMs,
        array $metadata = [],
    ): ?UsageRecord {
        if (! $this->trackingEnabled) {
            return null;
        }

        $teamId = null;
        $userId = null;

        // Get team ID if available
        if (method_exists($this, 'getCurrentTeamId')) {
            $teamId = $this->getCurrentTeamId();
        } elseif (property_exists($this, 'teamId')) {
            $teamId = $this->teamId;
        }

        // Get user ID from auth
        $userId = auth()->id();

        $this->lastUsageRecord = $this->getUsageTracker()->track(
            agentClass: static::class,
            model: $model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            latencyMs: $latencyMs,
            teamId: $teamId,
            userId: $userId,
            metadata: $metadata,
        );

        return $this->lastUsageRecord;
    }

    /**
     * Get the last usage record.
     */
    public function getLastUsageRecord(): ?UsageRecord
    {
        return $this->lastUsageRecord;
    }

    /**
     * Get total cost from last request.
     */
    public function getLastRequestCost(): float
    {
        return $this->lastUsageRecord?->cost ?? 0.0;
    }

    /**
     * Get total tokens from last request.
     */
    public function getLastRequestTokens(): int
    {
        return $this->lastUsageRecord?->totalTokens() ?? 0;
    }

    /**
     * Get latency from last request.
     */
    public function getLastRequestLatency(): float
    {
        return $this->lastUsageRecord?->latencyMs ?? 0.0;
    }
}
