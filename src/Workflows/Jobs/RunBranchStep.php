<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Jobs;

use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Run Branch Step - Executes a single branch of a queued ParallelPattern.
 *
 * One job is dispatched per branch inside a {@see Batch}.
 * Jobs run concurrently across queue workers. Each job rehydrates a snapshot
 * of the workflow context, runs its step, and writes the outcome to the cache
 * where the QueueParallelDriver collects it once the batch finishes.
 *
 * Exceptions are caught and stored as a failed result rather than rethrown, so
 * the driver — not the batch — owns the failure-threshold decision. The only
 * exception is race mode, where a successful branch cancels the batch.
 */
class RunBranchStep implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  string  $runKey  Unique key shared by all branches of one execution
     * @param  string  $branchName  The branch (step) name
     * @param  StepInterface  $step  The step to execute (must be serializable)
     * @param  array<string, mixed>  $contextState  Snapshot from WorkflowContext::getState()
     * @param  bool  $race  Cancel the batch after the first success
     * @param  int  $ttl  Result cache lifetime in seconds
     */
    public function __construct(
        public readonly string $runKey,
        public readonly string $branchName,
        public readonly StepInterface $step,
        public readonly array $contextState,
        public readonly bool $race = false,
        public readonly int $ttl = 3600,
    ) {}

    /**
     * Execute the branch.
     */
    public function handle(): void
    {
        // Another branch already won the race, or the batch was cancelled.
        if ($this->batch()?->cancelled()) {
            return;
        }

        $result = $this->runStep();

        Cache::put($this->resultKey(), [
            'status' => $result->status,
            'output' => $result->output,
            'message' => $result->message,
        ], now()->addSeconds($this->ttl));

        if ($this->race && $result->isSuccess()) {
            $this->batch()?->cancel();
        }
    }

    /**
     * Run the step against a rehydrated context snapshot.
     */
    protected function runStep(): StepResult
    {
        try {
            $context = WorkflowContext::fromState($this->contextState);

            return $this->step->execute($context);
        } catch (Throwable $e) {
            return StepResult::failed($e->getMessage(), $e);
        }
    }

    /**
     * The cache key this branch writes its result to.
     */
    public function resultKey(): string
    {
        return self::cacheKey($this->runKey, $this->branchName);
    }

    /**
     * Build the cache key for a given run and branch.
     *
     * Used by both the job (to write) and the driver (to collect).
     */
    public static function cacheKey(string $runKey, string $branchName): string
    {
        return "agent-orchestrator:parallel:{$runKey}:{$branchName}";
    }
}
