<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tracking;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Usage Tracker - Tracks agent usage for billing and analytics.
 */
class UsageTracker
{
    /**
     * In-memory buffer for batch inserts.
     *
     * @var array<UsageRecord>
     */
    protected array $buffer = [];

    /**
     * Buffer size before auto-flush.
     */
    protected int $bufferSize = 100;

    /**
     * Create a new usage tracker.
     */
    public function __construct(
        protected CostCalculator $costCalculator,
        protected ?Dispatcher $events = null,
    ) {}

    /**
     * Create a new usage tracker.
     */
    public static function make(?CostCalculator $costCalculator = null): static
    {
        return new static(
            $costCalculator ?? CostCalculator::make(),
        );
    }

    /**
     * Track a usage record.
     */
    public function track(
        string $agentClass,
        string $model,
        int $inputTokens,
        int $outputTokens,
        float $latencyMs,
        ?int $teamId = null,
        ?int $userId = null,
        array $metadata = [],
    ): UsageRecord {
        $cost = $this->costCalculator->calculate($model, $inputTokens, $outputTokens);

        $record = new UsageRecord(
            agentClass: $agentClass,
            model: $model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: $cost,
            latencyMs: $latencyMs,
            teamId: $teamId,
            userId: $userId,
            requestId: (string) Str::uuid(),
            metadata: $metadata,
            timestamp: now(),
        );

        $this->buffer[] = $record;

        // Auto-flush if buffer is full
        if (count($this->buffer) >= $this->bufferSize) {
            $this->flush();
        }

        return $record;
    }

    /**
     * Track embedding usage.
     */
    public function trackEmbedding(
        string $model,
        int $tokens,
        ?int $teamId = null,
        ?int $userId = null,
        array $metadata = [],
    ): UsageRecord {
        $cost = $this->costCalculator->calculateEmbedding($model, $tokens);

        $record = new UsageRecord(
            agentClass: 'embedding',
            model: $model,
            inputTokens: $tokens,
            outputTokens: 0,
            cost: $cost,
            latencyMs: 0,
            teamId: $teamId,
            userId: $userId,
            requestId: (string) Str::uuid(),
            metadata: array_merge($metadata, ['type' => 'embedding']),
            timestamp: now(),
        );

        $this->buffer[] = $record;

        if (count($this->buffer) >= $this->bufferSize) {
            $this->flush();
        }

        return $record;
    }

    /**
     * Flush buffer to database.
     */
    public function flush(): int
    {
        if (empty($this->buffer)) {
            return 0;
        }

        $records = $this->buffer;
        $this->buffer = [];

        try {
            $data = array_map(function (UsageRecord $record) {
                return [
                    'request_id' => $record->requestId,
                    'agent_class' => $record->agentClass,
                    'model' => $record->model,
                    'input_tokens' => $record->inputTokens,
                    'output_tokens' => $record->outputTokens,
                    'cost' => $record->cost,
                    'latency_ms' => $record->latencyMs,
                    'team_id' => $record->teamId,
                    'user_id' => $record->userId,
                    'metadata' => json_encode($record->metadata),
                    'created_at' => $record->timestamp,
                ];
            }, $records);

            DB::table('agent_usage_logs')->insert($data);

            return count($records);
        } catch (\Throwable $e) {
            // Re-add to buffer on failure
            $this->buffer = array_merge($records, $this->buffer);

            throw $e;
        }
    }

    /**
     * Set buffer size.
     */
    public function setBufferSize(int $size): static
    {
        $this->bufferSize = max(1, $size);

        return $this;
    }

    /**
     * Get buffered records.
     *
     * @return array<UsageRecord>
     */
    public function getBuffer(): array
    {
        return $this->buffer;
    }

    /**
     * Clear the buffer without flushing.
     */
    public function clearBuffer(): static
    {
        $this->buffer = [];

        return $this;
    }

    /**
     * Get total cost for a team in a date range.
     */
    public function getTeamCost(int $teamId, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float
    {
        $query = DB::table('agent_usage_logs')
            ->where('team_id', $teamId);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return (float) $query->sum('cost');
    }

    /**
     * Get usage summary for a team.
     */
    public function getTeamSummary(int $teamId, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $query = DB::table('agent_usage_logs')
            ->where('team_id', $teamId);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $result = $query->selectRaw('
            COUNT(*) as total_requests,
            SUM(input_tokens) as total_input_tokens,
            SUM(output_tokens) as total_output_tokens,
            SUM(cost) as total_cost,
            AVG(latency_ms) as avg_latency_ms
        ')->first();

        return [
            'team_id' => $teamId,
            'total_requests' => (int) ($result->total_requests ?? 0),
            'total_input_tokens' => (int) ($result->total_input_tokens ?? 0),
            'total_output_tokens' => (int) ($result->total_output_tokens ?? 0),
            'total_tokens' => (int) (($result->total_input_tokens ?? 0) + ($result->total_output_tokens ?? 0)),
            'total_cost' => round((float) ($result->total_cost ?? 0), 4),
            'avg_latency_ms' => round((float) ($result->avg_latency_ms ?? 0), 2),
            'from' => $from?->format('Y-m-d'),
            'to' => $to?->format('Y-m-d'),
        ];
    }

    /**
     * Get usage by agent for a team.
     */
    public function getUsageByAgent(int $teamId, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $query = DB::table('agent_usage_logs')
            ->where('team_id', $teamId);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query->selectRaw('
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
}
