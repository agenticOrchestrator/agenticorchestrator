<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows\Steps;

use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\Steps\LoopStep;
use AgenticOrchestrator\Workflows\WorkflowContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoopStep::class)]
class LoopStepTest extends TestCase
{
    #[Test]
    public function it_iterates_over_collection_from_context(): void
    {
        $results = [];
        $step = LoopStep::forEach('items', function ($item, $index) use (&$results) {
            $results[] = "{$index}:{$item}";

            return $item;
        });

        $context = new WorkflowContext;
        $context->set('items', ['a', 'b', 'c']);

        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(['0:a', '1:b', '2:c'], $results);
        $this->assertSame(['a', 'b', 'c'], $result->output);
    }

    #[Test]
    public function it_iterates_with_closure_collection(): void
    {
        $step = LoopStep::forEach(
            fn (WorkflowContext $ctx) => $ctx->get('numbers', []),
            fn ($item) => $item * 2
        );

        $context = new WorkflowContext;
        $context->set('numbers', [1, 2, 3]);

        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame([2, 4, 6], $result->output);
    }

    #[Test]
    public function it_loops_fixed_number_of_times(): void
    {
        $step = LoopStep::times(5, fn ($i) => $i * 10);

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame([0, 10, 20, 30, 40], $result->output);
        $this->assertSame(5, $result->getMeta('iterations'));
    }

    #[Test]
    public function it_stops_on_failure_by_default(): void
    {
        $step = LoopStep::times(5, function ($i) {
            if ($i === 2) {
                return StepResult::failed('Error at 2');
            }

            return $i;
        });

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isFailed());
        $this->assertStringContainsString('iteration 2', $result->message);
    }

    #[Test]
    public function it_can_continue_on_failure(): void
    {
        $step = LoopStep::times(3, function ($i) {
            if ($i === 1) {
                return StepResult::failed('Error at 1');
            }

            return $i;
        })->continueOnFailure();

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(1, $result->getMeta('failures'));
    }

    #[Test]
    public function it_sets_loop_variables_in_context(): void
    {
        $capturedItems = [];

        $step = LoopStep::forEach('items', function ($item, $index, $context) use (&$capturedItems) {
            $capturedItems[] = [
                'item' => $context->get('current_item'),
                'index' => $context->get('current_index'),
            ];

            return $item;
        })->as('current_item', 'current_index');

        $context = new WorkflowContext;
        $context->set('items', ['x', 'y']);

        $step->execute($context);

        $this->assertSame('x', $capturedItems[0]['item']);
        $this->assertSame(0, $capturedItems[0]['index']);
        $this->assertSame('y', $capturedItems[1]['item']);
        $this->assertSame(1, $capturedItems[1]['index']);

        // Loop variables cleaned up
        $this->assertFalse($context->has('current_item'));
        $this->assertFalse($context->has('current_index'));
    }

    #[Test]
    public function it_handles_empty_collection(): void
    {
        $step = LoopStep::forEach('items', fn ($item) => $item);

        $context = new WorkflowContext;
        $context->set('items', []);

        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame([], $result->output);
    }

    #[Test]
    public function it_handles_zero_iterations(): void
    {
        $step = LoopStep::times(0, fn ($i) => $i);

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame([], $result->output);
    }
}
