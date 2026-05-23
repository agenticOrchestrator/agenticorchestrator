<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Patterns\Drivers;

use AgenticOrchestrator\Contracts\ParallelDriverInterface;
use AgenticOrchestrator\Workflows\Patterns\ParallelOptions;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sync Parallel Driver - Runs branches sequentially in the current process.
 *
 * This is the default driver. It does not provide true concurrency: branches
 * execute one after another, but with parallel semantics (independent results,
 * race mode, failure thresholds). For real concurrency use the
 * {@see QueueParallelDriver}, which fans branches out across queue workers.
 */
class SyncParallelDriver implements ParallelDriverInterface
{
    public function run(array $steps, WorkflowContext $context, ParallelOptions $options): StepResult
    {
        if (empty($steps)) {
            return StepResult::success([]);
        }

        $results = [];
        $failures = 0;
        $successes = 0;

        foreach ($steps as $step) {
            $stepName = $step->getName();

            // Skip already completed steps (for resumption)
            if ($context->isStepCompleted($stepName)) {
                $successes++;

                continue;
            }

            try {
                $result = $step->execute($context);
                $results[$stepName] = $result;

                if ($result->isSuccess()) {
                    $successes++;
                    $context->markStepCompleted($stepName);

                    // Race mode: return on first success
                    if (! $options->waitForAll) {
                        return StepResult::success(
                            [$stepName => $result->output],
                            ['winner' => $stepName, 'partial_results' => $results]
                        );
                    }
                } elseif ($result->isFailed()) {
                    $failures++;
                    $context->markStepFailed($stepName, $result->message ?? 'Unknown error');
                } elseif ($result->shouldPause()) {
                    // A step is waiting for approval - pause entire parallel execution
                    return StepResult::waiting(
                        "Parallel step '{$stepName}' requires approval",
                        ['paused_at' => $stepName, 'step_result' => $result],
                        ['partial_results' => $results]
                    );
                }
            } catch (Throwable $e) {
                $failures++;
                $results[$stepName] = StepResult::failed($e->getMessage(), $e);
                $context->markStepFailed($stepName, $e->getMessage(), get_class($e));

                Log::error("Parallel step '{$stepName}' threw exception", [
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        // Check failure threshold
        if ($failures > $options->failureThreshold) {
            return StepResult::failed(
                "Too many parallel step failures ({$failures} > {$options->failureThreshold})",
                metadata: ['step_results' => $results, 'failures' => $failures, 'successes' => $successes]
            );
        }

        // Collect successful outputs
        $outputs = [];
        foreach ($results as $stepName => $result) {
            if ($result->isSuccess()) {
                $outputs[$stepName] = $result->output;
            }
        }

        return StepResult::success(
            $outputs,
            [
                'steps_completed' => $successes,
                'steps_failed' => $failures,
                'step_results' => $results,
            ]
        );
    }
}
