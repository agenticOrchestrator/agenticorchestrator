<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Steps;

use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use Closure;

/**
 * Loop Step - Iterates over a collection or a fixed number of times.
 *
 * Supports forEach() iteration over context collections and
 * times() for fixed iteration counts.
 */
class LoopStep extends Step
{
    /**
     * The collection resolver (returns iterable from context).
     *
     * @var Closure(WorkflowContext): iterable|null
     */
    protected ?Closure $collectionResolver = null;

    /**
     * Fixed iteration count.
     */
    protected ?int $iterations = null;

    /**
     * The body step to execute on each iteration.
     */
    protected Closure $body;

    /**
     * Context key to store the current item.
     */
    protected string $itemKey = 'loop_item';

    /**
     * Context key to store the current index.
     */
    protected string $indexKey = 'loop_index';

    /**
     * Whether to stop on first failure.
     */
    protected bool $stopOnFailure = true;

    /**
     * Create a new loop step.
     *
     * @param  Closure(mixed, int, WorkflowContext): mixed  $body
     */
    public function __construct(Closure $body)
    {
        $this->body = $body;
    }

    /**
     * Create a loop that iterates over a collection from context.
     *
     * @param  string|Closure(WorkflowContext): iterable  $collection  Context key or resolver
     * @param  Closure(mixed, int, WorkflowContext): mixed  $body
     */
    public static function forEach(string|Closure $collection, Closure $body): static
    {
        $step = new static($body);

        if (is_string($collection)) {
            $step->collectionResolver = fn (WorkflowContext $ctx) => $ctx->get($collection, []);
        } else {
            $step->collectionResolver = $collection;
        }

        return $step;
    }

    /**
     * Create a loop that runs a fixed number of times.
     *
     * @param  Closure(int, WorkflowContext): mixed  $body
     */
    public static function times(int $count, Closure $body): static
    {
        $step = new static($body);
        $step->iterations = $count;

        return $step;
    }

    /**
     * Set the context key for the current item.
     */
    public function as(string $itemKey, string $indexKey = 'loop_index'): static
    {
        $this->itemKey = $itemKey;
        $this->indexKey = $indexKey;

        return $this;
    }

    /**
     * Continue on failure instead of stopping.
     */
    public function continueOnFailure(): static
    {
        $this->stopOnFailure = false;

        return $this;
    }

    /**
     * Execute the loop.
     */
    protected function handle(WorkflowContext $context): mixed
    {
        $results = [];
        $failures = 0;

        if ($this->collectionResolver !== null) {
            $collection = ($this->collectionResolver)($context);
            $index = 0;

            foreach ($collection as $item) {
                $context->set($this->itemKey, $item);
                $context->set($this->indexKey, $index);

                $result = ($this->body)($item, $index, $context);

                if ($result instanceof StepResult) {
                    if ($result->isFailed()) {
                        $failures++;
                        if ($this->stopOnFailure) {
                            return StepResult::failed(
                                "Loop failed at index {$index}: ".($result->message ?? 'Unknown error'),
                                $result->exception,
                                ['iteration_results' => $results, 'failed_at' => $index]
                            );
                        }
                    }
                    $results[$index] = $result->output;
                } else {
                    $results[$index] = $result;
                }

                $index++;
            }
        } elseif ($this->iterations !== null) {
            for ($i = 0; $i < $this->iterations; $i++) {
                $context->set($this->indexKey, $i);

                $result = ($this->body)($i, $context);

                if ($result instanceof StepResult) {
                    if ($result->isFailed()) {
                        $failures++;
                        if ($this->stopOnFailure) {
                            return StepResult::failed(
                                "Loop failed at iteration {$i}: ".($result->message ?? 'Unknown error'),
                                $result->exception,
                                ['iteration_results' => $results, 'failed_at' => $i]
                            );
                        }
                    }
                    $results[$i] = $result->output;
                } else {
                    $results[$i] = $result;
                }
            }
        }

        // Clean up loop variables
        $context->forget($this->itemKey);
        $context->forget($this->indexKey);

        return StepResult::success($results, [
            'iterations' => count($results),
            'failures' => $failures,
        ]);
    }
}
