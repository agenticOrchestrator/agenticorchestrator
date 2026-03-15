<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows\Patterns;

use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\Patterns\MapReducePattern;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MapReducePattern::class)]
class MapReducePatternTest extends TestCase
{
    #[Test]
    public function it_maps_and_reduces_collection(): void
    {
        $pattern = MapReducePattern::make(
            'numbers',
            fn ($item) => $item * 2,
            fn ($carry, $value) => ($carry ?? 0) + $value,
            0
        );

        $context = new WorkflowContext;
        $context->set('numbers', [1, 2, 3, 4, 5]);

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(30, $result->output); // (1+2+3+4+5)*2 = 30
        $this->assertSame(5, $result->getMeta('mapped_count'));
    }

    #[Test]
    public function it_maps_with_closure_collection(): void
    {
        $pattern = MapReducePattern::make(
            fn (WorkflowContext $ctx) => $ctx->get('items', []),
            fn ($item) => strtoupper($item),
            fn ($carry, $value) => ($carry ?? '').$value,
            ''
        );

        $context = new WorkflowContext;
        $context->set('items', ['a', 'b', 'c']);

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('ABC', $result->output);
    }

    #[Test]
    public function it_maps_with_step_interface(): void
    {
        $mapperStep = $this->createMock(StepInterface::class);
        $mapperStep->method('execute')
            ->willReturnCallback(function (WorkflowContext $ctx) {
                $item = $ctx->get('map_item');

                return StepResult::success($item * 10);
            });

        $pattern = MapReducePattern::make(
            'values',
            $mapperStep,
            fn ($carry, $value) => array_merge($carry ?? [], [$value]),
            []
        );

        $context = new WorkflowContext;
        $context->set('values', [1, 2, 3]);

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame([10, 20, 30], $result->output);
    }

    #[Test]
    public function it_fails_when_mapper_exceeds_failure_threshold(): void
    {
        $callCount = 0;
        $pattern = MapReducePattern::make(
            'items',
            function () use (&$callCount) {
                $callCount++;
                throw new \RuntimeException('Mapper error');
            },
            fn ($carry, $value) => $carry,
            null
        )->allowFailures(1);

        $context = new WorkflowContext;
        $context->set('items', [1, 2, 3]);

        $result = $pattern->execute($context);

        $this->assertTrue($result->isFailed());
        $this->assertSame(2, $callCount); // Fails on second failure
    }

    #[Test]
    public function it_allows_specified_number_of_failures(): void
    {
        $pattern = MapReducePattern::make(
            'items',
            function ($item) {
                if ($item === 'bad') {
                    throw new \RuntimeException('Bad item');
                }

                return strtoupper($item);
            },
            fn ($carry, $value) => ($carry ?? '').$value,
            ''
        )->allowFailures(2);

        $context = new WorkflowContext;
        $context->set('items', ['a', 'bad', 'b', 'bad', 'c']);

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('ABC', $result->output);
        $this->assertSame(2, $result->getMeta('failures'));
    }

    #[Test]
    public function it_handles_empty_collection(): void
    {
        $pattern = MapReducePattern::make(
            'items',
            fn ($item) => $item,
            fn ($carry, $value) => $carry,
            'default'
        );

        $context = new WorkflowContext;
        $context->set('items', []);

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('default', $result->output);
    }

    #[Test]
    public function it_sets_pattern_name(): void
    {
        $pattern = MapReducePattern::make(
            'items',
            fn ($item) => $item,
            fn ($carry, $value) => $carry,
        )->as('word-count');

        $this->assertSame('word-count', $pattern->getName());
    }

    #[Test]
    public function it_cleans_up_map_context_variables(): void
    {
        $pattern = MapReducePattern::make(
            'items',
            fn ($item) => $item,
            fn ($carry, $value) => $carry,
        );

        $context = new WorkflowContext;
        $context->set('items', [1]);

        $pattern->execute($context);

        $this->assertFalse($context->has('map_item'));
        $this->assertFalse($context->has('map_index'));
        $this->assertFalse($context->has('map_key'));
    }
}
