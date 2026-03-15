<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows\Steps;

use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\Steps\WhileStep;
use AgenticOrchestrator\Workflows\WorkflowContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WhileStep::class)]
class WhileStepTest extends TestCase
{
    #[Test]
    public function it_loops_while_condition_is_true(): void
    {
        $step = WhileStep::make(
            fn (WorkflowContext $ctx) => ($ctx->get('counter', 0)) < 3,
            function ($i, $ctx) {
                $ctx->set('counter', $i + 1);

                return $i;
            }
        );

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame([0, 1, 2], $result->output);
        $this->assertSame(3, $result->getMeta('iterations'));
    }

    #[Test]
    public function it_does_not_execute_if_condition_is_initially_false(): void
    {
        $step = WhileStep::make(
            fn () => false,
            fn ($i) => $i
        );

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame([], $result->output);
        $this->assertSame(0, $result->getMeta('iterations'));
    }

    #[Test]
    public function it_fails_when_max_iterations_exceeded(): void
    {
        $step = WhileStep::make(
            fn () => true,
            fn ($i) => $i
        )->maxIterations(5);

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isFailed());
        $this->assertStringContainsString('maximum iterations (5)', $result->message);
    }

    #[Test]
    public function it_stops_on_body_failure(): void
    {
        $step = WhileStep::make(
            fn () => true,
            function ($i) {
                if ($i === 2) {
                    return StepResult::failed('Error at 2');
                }

                return $i;
            }
        );

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isFailed());
        $this->assertStringContainsString('iteration 2', $result->message);
    }

    #[Test]
    public function it_sets_custom_max_iterations(): void
    {
        $step = WhileStep::make(
            fn () => true,
            fn ($i) => $i
        )->maxIterations(10);

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isFailed());
        $this->assertStringContainsString('maximum iterations (10)', $result->message);
    }

    #[Test]
    public function it_uses_context_for_condition_evaluation(): void
    {
        $step = WhileStep::make(
            fn (WorkflowContext $ctx) => $ctx->get('should_continue', true),
            function ($i, $ctx) {
                if ($i >= 2) {
                    $ctx->set('should_continue', false);
                }

                return "iteration-{$i}";
            }
        );

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(['iteration-0', 'iteration-1', 'iteration-2'], $result->output);
    }
}
