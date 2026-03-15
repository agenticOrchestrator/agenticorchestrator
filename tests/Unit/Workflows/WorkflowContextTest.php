<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows;

use AgenticOrchestrator\Workflows\WorkflowContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkflowContext::class)]
class WorkflowContextTest extends TestCase
{
    #[Test]
    public function it_stores_and_retrieves_input(): void
    {
        $context = new WorkflowContext(['name' => 'John', 'age' => 30]);

        $this->assertSame('John', $context->get('name'));
        $this->assertSame(30, $context->get('age'));
        $this->assertNull($context->get('missing'));
        $this->assertSame('default', $context->get('missing', 'default'));
    }

    #[Test]
    public function it_sets_values(): void
    {
        $context = new WorkflowContext;
        $context->set('key', 'value');

        $this->assertSame('value', $context->get('key'));
    }

    #[Test]
    public function it_merges_data(): void
    {
        $context = new WorkflowContext(['a' => 1]);
        $context->merge(['b' => 2, 'c' => 3]);

        $this->assertSame(1, $context->get('a'));
        $this->assertSame(2, $context->get('b'));
        $this->assertSame(3, $context->get('c'));
    }

    #[Test]
    public function it_tracks_step_outputs(): void
    {
        $context = new WorkflowContext;
        $context->set('step1', ['result' => 'data']);

        $this->assertSame(['result' => 'data'], $context->get('step1'));
        $this->assertArrayHasKey('step1', $context->getOutputs());
    }

    #[Test]
    public function it_tracks_completed_steps(): void
    {
        $context = new WorkflowContext;

        $this->assertFalse($context->isStepCompleted('step1'));

        $context->markStepCompleted('step1');

        $this->assertTrue($context->isStepCompleted('step1'));
        $this->assertContains('step1', $context->getCompletedSteps());
    }

    #[Test]
    public function it_tracks_failed_steps(): void
    {
        $context = new WorkflowContext;
        $context->markStepFailed('step1', 'Something went wrong', 'RuntimeException');

        $failedSteps = $context->getFailedSteps();

        $this->assertArrayHasKey('step1', $failedSteps);
        $this->assertSame('Something went wrong', $failedSteps['step1']['message']);
        $this->assertSame('RuntimeException', $failedSteps['step1']['exception']);
    }

    #[Test]
    public function it_stores_and_retrieves_metadata(): void
    {
        $context = new WorkflowContext([], ['execution_id' => 'abc123']);

        $this->assertSame('abc123', $context->getMeta('execution_id'));
        $this->assertNull($context->getMeta('missing'));
    }

    #[Test]
    public function it_creates_immutable_copy_with_additional_data(): void
    {
        $original = new WorkflowContext(['a' => 1]);
        $copy = $original->with(['b' => 2]);

        $this->assertNull($original->get('b'));
        $this->assertSame(2, $copy->get('b'));
        $this->assertSame(1, $copy->get('a'));
    }

    #[Test]
    public function it_serializes_to_state(): void
    {
        $context = new WorkflowContext(['input' => 'value'], ['meta' => 'data']);
        $context->set('key', 'value');
        $context->markStepCompleted('step1');

        $state = $context->getState();

        $this->assertArrayHasKey('data', $state);
        $this->assertArrayHasKey('metadata', $state);
        $this->assertArrayHasKey('completed_steps', $state);
    }

    #[Test]
    public function it_restores_from_state(): void
    {
        $state = [
            'data' => ['key' => 'value'],
            'outputs' => ['step1' => ['result' => 'data']],
            'metadata' => ['execution_id' => 'abc123'],
            'completed_steps' => ['step1'],
            'failed_steps' => [],
        ];

        $context = WorkflowContext::fromState($state);

        $this->assertSame('value', $context->get('key'));
        $this->assertSame('abc123', $context->getMeta('execution_id'));
        $this->assertTrue($context->isStepCompleted('step1'));
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $context = new WorkflowContext(['input' => 'value']);
        $context->set('output', 'result');
        $array = $context->toArray();

        // toArray() returns merged input + data
        $this->assertArrayHasKey('input', $array);
        $this->assertSame('value', $array['input']);
        $this->assertArrayHasKey('output', $array);
        $this->assertSame('result', $array['output']);
    }
}
