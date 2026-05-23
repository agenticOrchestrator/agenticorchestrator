<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Patterns\Drivers;

use AgenticOrchestrator\Contracts\ParallelDriverInterface;
use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\Jobs\RunBranchStep;
use AgenticOrchestrator\Workflows\Patterns\ParallelOptions;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Queue Parallel Driver - Fans branches out across queue workers.
 *
 * Each branch is dispatched as a {@see RunBranchStep} job inside a batch
 * (Bus::batch). The branches run concurrently on whatever workers are
 * available; this driver dispatches the batch and then waits (with a bounded
 * poll loop) for it to finish before aggregating the results.
 *
 * With the `sync` queue connection the jobs run inline during dispatch, so the
 * wait returns immediately — useful for tests and small setups.
 *
 * Limitations of this first version:
 * - The orchestrating process blocks while the batch runs. If the workflow
 *   itself is queued, make sure branch jobs run on a *different* worker pool to
 *   avoid starving the queue. A non-blocking pause/resume variant is planned.
 * - Branch steps must be serializable (no closures). Use invokable step
 *   classes or AgentStep with a registered agent name.
 * - Tenant/user scope is not re-applied inside branch jobs yet.
 */
class QueueParallelDriver implements ParallelDriverInterface
{
    /**
     * @param  string|null  $connection  Queue connection (null = default)
     * @param  string|null  $queue  Queue name (null = default)
     * @param  int  $timeout  Maximum seconds to wait for the batch to finish
     * @param  int  $pollIntervalMs  How often to poll the batch status, in milliseconds
     */
    public function __construct(
        protected ?string $connection = null,
        protected ?string $queue = null,
        protected int $timeout = 300,
        protected int $pollIntervalMs = 250,
    ) {}

    public function run(array $steps, WorkflowContext $context, ParallelOptions $options): StepResult
    {
        if (empty($steps)) {
            return StepResult::success([]);
        }

        // Only dispatch branches that have not already completed (resumption).
        $pending = array_values(array_filter(
            $steps,
            fn ($step) => ! $context->isStepCompleted($step->getName()),
        ));

        if (empty($pending)) {
            return StepResult::success([]);
        }

        $runKey = (string) Str::uuid();
        $snapshot = $context->getState();
        $ttl = max(60, $this->timeout + 60);

        $jobs = [];
        foreach ($pending as $step) {
            $jobs[] = new RunBranchStep(
                runKey: $runKey,
                branchName: $step->getName(),
                step: $step,
                contextState: $snapshot,
                race: ! $options->waitForAll,
                ttl: $ttl,
            );
        }

        $pending_batch = Bus::batch($jobs)
            ->name("workflow-parallel:{$options->name}")
            // Branch jobs catch their own errors; the driver owns the failure
            // threshold. allowFailures keeps the batch alive on infra errors.
            ->allowFailures();

        if ($this->connection !== null) {
            $pending_batch->onConnection($this->connection);
        }

        if ($this->queue !== null) {
            $pending_batch->onQueue($this->queue);
        }

        $batch = $pending_batch->dispatch();

        if (! $this->await($batch->id)) {
            return StepResult::failed(
                "Parallel batch '{$options->name}' timed out after {$this->timeout}s",
                metadata: ['batch_id' => $batch->id, 'driver' => 'queue'],
            );
        }

        return $this->collect($pending, $context, $options, $runKey);
    }

    /**
     * Wait for the batch to finish, up to the configured timeout.
     *
     * Returns true if the batch finished (or was cancelled / pruned), false on
     * timeout. With the sync connection this returns on the first check.
     */
    protected function await(string $batchId): bool
    {
        $deadline = microtime(true) + $this->timeout;

        do {
            $batch = Bus::findBatch($batchId);

            if ($batch === null || $batch->finished() || $batch->cancelled()) {
                return true;
            }

            usleep($this->pollIntervalMs * 1000);
        } while (microtime(true) < $deadline);

        return false;
    }

    /**
     * Collect branch results from the cache and aggregate them.
     *
     * @param  array<StepInterface>  $steps
     */
    protected function collect(array $steps, WorkflowContext $context, ParallelOptions $options, string $runKey): StepResult
    {
        $outputs = [];
        $results = [];
        $failures = 0;
        $successes = 0;

        foreach ($steps as $step) {
            $name = $step->getName();
            $cached = Cache::pull(RunBranchStep::cacheKey($runKey, $name));

            if ($cached === null) {
                // In race mode a cancelled branch never reports - that is fine.
                // When waiting for all, a missing result is a failure.
                if ($options->waitForAll) {
                    $failures++;
                    $context->markStepFailed($name, 'Branch did not report a result');
                }

                continue;
            }

            $results[$name] = $cached;

            if (($cached['status'] ?? null) === StepResult::STATUS_SUCCESS) {
                $successes++;
                $outputs[$name] = $cached['output'];
                $context->markStepCompleted($name);

                // Race mode: first successful branch wins.
                if (! $options->waitForAll) {
                    return StepResult::success(
                        [$name => $cached['output']],
                        ['winner' => $name, 'driver' => 'queue', 'partial_results' => $results],
                    );
                }
            } else {
                $failures++;
                $context->markStepFailed($name, $cached['message'] ?? 'Unknown error');
            }
        }

        if ($failures > $options->failureThreshold) {
            return StepResult::failed(
                "Too many parallel step failures ({$failures} > {$options->failureThreshold})",
                metadata: ['step_results' => $results, 'failures' => $failures, 'successes' => $successes, 'driver' => 'queue'],
            );
        }

        return StepResult::success($outputs, [
            'steps_completed' => $successes,
            'steps_failed' => $failures,
            'driver' => 'queue',
        ]);
    }
}
