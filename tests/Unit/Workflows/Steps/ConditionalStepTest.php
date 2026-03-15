<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows\Steps;

use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\Steps\ConditionalStep;
use AgenticOrchestrator\Workflows\WorkflowContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConditionalStep::class)]
class ConditionalStepTest extends TestCase
{
    #[Test]
    public function it_executes_then_step_when_condition_is_true(): void
    {
        $thenStep = $this->createMock(StepInterface::class);
        $thenStep->expects($this->once())
            ->method('execute')
            ->willReturn(StepResult::success(['branch' => 'then']));

        $step = ConditionalStep::when(
            fn (WorkflowContext $ctx) => true,
            $thenStep
        );

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(['branch' => 'then'], $result->output);
    }

    #[Test]
    public function it_returns_skipped_when_condition_is_false_and_no_else(): void
    {
        $thenStep = $this->createMock(StepInterface::class);
        $thenStep->expects($this->never())->method('execute');

        $step = ConditionalStep::when(
            fn (WorkflowContext $ctx) => false,
            $thenStep
        );

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isSkipped());
        $this->assertSame('Condition not met and no else branch', $result->message);
    }

    #[Test]
    public function it_executes_else_step_when_condition_is_false(): void
    {
        $thenStep = $this->createMock(StepInterface::class);
        $thenStep->expects($this->never())->method('execute');

        $elseStep = $this->createMock(StepInterface::class);
        $elseStep->expects($this->once())
            ->method('execute')
            ->willReturn(StepResult::success(['branch' => 'else']));

        $step = ConditionalStep::when(
            fn (WorkflowContext $ctx) => false,
            $thenStep
        )->otherwise($elseStep);

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(['branch' => 'else'], $result->output);
    }

    #[Test]
    public function it_evaluates_condition_with_context(): void
    {
        $thenStep = $this->createMock(StepInterface::class);
        $thenStep->expects($this->once())
            ->method('execute')
            ->willReturn(StepResult::success('executed'));

        $step = ConditionalStep::when(
            fn (WorkflowContext $ctx) => $ctx->get('flag') === true,
            $thenStep
        );

        $context = new WorkflowContext;
        $context->set('flag', true);

        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_creates_condition_based_on_context_key_existence(): void
    {
        $thenStep = $this->createMock(StepInterface::class);
        $thenStep->expects($this->once())
            ->method('execute')
            ->willReturn(StepResult::success('found'));

        $step = ConditionalStep::ifHas('required_key', $thenStep);

        $context = new WorkflowContext;
        $context->set('required_key', 'value');

        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_skips_when_context_key_does_not_exist(): void
    {
        $thenStep = $this->createMock(StepInterface::class);
        $thenStep->expects($this->never())->method('execute');

        $step = ConditionalStep::ifHas('missing_key', $thenStep);

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isSkipped());
    }

    #[Test]
    public function it_creates_condition_based_on_context_value_equality(): void
    {
        $thenStep = $this->createMock(StepInterface::class);
        $thenStep->expects($this->once())
            ->method('execute')
            ->willReturn(StepResult::success('matched'));

        $step = ConditionalStep::ifEquals('status', 'active', $thenStep);

        $context = new WorkflowContext;
        $context->set('status', 'active');

        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_skips_when_context_value_does_not_match(): void
    {
        $thenStep = $this->createMock(StepInterface::class);
        $thenStep->expects($this->never())->method('execute');

        $step = ConditionalStep::ifEquals('status', 'active', $thenStep);

        $context = new WorkflowContext;
        $context->set('status', 'inactive');

        $result = $step->execute($context);

        $this->assertTrue($result->isSkipped());
    }

    #[Test]
    public function it_propagates_failure_from_then_step(): void
    {
        $thenStep = $this->createMock(StepInterface::class);
        $thenStep->method('execute')
            ->willReturn(StepResult::failed('Then step failed'));

        $step = ConditionalStep::when(
            fn () => true,
            $thenStep
        );

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isFailed());
        $this->assertSame('Then step failed', $result->message);
    }

    #[Test]
    public function it_propagates_failure_from_else_step(): void
    {
        $thenStep = $this->createMock(StepInterface::class);
        $thenStep->expects($this->never())->method('execute');

        $elseStep = $this->createMock(StepInterface::class);
        $elseStep->method('execute')
            ->willReturn(StepResult::failed('Else step failed'));

        $step = ConditionalStep::when(
            fn () => false,
            $thenStep
        )->otherwise($elseStep);

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isFailed());
        $this->assertSame('Else step failed', $result->message);
    }

    #[Test]
    public function it_returns_null_output_key(): void
    {
        $thenStep = $this->createMock(StepInterface::class);

        $step = ConditionalStep::when(fn () => true, $thenStep);

        $this->assertNull($step->getOutputKey());
    }

    #[Test]
    public function it_has_correct_auto_generated_name(): void
    {
        $thenStep = $this->createMock(StepInterface::class);

        $step = ConditionalStep::when(fn () => true, $thenStep);

        $this->assertSame('conditional', $step->getName());
    }

    #[Test]
    public function it_propagates_waiting_result_from_inner_step(): void
    {
        $thenStep = $this->createMock(StepInterface::class);
        $thenStep->method('execute')
            ->willReturn(StepResult::waiting('Needs approval'));

        $step = ConditionalStep::when(fn () => true, $thenStep);

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isWaiting());
        $this->assertSame('Needs approval', $result->message);
    }
}
