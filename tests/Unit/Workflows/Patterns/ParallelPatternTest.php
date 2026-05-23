<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows\Patterns;

use AgenticOrchestrator\Contracts\ParallelDriverInterface;
use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\Patterns\ParallelOptions;
use AgenticOrchestrator\Workflows\Patterns\ParallelPattern;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParallelPattern::class)]
class ParallelPatternTest extends TestCase
{
    #[Test]
    public function it_creates_with_static_make(): void
    {
        $pattern = ParallelPattern::make();

        $this->assertInstanceOf(ParallelPattern::class, $pattern);
    }

    #[Test]
    public function it_returns_empty_success_with_no_steps(): void
    {
        $pattern = ParallelPattern::make();
        $context = new WorkflowContext;

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame([], $result->output);
    }

    #[Test]
    public function it_executes_all_steps(): void
    {
        $step1 = $this->createStep('step1', fn () => StepResult::success(['value' => 1]));
        $step2 = $this->createStep('step2', fn () => StepResult::success(['value' => 2]));
        $step3 = $this->createStep('step3', fn () => StepResult::success(['value' => 3]));

        $pattern = ParallelPattern::make([$step1, $step2, $step3]);
        $context = new WorkflowContext;

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('step1', $result->output);
        $this->assertArrayHasKey('step2', $result->output);
        $this->assertArrayHasKey('step3', $result->output);
    }

    #[Test]
    public function it_fails_when_too_many_failures(): void
    {
        $step1 = $this->createStep('step1', fn () => StepResult::failed('Error 1'));
        $step2 = $this->createStep('step2', fn () => StepResult::success(['value' => 2]));

        $pattern = ParallelPattern::make([$step1, $step2]);
        $context = new WorkflowContext;

        $result = $pattern->execute($context);

        $this->assertTrue($result->isFailed());
    }

    #[Test]
    public function it_allows_configured_failures(): void
    {
        $step1 = $this->createStep('step1', fn () => StepResult::failed('Error 1'));
        $step2 = $this->createStep('step2', fn () => StepResult::success(['value' => 2]));
        $step3 = $this->createStep('step3', fn () => StepResult::success(['value' => 3]));

        $pattern = ParallelPattern::make([$step1, $step2, $step3])
            ->allowFailures(1);

        $context = new WorkflowContext;
        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_returns_first_success_in_race_mode(): void
    {
        $step1 = $this->createStep('step1', fn () => StepResult::success(['winner' => true]));
        $step2 = $this->createStep('step2', fn () => StepResult::success(['value' => 2]));

        $pattern = ParallelPattern::make([$step1, $step2])->race();
        $context = new WorkflowContext;

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('step1', $result->getMeta('winner'));
    }

    #[Test]
    public function it_pauses_when_step_needs_approval(): void
    {
        $step1 = $this->createStep('step1', fn () => StepResult::success(['value' => 1]));
        $step2 = $this->createStep('step2', fn () => StepResult::waiting('Needs approval'));

        $pattern = ParallelPattern::make([$step1, $step2]);
        $context = new WorkflowContext;

        $result = $pattern->execute($context);

        $this->assertTrue($result->shouldPause());
    }

    #[Test]
    public function it_skips_completed_steps(): void
    {
        $step1 = $this->createStep('step1', fn () => StepResult::success(['value' => 1]));
        $step2 = $this->createStep('step2', fn () => StepResult::success(['value' => 2]));

        // Step 1 should not be called if already completed
        $step1->expects($this->never())->method('execute');

        $pattern = ParallelPattern::make([$step1, $step2]);
        $context = new WorkflowContext;
        $context->markStepCompleted('step1');

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_sets_pattern_name(): void
    {
        $pattern = ParallelPattern::make()->as('my-parallel');

        $this->assertSame('my-parallel', $pattern->getName());
    }

    #[Test]
    public function it_adds_steps_fluently(): void
    {
        $step1 = $this->createStep('step1', fn () => StepResult::success([]));
        $step2 = $this->createStep('step2', fn () => StepResult::success([]));

        $pattern = ParallelPattern::make()
            ->addStep($step1)
            ->addSteps([$step2]);

        $context = new WorkflowContext;
        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_is_retryable(): void
    {
        $pattern = ParallelPattern::make();

        $this->assertTrue($pattern->isRetryable());
        $this->assertSame(3, $pattern->getMaxRetries());
    }

    #[Test]
    public function it_delegates_execution_to_the_configured_driver(): void
    {
        $driver = new class implements ParallelDriverInterface
        {
            public bool $called = false;

            public function run(array $steps, WorkflowContext $context, ParallelOptions $options): StepResult
            {
                $this->called = true;

                return StepResult::success(['from' => 'driver']);
            }
        };

        $result = ParallelPattern::make([])->useDriver($driver)->execute(new WorkflowContext);

        $this->assertTrue($driver->called);
        $this->assertSame(['from' => 'driver'], $result->output);
    }

    /**
     * Create a mock step with a callback.
     */
    private function createStep(string $name, callable $callback): StepInterface
    {
        $step = $this->createMock(StepInterface::class);
        $step->method('getName')->willReturn($name);
        $step->method('getDependencies')->willReturn([]);
        $step->expects($this->any())
            ->method('execute')
            ->willReturnCallback($callback);

        return $step;
    }
}
