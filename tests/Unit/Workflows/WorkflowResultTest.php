<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows;

use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use AgenticOrchestrator\Workflows\WorkflowResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(WorkflowResult::class)]
class WorkflowResultTest extends TestCase
{
    #[Test]
    public function it_creates_successful_result(): void
    {
        $context = new WorkflowContext;
        $result = new WorkflowResult(
            executionId: 'exec-123',
            status: StepResult::STATUS_SUCCESS,
            output: ['data' => 'value'],
            context: $context,
            duration: 150.5,
        );

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isFailed());
        $this->assertFalse($result->isPaused());
        $this->assertSame('exec-123', $result->executionId);
        $this->assertSame(150.5, $result->duration);
    }

    #[Test]
    public function it_creates_failed_result(): void
    {
        $context = new WorkflowContext;
        $exception = new RuntimeException('Test error');
        $result = new WorkflowResult(
            executionId: 'exec-123',
            status: StepResult::STATUS_FAILED,
            output: null,
            context: $context,
            duration: 50.0,
            error: 'Something went wrong',
            exception: $exception,
        );

        $this->assertTrue($result->isFailed());
        $this->assertFalse($result->isSuccess());
        $this->assertSame('Something went wrong', $result->error);
        $this->assertSame($exception, $result->exception);
    }

    #[Test]
    public function it_creates_paused_result(): void
    {
        $context = new WorkflowContext;
        $result = new WorkflowResult(
            executionId: 'exec-123',
            status: StepResult::STATUS_WAITING,
            output: null,
            context: $context,
            duration: 100.0,
        );

        $this->assertTrue($result->isPaused());
        $this->assertFalse($result->isSuccess());
        $this->assertFalse($result->isFailed());
    }

    #[Test]
    public function it_gets_output(): void
    {
        $context = new WorkflowContext;
        $result = new WorkflowResult(
            executionId: 'exec-123',
            status: StepResult::STATUS_SUCCESS,
            output: ['key' => 'value', 'number' => 42],
            context: $context,
            duration: 100.0,
        );

        $this->assertSame(['key' => 'value', 'number' => 42], $result->getOutput());
        $this->assertSame('value', $result->get('key'));
        $this->assertSame(42, $result->get('number'));
        $this->assertNull($result->get('missing'));
        $this->assertSame('default', $result->get('missing', 'default'));
    }

    #[Test]
    public function it_gets_completed_steps_from_context(): void
    {
        $context = new WorkflowContext;
        $context->markStepCompleted('step1');
        $context->markStepCompleted('step2');

        $result = new WorkflowResult(
            executionId: 'exec-123',
            status: StepResult::STATUS_SUCCESS,
            output: null,
            context: $context,
            duration: 100.0,
        );

        $completedSteps = $result->getCompletedSteps();

        $this->assertContains('step1', $completedSteps);
        $this->assertContains('step2', $completedSteps);
    }

    #[Test]
    public function it_gets_failed_steps_from_context(): void
    {
        $context = new WorkflowContext;
        $context->markStepFailed('step1', 'Error message', 'RuntimeException');

        $result = new WorkflowResult(
            executionId: 'exec-123',
            status: StepResult::STATUS_FAILED,
            output: null,
            context: $context,
            duration: 100.0,
        );

        $failedSteps = $result->getFailedSteps();

        $this->assertArrayHasKey('step1', $failedSteps);
        $this->assertSame('Error message', $failedSteps['step1']['message']);
    }

    #[Test]
    public function it_gets_metadata(): void
    {
        $context = new WorkflowContext;
        $result = new WorkflowResult(
            executionId: 'exec-123',
            status: StepResult::STATUS_SUCCESS,
            output: null,
            context: $context,
            duration: 100.0,
            metadata: ['steps_completed' => 5],
        );

        $this->assertSame(5, $result->getMeta('steps_completed'));
        $this->assertNull($result->getMeta('missing'));
    }

    #[Test]
    public function it_gets_state_for_persistence(): void
    {
        $context = new WorkflowContext(['input' => 'data']);
        $context->set('processed', true);
        $context->markStepCompleted('step1');

        $result = new WorkflowResult(
            executionId: 'exec-123',
            status: StepResult::STATUS_SUCCESS,
            output: null,
            context: $context,
            duration: 100.0,
        );

        $state = $result->getState();

        $this->assertArrayHasKey('data', $state);
        $this->assertArrayHasKey('completed_steps', $state);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $context = new WorkflowContext;
        $result = new WorkflowResult(
            executionId: 'exec-123',
            status: StepResult::STATUS_SUCCESS,
            output: ['data' => 'value'],
            context: $context,
            duration: 150.5,
            metadata: ['key' => 'meta'],
        );

        $array = $result->toArray();

        $this->assertSame('exec-123', $array['execution_id']);
        $this->assertSame('success', $array['status']);
        $this->assertSame(['data' => 'value'], $array['output']);
        $this->assertSame(150.5, $array['duration_ms']);
        $this->assertSame(['key' => 'meta'], $array['metadata']);
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $context = new WorkflowContext;
        $result = new WorkflowResult(
            executionId: 'exec-123',
            status: StepResult::STATUS_SUCCESS,
            output: ['data' => 'value'],
            context: $context,
            duration: 100.0,
        );

        $json = json_encode($result);

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('exec-123', $decoded['execution_id']);
        $this->assertSame('success', $decoded['status']);
    }
}
