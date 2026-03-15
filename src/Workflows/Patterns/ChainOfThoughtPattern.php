<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Patterns;

use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use Closure;

/**
 * Chain of Thought Pattern - Sequential reasoning chain with reflection.
 *
 * Executes reasoning steps sequentially, where each step can reflect
 * on previous steps' outputs to build upon or revise conclusions.
 * Supports optional reflection steps between reasoning steps.
 */
class ChainOfThoughtPattern implements StepInterface
{
    /**
     * Pattern name.
     */
    protected string $name = 'chain_of_thought';

    /**
     * The reasoning steps in order.
     *
     * @var array<StepInterface>
     */
    protected array $steps = [];

    /**
     * Optional reflection callback invoked after each step.
     *
     * @var Closure(StepResult, array<string, mixed>, WorkflowContext): StepResult|null|null
     */
    protected ?Closure $reflector = null;

    /**
     * Context key prefix for storing thought chain.
     */
    protected string $thoughtPrefix = 'thought';

    /**
     * Maximum reasoning steps (to prevent runaway chains).
     */
    protected int $maxSteps = 20;

    /**
     * Whether to include the full thought chain in the final result.
     */
    protected bool $includeChain = true;

    /**
     * Create a new chain of thought pattern.
     *
     * @param  array<StepInterface>  $steps
     */
    public function __construct(array $steps = [])
    {
        $this->steps = $steps;
    }

    /**
     * Create a chain of thought pattern.
     *
     * @param  array<StepInterface>  $steps
     */
    public static function make(array $steps = []): static
    {
        return new static($steps);
    }

    /**
     * Add a reasoning step.
     */
    public function addStep(StepInterface $step): static
    {
        $this->steps[] = $step;

        return $this;
    }

    /**
     * Add multiple steps.
     *
     * @param  array<StepInterface>  $steps
     */
    public function addSteps(array $steps): static
    {
        foreach ($steps as $step) {
            $this->addStep($step);
        }

        return $this;
    }

    /**
     * Set a reflection callback invoked after each reasoning step.
     *
     * The reflector receives the step result, the accumulated thought
     * chain, and the context. It can return a modified StepResult
     * or null to accept the result as-is.
     *
     * @param  Closure(StepResult, array<string, mixed>, WorkflowContext): StepResult|null  $reflector
     */
    public function withReflection(Closure $reflector): static
    {
        $this->reflector = $reflector;

        return $this;
    }

    /**
     * Set the thought chain prefix for context storage.
     */
    public function thoughtPrefix(string $prefix): static
    {
        $this->thoughtPrefix = $prefix;

        return $this;
    }

    /**
     * Set maximum reasoning steps.
     */
    public function maxSteps(int $max): static
    {
        $this->maxSteps = $max;

        return $this;
    }

    /**
     * Exclude the thought chain from the final result metadata.
     */
    public function excludeChain(): static
    {
        $this->includeChain = false;

        return $this;
    }

    /**
     * Execute the chain of thought.
     */
    public function execute(WorkflowContext $context): StepResult
    {
        if (empty($this->steps)) {
            return StepResult::success([]);
        }

        /** @var array<string, mixed> $thoughtChain */
        $thoughtChain = [];
        $stepCount = 0;

        foreach ($this->steps as $step) {
            if ($stepCount >= $this->maxSteps) {
                return StepResult::failed(
                    "Chain of thought exceeded maximum steps ({$this->maxSteps})",
                    metadata: ['thought_chain' => $thoughtChain, 'steps_completed' => $stepCount]
                );
            }

            $stepName = $step->getName();

            // Store the current thought chain in context for steps to reference
            $context->set("{$this->thoughtPrefix}_chain", $thoughtChain);
            $context->set("{$this->thoughtPrefix}_step", $stepCount);

            // Execute reasoning step
            $result = $step->execute($context);

            if ($result->isFailed()) {
                return StepResult::failed(
                    "Reasoning step '{$stepName}' failed: ".($result->message ?? 'Unknown error'),
                    $result->exception,
                    ['thought_chain' => $thoughtChain, 'failed_at' => $stepName]
                );
            }

            if ($result->shouldPause()) {
                return StepResult::waiting(
                    $result->message ?? "Chain paused at step: {$stepName}",
                    ['paused_at' => $stepName, 'step_result' => $result],
                    ['thought_chain' => $thoughtChain]
                );
            }

            // Record thought
            $thought = [
                'step' => $stepName,
                'output' => $result->output,
                'metadata' => $result->metadata,
            ];

            // Apply reflection if configured
            if ($this->reflector !== null) {
                $reflected = ($this->reflector)($result, $thoughtChain, $context);

                if ($reflected instanceof StepResult) {
                    $thought['reflected'] = true;
                    $thought['original_output'] = $thought['output'];
                    $thought['output'] = $reflected->output;
                    $result = $reflected;
                }
            }

            $thoughtChain[$stepName] = $thought;

            // Store individual thought in context
            $context->set("{$this->thoughtPrefix}_{$stepName}", $result->output);

            $stepCount++;
        }

        // Clean up temporary context
        $context->forget("{$this->thoughtPrefix}_chain");
        $context->forget("{$this->thoughtPrefix}_step");

        // Build final result
        $finalOutput = [];
        foreach ($thoughtChain as $name => $thought) {
            $finalOutput[$name] = $thought['output'];
        }

        $metadata = ['steps_completed' => $stepCount];
        if ($this->includeChain) {
            $metadata['thought_chain'] = $thoughtChain;
        }

        return StepResult::success($finalOutput, $metadata);
    }

    /**
     * Get the pattern name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the pattern name.
     */
    public function as(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getOutputKey(): ?string
    {
        return null;
    }

    public function isRetryable(): bool
    {
        return true;
    }

    public function getMaxRetries(): int
    {
        return 3;
    }

    public function getTimeout(): ?int
    {
        return null;
    }

    public function requiresHumanApproval(): bool
    {
        return false;
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return [];
    }
}
