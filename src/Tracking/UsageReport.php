<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tracking;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use JsonSerializable;

/**
 * Usage Report - Generates usage reports for teams and agents.
 */
class UsageReport implements JsonSerializable
{
    protected ?int $teamId = null;

    protected ?string $agentClass = null;

    protected ?DateTimeInterface $from = null;

    protected ?DateTimeInterface $to = null;

    protected string $groupBy = 'day';

    protected ?array $data = null;

    /**
     * Create a new usage report.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Create a report for a specific team.
     */
    public static function forTeam(int|object $team): static
    {
        $instance = new static;
        $instance->teamId = is_object($team) ? $team->id : $team;

        return $instance;
    }

    /**
     * Create a report for a specific agent.
     */
    public static function forAgent(string $agentClass): static
    {
        $instance = new static;
        $instance->agentClass = $agentClass;

        return $instance;
    }

    /**
     * Set date range.
     */
    public function dateRange(DateTimeInterface $from, DateTimeInterface $to): static
    {
        $this->from = $from;
        $this->to = $to;

        return $this;
    }

    /**
     * Set start date.
     */
    public function from(DateTimeInterface $from): static
    {
        $this->from = $from;

        return $this;
    }

    /**
     * Set end date.
     */
    public function to(DateTimeInterface $to): static
    {
        $this->to = $to;

        return $this;
    }

    /**
     * Group by time period.
     */
    public function groupBy(string $period): static
    {
        $this->groupBy = $period;

        return $this;
    }

    /**
     * Group by day.
     */
    public function daily(): static
    {
        return $this->groupBy('day');
    }

    /**
     * Group by week.
     */
    public function weekly(): static
    {
        return $this->groupBy('week');
    }

    /**
     * Group by month.
     */
    public function monthly(): static
    {
        return $this->groupBy('month');
    }

    /**
     * Generate the report.
     */
    public function generate(): static
    {
        $this->data = [
            'summary' => $this->generateSummary(),
            'by_agent' => $this->generateByAgent(),
            'by_model' => $this->generateByModel(),
            'timeline' => $this->generateTimeline(),
            'top_users' => $this->generateTopUsers(),
            'metadata' => [
                'team_id' => $this->teamId,
                'agent_class' => $this->agentClass,
                'from' => $this->from?->format('Y-m-d'),
                'to' => $this->to?->format('Y-m-d'),
                'group_by' => $this->groupBy,
                'generated_at' => now()->toIso8601String(),
            ],
        ];

        return $this;
    }

    /**
     * Generate summary statistics.
     */
    protected function generateSummary(): array
    {
        $query = $this->baseQuery();

        $result = $query->selectRaw('
            COUNT(*) as total_requests,
            SUM(input_tokens) as total_input_tokens,
            SUM(output_tokens) as total_output_tokens,
            SUM(cost) as total_cost,
            AVG(latency_ms) as avg_latency_ms,
            MIN(latency_ms) as min_latency_ms,
            MAX(latency_ms) as max_latency_ms,
            COUNT(DISTINCT agent_class) as unique_agents,
            COUNT(DISTINCT user_id) as unique_users
        ')->first();

        return [
            'total_requests' => (int) ($result->total_requests ?? 0),
            'total_input_tokens' => (int) ($result->total_input_tokens ?? 0),
            'total_output_tokens' => (int) ($result->total_output_tokens ?? 0),
            'total_tokens' => (int) (($result->total_input_tokens ?? 0) + ($result->total_output_tokens ?? 0)),
            'total_cost' => round((float) ($result->total_cost ?? 0), 4),
            'avg_latency_ms' => round((float) ($result->avg_latency_ms ?? 0), 2),
            'min_latency_ms' => round((float) ($result->min_latency_ms ?? 0), 2),
            'max_latency_ms' => round((float) ($result->max_latency_ms ?? 0), 2),
            'unique_agents' => (int) ($result->unique_agents ?? 0),
            'unique_users' => (int) ($result->unique_users ?? 0),
        ];
    }

    /**
     * Generate usage by agent.
     */
    protected function generateByAgent(): array
    {
        return $this->baseQuery()
            ->selectRaw('
                agent_class,
                COUNT(*) as request_count,
                SUM(input_tokens) as input_tokens,
                SUM(output_tokens) as output_tokens,
                SUM(cost) as cost,
                AVG(latency_ms) as avg_latency_ms
            ')
            ->groupBy('agent_class')
            ->orderByDesc('cost')
            ->get()
            ->map(fn ($row) => [
                'agent' => class_basename($row->agent_class),
                'agent_class' => $row->agent_class,
                'request_count' => (int) $row->request_count,
                'input_tokens' => (int) $row->input_tokens,
                'output_tokens' => (int) $row->output_tokens,
                'cost' => round((float) $row->cost, 4),
                'avg_latency_ms' => round((float) $row->avg_latency_ms, 2),
            ])
            ->toArray();
    }

    /**
     * Generate usage by model.
     */
    protected function generateByModel(): array
    {
        return $this->baseQuery()
            ->selectRaw('
                model,
                COUNT(*) as request_count,
                SUM(input_tokens) as input_tokens,
                SUM(output_tokens) as output_tokens,
                SUM(cost) as cost
            ')
            ->groupBy('model')
            ->orderByDesc('cost')
            ->get()
            ->map(fn ($row) => [
                'model' => $row->model,
                'request_count' => (int) $row->request_count,
                'input_tokens' => (int) $row->input_tokens,
                'output_tokens' => (int) $row->output_tokens,
                'cost' => round((float) $row->cost, 4),
            ])
            ->toArray();
    }

    /**
     * Generate timeline data.
     */
    protected function generateTimeline(): array
    {
        $dateFormat = match ($this->groupBy) {
            'hour' => '%Y-%m-%d %H:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%W',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        return $this->baseQuery()
            ->selectRaw("
                DATE_FORMAT(created_at, '{$dateFormat}') as period,
                COUNT(*) as request_count,
                SUM(input_tokens) as input_tokens,
                SUM(output_tokens) as output_tokens,
                SUM(cost) as cost
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'period' => $row->period,
                'request_count' => (int) $row->request_count,
                'tokens' => (int) ($row->input_tokens + $row->output_tokens),
                'cost' => round((float) $row->cost, 4),
            ])
            ->toArray();
    }

    /**
     * Generate top users.
     */
    protected function generateTopUsers(): array
    {
        return $this->baseQuery()
            ->whereNotNull('user_id')
            ->selectRaw('
                user_id,
                COUNT(*) as request_count,
                SUM(cost) as cost
            ')
            ->groupBy('user_id')
            ->orderByDesc('cost')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'user_id' => (int) $row->user_id,
                'request_count' => (int) $row->request_count,
                'cost' => round((float) $row->cost, 4),
            ])
            ->toArray();
    }

    /**
     * Get base query with filters.
     */
    protected function baseQuery()
    {
        $query = DB::table('agent_usage_logs');

        if ($this->teamId) {
            $query->where('team_id', $this->teamId);
        }

        if ($this->agentClass) {
            $query->where('agent_class', $this->agentClass);
        }

        if ($this->from) {
            $query->where('created_at', '>=', $this->from);
        }

        if ($this->to) {
            $query->where('created_at', '<=', $this->to);
        }

        return $query;
    }

    /**
     * Get total cost from report.
     */
    public function totalCost(): float
    {
        return $this->data['summary']['total_cost'] ?? 0.0;
    }

    /**
     * Get total tokens from report.
     */
    public function totalTokens(): int
    {
        return $this->data['summary']['total_tokens'] ?? 0;
    }

    /**
     * Get total requests from report.
     */
    public function totalRequests(): int
    {
        return $this->data['summary']['total_requests'] ?? 0;
    }

    /**
     * Get summary data.
     */
    public function summary(): array
    {
        return $this->data['summary'] ?? [];
    }

    /**
     * Get usage by agent.
     */
    public function byAgent(): array
    {
        return $this->data['by_agent'] ?? [];
    }

    /**
     * Get usage by model.
     */
    public function byModel(): array
    {
        return $this->data['by_model'] ?? [];
    }

    /**
     * Get timeline data.
     */
    public function timeline(): array
    {
        return $this->data['timeline'] ?? [];
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        if ($this->data === null) {
            $this->generate();
        }

        return $this->data;
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
