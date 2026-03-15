<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Testing;

use AgenticOrchestrator\Contracts\WorkflowInterface;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use AgenticOrchestrator\Workflows\WorkflowDefinition;
use AgenticOrchestrator\Workflows\WorkflowResult;
use Closure;
use PHPUnit\Framework\AssertionFailedError;

/**
 * Fake Workflow - Test double for workflow testing.
 *
 * @example
 * ```php
 * $fake = FakeWorkflow::make()
 *     ->succeedsWith(['result' => 'done']);
 *
 * $result = $fake->run(['input' => 'test']);
 * $fake->assertRan();
 * ```
 */
class FakeWorkflow implements WorkflowInterface
{
    protected string $name = 'FakeWorkflow';

    protected ?int $teamId = null;

    /** @var WorkflowResult|Closure|null */
    protected $result = null;

    /** @var array<array{input: array<string, mixed>}> */
    protected array $runs = [];

    protected bool $shouldFail = false;

    protected string $failureMessage = 'Workflow failed';

    protected ?string $failedStep = null;

    protected bool $shouldPause = false;

    protected ?string $pauseStep = null;

    protected string $pauseMessage = 'Awaiting approval';

    /**
     * Create a new fake workflow.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Set the workflow name.
     */
    public function named(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Configure workflow to succeed with output.
     *
     * @param  array<string, mixed>|Closure  $output
     */
    public function succeedsWith(array|Closure $output): static
    {
        if ($output instanceof Closure) {
            $this->result = $output;
        } else {
            $context = new WorkflowContext(['output' => $output]);
            $context->markStepCompleted('fake-step');

            $this->result = new WorkflowResult(
                executionId: 'fake-execution-'.uniqid(),
                status: StepResult::STATUS_SUCCESS,
                output: $output,
                context: $context,
                duration: 100.0,
            );
        }

        return $this;
    }

    /**
     * Configure workflow to fail.
     */
    public function fails(string $message = 'Workflow failed', ?string $step = null): static
    {
        $this->shouldFail = true;
        $this->failureMessage = $message;
        $this->failedStep = $step;

        return $this;
    }

    /**
     * Configure workflow to pause.
     *
     * @param  array<string, mixed>  $state
     */
    public function pausesAt(string $step, array $state = []): static
    {
        $this->shouldPause = true;
        $this->pauseStep = $step;

        $context = new WorkflowContext($state);
        $this->result = new WorkflowResult(
            executionId: 'fake-execution-'.uniqid(),
            status: StepResult::STATUS_WAITING,
            output: null,
            context: $context,
            duration: 50.0,
            metadata: ['paused_step' => $step],
        );

        return $this;
    }

    /**
     * Get the workflow definition.
     */
    public function definition(): WorkflowDefinition
    {
        return WorkflowDefinition::create()
            ->name($this->name)
            ->description('Fake workflow for testing');
    }

    /**
     * Scope to a team.
     */
    public function forTeam(int|string|object $team): static
    {
        $clone = clone $this;
        $clone->teamId = is_object($team) ? (int) $team->getKey() : (int) $team;

        return $clone;
    }

    /**
     * Run the workflow.
     *
     * @param  array<string, mixed>  $input
     */
    public function run(array $input = []): WorkflowResult
    {
        $this->runs[] = ['input' => $input];

        if ($this->shouldFail) {
            $context = new WorkflowContext($input);
            if ($this->failedStep) {
                $context->markStepFailed($this->failedStep, $this->failureMessage);
            }

            return new WorkflowResult(
                executionId: 'fake-execution-'.uniqid(),
                status: StepResult::STATUS_FAILED,
                output: null,
                context: $context,
                duration: 100.0,
                error: $this->failureMessage,
            );
        }

        if ($this->result instanceof Closure) {
            $result = ($this->result)($input);

            if ($result instanceof WorkflowResult) {
                return $result;
            }

            $context = new WorkflowContext(['output' => $result]);
            $context->markStepCompleted('fake-step');

            return new WorkflowResult(
                executionId: 'fake-execution-'.uniqid(),
                status: StepResult::STATUS_SUCCESS,
                output: $result,
                context: $context,
                duration: 100.0,
            );
        }

        if ($this->result instanceof WorkflowResult) {
            return $this->result;
        }

        // Default: return empty success
        $context = new WorkflowContext(['output' => []]);
        $context->markStepComplete('fake-step');

        return new WorkflowResult(
            executionId: 'fake-execution-'.uniqid(),
            status: StepResult::STATUS_SUCCESS,
            output: [],
            context: $context,
            duration: 100.0,
        );
    }

    /**
     * Assert workflow was run.
     */
    public function assertRan(): void
    {
        if (empty($this->runs)) {
            throw new AssertionFailedError(
                'Expected workflow to be run, but it was not.'
            );
        }
    }

    /**
     * Assert workflow was not run.
     */
    public function assertNotRan(): void
    {
        if (! empty($this->runs)) {
            throw new AssertionFailedError(
                sprintf('Expected workflow not to be run, but it was run %d time(s).', count($this->runs))
            );
        }
    }

    /**
     * Assert run count.
     */
    public function assertRanTimes(int $count): void
    {
        $actual = count($this->runs);

        if ($actual !== $count) {
            throw new AssertionFailedError(
                sprintf('Expected workflow to be run %d time(s), but it was run %d time(s).', $count, $actual)
            );
        }
    }

    /**
     * Assert run with specific input.
     *
     * @param  array<string, mixed>  $input
     */
    public function assertRanWith(array $input): void
    {
        foreach ($this->runs as $run) {
            if ($run['input'] === $input) {
                return;
            }
        }

        throw new AssertionFailedError(
            sprintf('Expected workflow to be run with %s, but it was not.', json_encode($input))
        );
    }

    /**
     * Assert run with input containing key.
     */
    public function assertRanWithKey(string $key): void
    {
        foreach ($this->runs as $run) {
            if (array_key_exists($key, $run['input'])) {
                return;
            }
        }

        throw new AssertionFailedError(
            sprintf('Expected workflow to be run with input key "%s", but it was not.', $key)
        );
    }

    /**
     * Get all runs.
     *
     * @return array<array{input: array<string, mixed>}>
     */
    public function getRuns(): array
    {
        return $this->runs;
    }

    /**
     * Get the last run input.
     *
     * @return array<string, mixed>|null
     */
    public function getLastRunInput(): ?array
    {
        $lastRun = $this->runs[count($this->runs) - 1] ?? null;

        return $lastRun ? $lastRun['input'] : null;
    }

    /**
     * Reset the fake workflow state.
     */
    public function reset(): static
    {
        $this->runs = [];

        return $this;
    }
}
