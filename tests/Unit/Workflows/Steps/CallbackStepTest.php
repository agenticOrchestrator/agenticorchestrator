<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows\Steps;

use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\Steps\CallbackStep;
use AgenticOrchestrator\Workflows\WorkflowContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(CallbackStep::class)]
class CallbackStepTest extends TestCase
{
    #[Test]
    public function it_executes_a_callback_and_returns_success(): void
    {
        $step = CallbackStep::make(fn (WorkflowContext $ctx) => ['result' => 'done']);

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(['result' => 'done'], $result->output);
    }

    #[Test]
    public function it_receives_workflow_context(): void
    {
        $step = CallbackStep::make(function (WorkflowContext $ctx) {
            return $ctx->get('input_value').'_processed';
        });

        $context = new WorkflowContext;
        $context->set('input_value', 'hello');

        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('hello_processed', $result->output);
    }

    #[Test]
    public function it_can_return_a_step_result_directly(): void
    {
        $step = CallbackStep::make(fn () => StepResult::failed('Intentional failure'));

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isFailed());
        $this->assertSame('Intentional failure', $result->message);
    }

    #[Test]
    public function it_wraps_exceptions_in_failed_result(): void
    {
        $step = CallbackStep::make(function () {
            throw new RuntimeException('Something broke');
        });

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isFailed());
        $this->assertSame('Something broke', $result->message);
    }

    #[Test]
    public function it_stores_output_when_output_key_is_set(): void
    {
        $step = CallbackStep::make(fn () => 'stored_value')
            ->outputAs('my_output');

        $context = new WorkflowContext;
        $step->execute($context);

        $this->assertSame('stored_value', $context->get('my_output'));
    }

    #[Test]
    public function it_has_correct_auto_generated_name(): void
    {
        $step = CallbackStep::make(fn () => null);

        $this->assertSame('callback', $step->getName());
    }

    #[Test]
    public function it_supports_custom_name(): void
    {
        $step = CallbackStep::make(fn () => null)->as('my_callback');

        $this->assertSame('my_callback', $step->getName());
    }

    #[Test]
    public function it_can_be_created_via_constructor(): void
    {
        $step = new CallbackStep(fn () => 'from_constructor');

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('from_constructor', $result->output);
    }

    #[Test]
    public function it_handles_null_return(): void
    {
        $step = CallbackStep::make(fn () => null);

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertNull($result->output);
    }

    #[Test]
    public function it_checks_dependencies_before_executing(): void
    {
        $executed = false;
        $step = CallbackStep::make(function () use (&$executed) {
            $executed = true;

            return 'done';
        })->dependsOn(['required_key']);

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isFailed());
        $this->assertStringContainsString('required_key', $result->message);
        $this->assertFalse($executed);
    }

    #[Test]
    public function it_executes_when_dependencies_are_met(): void
    {
        $step = CallbackStep::make(fn () => 'ok')
            ->dependsOn(['required_key']);

        $context = new WorkflowContext;
        $context->set('required_key', 'present');

        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('ok', $result->output);
    }

    #[Test]
    public function it_can_modify_context_from_callback(): void
    {
        $step = CallbackStep::make(function (WorkflowContext $ctx) {
            $ctx->set('side_effect', 'set_by_callback');

            return 'done';
        });

        $context = new WorkflowContext;
        $step->execute($context);

        $this->assertSame('set_by_callback', $context->get('side_effect'));
    }
}
