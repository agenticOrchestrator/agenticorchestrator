<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows\Patterns;

use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\Patterns\SequentialPattern;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SequentialPattern::class)]
class SequentialPatternTest extends TestCase
{
    #[Test]
    public function it_creates_with_static_make(): void
    {
        $pattern = SequentialPattern::make();

        $this->assertInstanceOf(SequentialPattern::class, $pattern);
    }

    #[Test]
    public function it_executes_steps_in_order(): void
    {
        $executionOrder = [];

        $step1 = $this->createStep('step1', function () use (&$executionOrder) {
            $executionOrder[] = 'step1';

            return StepResult::success(['step' => 1]);
        });

        $step2 = $this->createStep('step2', function () use (&$executionOrder) {
            $executionOrder[] = 'step2';

            return StepResult::success(['step' => 2]);
        });

        $step3 = $this->createStep('step3', function () use (&$executionOrder) {
            $executionOrder[] = 'step3';

            return StepResult::success(['step' => 3]);
        });

        $pattern = SequentialPattern::make([$step1, $step2, $step3]);
        $context = new WorkflowContext;

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(['step1', 'step2', 'step3'], $executionOrder);
    }

    #[Test]
    public function it_returns_empty_success_with_no_steps(): void
    {
        $pattern = SequentialPattern::make();
        $context = new WorkflowContext;

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame([], $result->output);
    }

    #[Test]
    public function it_stops_on_failure(): void
    {
        $step1 = $this->createStep('step1', fn () => StepResult::success(['step' => 1]));
        $step2 = $this->createStep('step2', fn () => StepResult::failed('Step 2 failed'));
        $step3 = $this->createStep('step3', fn () => StepResult::success(['step' => 3]));

        // Step 3 should never be called
        $step3->expects($this->never())->method('execute');

        $pattern = SequentialPattern::make([$step1, $step2, $step3]);
        $context = new WorkflowContext;

        $result = $pattern->execute($context);

        $this->assertTrue($result->isFailed());
        $this->assertSame("Step 'step2' failed: Step 2 failed", $result->message);
    }

    #[Test]
    public function it_stops_on_pause(): void
    {
        $step1 = $this->createStep('step1', fn () => StepResult::success(['step' => 1]));
        $step2 = $this->createStep('step2', fn () => StepResult::waiting('Needs approval'));
        $step3 = $this->createStep('step3', fn () => StepResult::success(['step' => 3]));

        // Step 3 should never be called
        $step3->expects($this->never())->method('execute');

        $pattern = SequentialPattern::make([$step1, $step2, $step3]);
        $context = new WorkflowContext;

        $result = $pattern->execute($context);

        $this->assertTrue($result->shouldPause());
    }

    #[Test]
    public function it_skips_completed_steps_on_resume(): void
    {
        $step1 = $this->createStep('step1', fn () => StepResult::success(['step' => 1]));
        $step2 = $this->createStep('step2', fn () => StepResult::success(['step' => 2]));

        // Step 1 should not be called if already completed
        $step1->expects($this->never())->method('execute');

        $pattern = SequentialPattern::make([$step1, $step2]);
        $context = new WorkflowContext;
        $context->markStepCompleted('step1');

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_collects_outputs_from_all_steps(): void
    {
        $step1 = $this->createStep('step1', fn () => StepResult::success(['value' => 'from step 1']));
        $step2 = $this->createStep('step2', fn () => StepResult::success(['value' => 'from step 2']));

        $pattern = SequentialPattern::make([$step1, $step2]);
        $context = new WorkflowContext;

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('step1', $result->output);
        $this->assertArrayHasKey('step2', $result->output);
    }

    #[Test]
    public function it_sets_pattern_name(): void
    {
        $pattern = SequentialPattern::make()->as('my-sequence');

        $this->assertSame('my-sequence', $pattern->getName());
    }

    #[Test]
    public function it_adds_step_fluently(): void
    {
        $step = $this->createStep('added', fn () => StepResult::success([]));

        $pattern = SequentialPattern::make()->addStep($step);
        $context = new WorkflowContext;

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
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
