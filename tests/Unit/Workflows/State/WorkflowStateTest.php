<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows\State;

use AgenticOrchestrator\Workflows\State\WorkflowState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkflowState::class)]
class WorkflowStateTest extends TestCase
{
    #[Test]
    public function it_has_correct_table_name(): void
    {
        $state = new WorkflowState;

        $this->assertSame('agent_workflow_states', $state->getTable());
    }

    #[Test]
    public function it_has_correct_fillable_attributes(): void
    {
        $state = new WorkflowState;
        $fillable = $state->getFillable();

        $this->assertContains('execution_id', $fillable);
        $this->assertContains('workflow_class', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('input', $fillable);
        $this->assertContains('state', $fillable);
        $this->assertContains('metadata', $fillable);
        $this->assertContains('error', $fillable);
        $this->assertContains('paused_at_step', $fillable);
        $this->assertContains('duration_ms', $fillable);
        $this->assertContains('tenant_id', $fillable);
        $this->assertContains('completed_at', $fillable);
    }

    #[Test]
    public function it_casts_input_to_array(): void
    {
        $state = new WorkflowState;
        $casts = $state->getCasts();

        $this->assertSame('array', $casts['input']);
    }

    #[Test]
    public function it_casts_state_to_array(): void
    {
        $state = new WorkflowState;
        $casts = $state->getCasts();

        $this->assertSame('array', $casts['state']);
    }

    #[Test]
    public function it_casts_metadata_to_array(): void
    {
        $state = new WorkflowState;
        $casts = $state->getCasts();

        $this->assertSame('array', $casts['metadata']);
    }

    #[Test]
    public function it_casts_duration_ms_to_float(): void
    {
        $state = new WorkflowState;
        $casts = $state->getCasts();

        $this->assertSame('float', $casts['duration_ms']);
    }

    #[Test]
    public function it_casts_completed_at_to_datetime(): void
    {
        $state = new WorkflowState;
        $casts = $state->getCasts();

        $this->assertSame('datetime', $casts['completed_at']);
    }

    #[Test]
    public function it_defines_status_constants(): void
    {
        $this->assertSame('pending', WorkflowState::STATUS_PENDING);
        $this->assertSame('running', WorkflowState::STATUS_RUNNING);
        $this->assertSame('paused', WorkflowState::STATUS_PAUSED);
        $this->assertSame('completed', WorkflowState::STATUS_COMPLETED);
        $this->assertSame('failed', WorkflowState::STATUS_FAILED);
        $this->assertSame('cancelled', WorkflowState::STATUS_CANCELLED);
    }

    #[Test]
    public function it_identifies_resumable_state(): void
    {
        $state = new WorkflowState;
        $state->status = WorkflowState::STATUS_PAUSED;

        $this->assertTrue($state->isResumable());
    }

    #[Test]
    public function it_identifies_non_resumable_states(): void
    {
        $state = new WorkflowState;

        $state->status = WorkflowState::STATUS_PENDING;
        $this->assertFalse($state->isResumable());

        $state->status = WorkflowState::STATUS_RUNNING;
        $this->assertFalse($state->isResumable());

        $state->status = WorkflowState::STATUS_COMPLETED;
        $this->assertFalse($state->isResumable());

        $state->status = WorkflowState::STATUS_FAILED;
        $this->assertFalse($state->isResumable());

        $state->status = WorkflowState::STATUS_CANCELLED;
        $this->assertFalse($state->isResumable());
    }

    #[Test]
    public function it_identifies_terminal_states(): void
    {
        $state = new WorkflowState;

        $state->status = WorkflowState::STATUS_COMPLETED;
        $this->assertTrue($state->isTerminal());

        $state->status = WorkflowState::STATUS_FAILED;
        $this->assertTrue($state->isTerminal());

        $state->status = WorkflowState::STATUS_CANCELLED;
        $this->assertTrue($state->isTerminal());
    }

    #[Test]
    public function it_identifies_non_terminal_states(): void
    {
        $state = new WorkflowState;

        $state->status = WorkflowState::STATUS_PENDING;
        $this->assertFalse($state->isTerminal());

        $state->status = WorkflowState::STATUS_RUNNING;
        $this->assertFalse($state->isTerminal());

        $state->status = WorkflowState::STATUS_PAUSED;
        $this->assertFalse($state->isTerminal());
    }

    #[Test]
    public function it_uses_uuids(): void
    {
        $state = new WorkflowState;

        // HasUuids trait sets the key type to string
        $this->assertSame('string', $state->getKeyType());
        $this->assertFalse($state->getIncrementing());
    }
}
