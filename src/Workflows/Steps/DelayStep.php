<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Steps;

use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use DateTimeInterface;

/**
 * Delay Step - Pauses workflow execution for a specified duration.
 *
 * Supports fixed duration delays (in seconds) or waiting until
 * a specific timestamp.
 */
class DelayStep extends Step
{
    /**
     * Delay duration in seconds.
     */
    protected ?int $seconds = null;

    /**
     * Target timestamp to wait until.
     */
    protected ?DateTimeInterface $until = null;

    /**
     * The sleep function (injectable for testing).
     *
     * @var callable(int): void
     */
    protected $sleepFunction;

    /**
     * Create a new delay step.
     */
    public function __construct()
    {
        $this->sleepFunction = fn (int $seconds): int => sleep($seconds);
    }

    /**
     * Create a delay for a number of seconds.
     */
    public static function forSeconds(int $seconds): static
    {
        $step = new static;
        $step->seconds = $seconds;

        return $step;
    }

    /**
     * Create a delay until a specific time.
     */
    public static function until(DateTimeInterface $timestamp): static
    {
        $step = new static;
        $step->until = $timestamp;

        return $step;
    }

    /**
     * Set a custom sleep function (useful for testing).
     *
     * @param  callable(int): void  $function
     */
    public function useSleepFunction(callable $function): static
    {
        $this->sleepFunction = $function;

        return $this;
    }

    /**
     * Execute the delay.
     */
    protected function handle(WorkflowContext $context): mixed
    {
        if ($this->until !== null) {
            $now = time();
            $target = $this->until->getTimestamp();
            $remaining = $target - $now;

            if ($remaining > 0) {
                ($this->sleepFunction)($remaining);
            }

            return StepResult::success(null, [
                'delayed_until' => $this->until->format('Y-m-d H:i:s'),
                'waited_seconds' => max(0, $remaining),
            ]);
        }

        if ($this->seconds !== null && $this->seconds > 0) {
            ($this->sleepFunction)($this->seconds);

            return StepResult::success(null, [
                'delayed_seconds' => $this->seconds,
            ]);
        }

        return StepResult::success(null, ['delayed_seconds' => 0]);
    }
}
