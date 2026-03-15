<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows\Steps;

use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\Steps\SubWorkflowStep;
use AgenticOrchestrator\Workflows\WorkflowContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubWorkflowStep::class)]
class SubWorkflowStepTest extends TestCase
{
    #[Test]
    public function it_executes_a_sub_workflow(): void
    {
        $innerStep = $this->createMock(StepInterface::class);
        $innerStep->method('execute')
            ->willReturn(StepResult::success(['result' => 'inner output']));

        $step = SubWorkflowStep::make($innerStep);

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(['result' => 'inner output'], $result->output);
    }

    #[Test]
    public function it_maps_input_from_parent_to_child_context(): void
    {
        $capturedContext = null;

        $innerStep = $this->createMock(StepInterface::class);
        $innerStep->method('execute')
            ->willReturnCallback(function (WorkflowContext $ctx) use (&$capturedContext) {
                $capturedContext = $ctx;

                return StepResult::success(['done' => true]);
            });

        $step = SubWorkflowStep::make($innerStep)
            ->mapInput(['parent_data' => 'child_data']);

        $context = new WorkflowContext;
        $context->set('parent_data', 'hello');

        $step->execute($context);

        $this->assertSame('hello', $capturedContext->get('child_data'));
    }

    #[Test]
    public function it_maps_input_with_closure(): void
    {
        $capturedContext = null;

        $innerStep = $this->createMock(StepInterface::class);
        $innerStep->method('execute')
            ->willReturnCallback(function (WorkflowContext $ctx) use (&$capturedContext) {
                $capturedContext = $ctx;

                return StepResult::success([]);
            });

        $step = SubWorkflowStep::make($innerStep)
            ->mapInput(fn (WorkflowContext $ctx) => [
                'combined' => $ctx->get('a', '').':'.$ctx->get('b', ''),
            ]);

        $context = new WorkflowContext;
        $context->set('a', 'foo');
        $context->set('b', 'bar');

        $step->execute($context);

        $this->assertSame('foo:bar', $capturedContext->get('combined'));
    }

    #[Test]
    public function it_maps_output_back_to_parent_context(): void
    {
        $innerStep = $this->createMock(StepInterface::class);
        $innerStep->method('execute')
            ->willReturn(StepResult::success(['child_key' => 'result_value']));

        $step = SubWorkflowStep::make($innerStep)
            ->mapOutput(['child_key' => 'parent_key']);

        $context = new WorkflowContext;
        $step->execute($context);

        $this->assertSame('result_value', $context->get('parent_key'));
    }

    #[Test]
    public function it_maps_output_with_closure(): void
    {
        $innerStep = $this->createMock(StepInterface::class);
        $innerStep->method('execute')
            ->willReturn(StepResult::success(['value' => 42]));

        $step = SubWorkflowStep::make($innerStep)
            ->mapOutput(function (StepResult $result, WorkflowContext $ctx) {
                $ctx->set('doubled', $result->output['value'] * 2);
            });

        $context = new WorkflowContext;
        $step->execute($context);

        $this->assertSame(84, $context->get('doubled'));
    }

    #[Test]
    public function it_runs_in_isolated_context(): void
    {
        $capturedContext = null;

        $innerStep = $this->createMock(StepInterface::class);
        $innerStep->method('execute')
            ->willReturnCallback(function (WorkflowContext $ctx) use (&$capturedContext) {
                $capturedContext = $ctx;

                return StepResult::success([]);
            });

        $step = SubWorkflowStep::make($innerStep)->isolated();

        $context = new WorkflowContext;
        $context->set('parent_only', 'should not be visible');

        $step->execute($context);

        $this->assertFalse($capturedContext->has('parent_only'));
    }

    #[Test]
    public function it_does_not_map_output_on_failure(): void
    {
        $innerStep = $this->createMock(StepInterface::class);
        $innerStep->method('execute')
            ->willReturn(StepResult::failed('Sub-workflow failed'));

        $step = SubWorkflowStep::make($innerStep)
            ->mapOutput(['key' => 'parent_key']);

        $context = new WorkflowContext;
        $step->execute($context);

        $this->assertFalse($context->has('parent_key'));
    }

    #[Test]
    public function it_propagates_failure_from_sub_workflow(): void
    {
        $innerStep = $this->createMock(StepInterface::class);
        $innerStep->method('execute')
            ->willReturn(StepResult::failed('Inner failure'));

        $step = SubWorkflowStep::make($innerStep);

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isFailed());
    }
}
