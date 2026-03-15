<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\State;

use AgenticOrchestrator\Agents\Concerns\HasTeamScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * Workflow State Model - Persists workflow execution state.
 *
 * Enables workflow resumption after pause, crash recovery,
 * and workflow execution history tracking.
 *
 * @property string $id
 * @property string $execution_id
 * @property string $workflow_class
 * @property string $status
 * @property array $input
 * @property array $state
 * @property array $metadata
 * @property string|null $error
 * @property string|null $paused_at_step
 * @property float $duration_ms
 * @property string|null $tenant_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $completed_at
 */
class WorkflowState extends Model
{
    use HasTeamScope;
    use HasUuids;
    use Prunable;

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The table associated with the model.
     */
    protected $table = 'agent_workflow_states';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'execution_id',
        'workflow_class',
        'status',
        'input',
        'state',
        'metadata',
        'error',
        'paused_at_step',
        'duration_ms',
        'tenant_id',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'input' => 'array',
        'state' => 'array',
        'metadata' => 'array',
        'duration_ms' => 'float',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the prunable query.
     */
    public function prunable(): Builder
    {
        $days = config('agent-orchestrator.workflows.retention_days', 30);

        return static::query()
            ->where('status', self::STATUS_COMPLETED)
            ->where('completed_at', '<', now()->subDays($days));
    }

    /**
     * Create a new workflow state record.
     */
    public static function createForExecution(
        string $executionId,
        string $workflowClass,
        array $input = [],
        ?string $tenantId = null
    ): static {
        return static::create([
            'execution_id' => $executionId,
            'workflow_class' => $workflowClass,
            'status' => self::STATUS_PENDING,
            'input' => $input,
            'state' => [],
            'metadata' => [],
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Find by execution ID.
     */
    public static function findByExecutionId(string $executionId): ?static
    {
        return static::where('execution_id', $executionId)->first();
    }

    /**
     * Mark as running.
     */
    public function markRunning(): static
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
        ]);

        return $this;
    }

    /**
     * Mark as paused.
     */
    public function markPaused(string $pausedAtStep, array $state): static
    {
        $this->update([
            'status' => self::STATUS_PAUSED,
            'paused_at_step' => $pausedAtStep,
            'state' => $state,
        ]);

        return $this;
    }

    /**
     * Mark as completed.
     */
    public function markCompleted(array $state, float $durationMs): static
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'state' => $state,
            'duration_ms' => $durationMs,
            'completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark as failed.
     */
    public function markFailed(string $error, array $state, float $durationMs): static
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error' => $error,
            'state' => $state,
            'duration_ms' => $durationMs,
            'completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark as cancelled.
     */
    public function markCancelled(): static
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Update workflow state.
     */
    public function updateState(array $state): static
    {
        $this->update(['state' => $state]);

        return $this;
    }

    /**
     * Add metadata.
     */
    public function addMetadata(array $metadata): static
    {
        $this->update([
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);

        return $this;
    }

    /**
     * Check if workflow is resumable.
     */
    public function isResumable(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    /**
     * Check if workflow is terminal.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ]);
    }

    /**
     * Scope to pending workflows.
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to running workflows.
     */
    public function scopeRunning(Builder $query): void
    {
        $query->where('status', self::STATUS_RUNNING);
    }

    /**
     * Scope to paused workflows.
     */
    public function scopePaused(Builder $query): void
    {
        $query->where('status', self::STATUS_PAUSED);
    }

    /**
     * Scope to active (non-terminal) workflows.
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNotIn('status', [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ]);
    }
}
