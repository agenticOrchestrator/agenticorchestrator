<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows;

use AgenticOrchestrator\Workflows\StepResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(StepResult::class)]
class StepResultTest extends TestCase
{
    #[Test]
    public function it_creates_success_result(): void
    {
        $result = StepResult::success(['data' => 'value']);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isFailed());
        $this->assertFalse($result->shouldPause());
        $this->assertSame(['data' => 'value'], $result->output);
        $this->assertSame(StepResult::STATUS_SUCCESS, $result->status);
    }

    #[Test]
    public function it_creates_failed_result(): void
    {
        $exception = new RuntimeException('Test error');
        $result = StepResult::failed('Something went wrong', $exception);

        $this->assertTrue($result->isFailed());
        $this->assertFalse($result->isSuccess());
        $this->assertSame('Something went wrong', $result->message);
        $this->assertSame($exception, $result->exception);
    }

    #[Test]
    public function it_creates_skipped_result(): void
    {
        $result = StepResult::skipped('Condition not met');

        $this->assertSame(StepResult::STATUS_SKIPPED, $result->status);
        $this->assertSame('Condition not met', $result->message);
    }

    #[Test]
    public function it_creates_pending_result(): void
    {
        $result = StepResult::pending('Waiting for input');

        $this->assertSame(StepResult::STATUS_PENDING, $result->status);
        $this->assertTrue($result->shouldPause());
    }

    #[Test]
    public function it_creates_waiting_result(): void
    {
        $result = StepResult::waiting('Human approval needed', ['step' => 'review']);

        $this->assertSame(StepResult::STATUS_WAITING, $result->status);
        $this->assertTrue($result->shouldPause());
        $this->assertSame('review', $result->output['step']);
    }

    #[Test]
    public function it_stores_and_retrieves_metadata(): void
    {
        $result = StepResult::success(['data' => 'value'], ['steps' => 5]);

        $this->assertSame(5, $result->getMeta('steps'));
        $this->assertNull($result->getMeta('missing'));
        $this->assertSame('default', $result->getMeta('missing', 'default'));
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $result = StepResult::success(['data' => 'value'], ['key' => 'meta']);
        $array = $result->toArray();

        $this->assertSame('success', $array['status']);
        $this->assertSame(['data' => 'value'], $array['output']);
        $this->assertSame(['key' => 'meta'], $array['metadata']);
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $result = StepResult::success(['data' => 'value']);
        $json = json_encode($result);

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('success', $decoded['status']);
    }
}
