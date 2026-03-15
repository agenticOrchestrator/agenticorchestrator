<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Steps;

use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use Closure;

/**
 * While Step - Loops while a condition is true.
 *
 * Includes a max iterations guard to prevent infinite loops.
 */
class WhileStep extends Step
{
    /**
     * The condition to evaluate before each iteration.
     *
     * @var Closure(WorkflowContext): bool
     */
    protected Closure $condition;

    /**
     * The body to execute on each iteration.
     *
     * @var Closure(int, WorkflowContext): mixed
     */
    protected Closure $body;

    /**
     * Maximum iterations to prevent infinite loops.
     */
    protected int $maxIterations = 100;

    /**
     * Create a new while step.
     *
     * @param  Closure(WorkflowContext): bool  $condition
     * @param  Closure(int, WorkflowContext): mixed  $body
     */
    public function __construct(Closure $condition, Closure $body)
    {
        $this->condition = $condition;
        $this->body = $body;
    }

    /**
     * Create a while step.
     *
     * @param  Closure(WorkflowContext): bool  $condition
     * @param  Closure(int, WorkflowContext): mixed  $body
     */
    public static function make(Closure $condition, Closure $body): static
    {
        return new static($condition, $body);
    }

    /**
     * Set the maximum number of iterations.
     */
    public function maxIterations(int $max): static
    {
        $this->maxIterations = $max;

        return $this;
    }

    /**
     * Execute the while loop.
     */
    protected function handle(WorkflowContext $context): mixed
    {
        $results = [];
        $iteration = 0;

        while (($this->condition)($context)) {
            if ($iteration >= $this->maxIterations) {
                return StepResult::failed(
                    "While loop exceeded maximum iterations ({$this->maxIterations})",
                    metadata: ['iteration_results' => $results, 'iterations' => $iteration]
                );
            }

            $result = ($this->body)($iteration, $context);

            if ($result instanceof StepResult) {
                if ($result->isFailed()) {
                    return StepResult::failed(
                        "While loop failed at iteration {$iteration}: ".($result->message ?? 'Unknown error'),
                        $result->exception,
                        ['iteration_results' => $results, 'failed_at' => $iteration]
                    );
                }
                $results[] = $result->output;
            } else {
                $results[] = $result;
            }

            $iteration++;
        }

        return StepResult::success($results, [
            'iterations' => $iteration,
        ]);
    }
}
