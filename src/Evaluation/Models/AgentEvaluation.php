<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation\Models;

use AgenticOrchestrator\Evaluation\EvaluationResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Agent Evaluation - Stores evaluation results in the database.
 */
class AgentEvaluation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * The table associated with the model.
     */
    protected $table = 'agent_evaluations';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'evaluation_id',
        'suite_class',
        'agent_class',
        'team_id',
        'status',
        'total_cases',
        'passed_cases',
        'failed_cases',
        'error_cases',
        'pass_rate',
        'average_score',
        'metric_scores',
        'results',
        'duration_ms',
        'metadata',
        'started_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'team_id' => 'integer',
        'total_cases' => 'integer',
        'passed_cases' => 'integer',
        'failed_cases' => 'integer',
        'error_cases' => 'integer',
        'pass_rate' => 'float',
        'average_score' => 'float',
        'metric_scores' => 'array',
        'results' => 'array',
        'duration_ms' => 'float',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Create a new evaluation record for starting an evaluation.
     */
    public static function createForEvaluation(
        string $suiteClass,
        string $agentClass,
        ?int $teamId = null,
    ): static {
        return static::create([
            'evaluation_id' => (string) Str::uuid(),
            'suite_class' => $suiteClass,
            'agent_class' => $agentClass,
            'team_id' => $teamId,
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the evaluation as completed with results.
     */
    public function markCompleted(EvaluationResult $result): static
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'total_cases' => $result->total(),
            'passed_cases' => $result->passedCount(),
            'failed_cases' => $result->failedCount(),
            'error_cases' => $result->errorCount(),
            'pass_rate' => $result->passRate(),
            'average_score' => $result->averageMetricScore(),
            'metric_scores' => $result->averageMetricsByName(),
            'results' => $result->toArray(),
            'duration_ms' => $result->totalDurationMs,
            'completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark the evaluation as failed.
     */
    public function markFailed(string $error): static
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'metadata' => array_merge($this->metadata ?? [], ['error' => $error]),
            'completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Scope to filter by agent class.
     */
    public function scopeForAgent($query, string $agentClass)
    {
        return $query->where('agent_class', $agentClass);
    }

    /**
     * Scope to filter by team.
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get completed evaluations only.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Check if the evaluation passed (100% pass rate).
     */
    public function passed(): bool
    {
        return $this->pass_rate >= 100.0;
    }

    /**
     * Get the duration in a human-readable format.
     */
    public function getDurationAttribute(): string
    {
        if ($this->duration_ms < 1000) {
            return round($this->duration_ms).'ms';
        }

        return round($this->duration_ms / 1000, 2).'s';
    }
}
