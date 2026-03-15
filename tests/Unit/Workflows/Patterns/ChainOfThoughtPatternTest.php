<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows\Patterns;

use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\Patterns\ChainOfThoughtPattern;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChainOfThoughtPattern::class)]
class ChainOfThoughtPatternTest extends TestCase
{
    #[Test]
    public function it_executes_reasoning_steps_sequentially(): void
    {
        $step1 = $this->createStep('analyze', StepResult::success('Found 3 issues'));
        $step2 = $this->createStep('prioritize', StepResult::success('Issue A is critical'));
        $step3 = $this->createStep('recommend', StepResult::success('Fix A first'));

        $pattern = ChainOfThoughtPattern::make([$step1, $step2, $step3]);

        $context = new WorkflowContext;
        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame([
            'analyze' => 'Found 3 issues',
            'prioritize' => 'Issue A is critical',
            'recommend' => 'Fix A first',
        ], $result->output);
        $this->assertSame(3, $result->getMeta('steps_completed'));
    }

    #[Test]
    public function it_stores_thought_chain_in_context(): void
    {
        $step1 = $this->createStep('step1', StepResult::success('thought 1'));
        $step2 = $this->createStep('step2', StepResult::success('thought 2'));

        $pattern = ChainOfThoughtPattern::make([$step1, $step2]);

        $context = new WorkflowContext;
        $pattern->execute($context);

        $this->assertSame('thought 1', $context->get('thought_step1'));
        $this->assertSame('thought 2', $context->get('thought_step2'));
    }

    #[Test]
    public function it_applies_reflection_after_each_step(): void
    {
        $step1 = $this->createStep('analyze', StepResult::success('raw analysis'));
        $step2 = $this->createStep('conclude', StepResult::success('raw conclusion'));

        $pattern = ChainOfThoughtPattern::make([$step1, $step2])
            ->withReflection(function (StepResult $result, array $chain) {
                return StepResult::success('reflected: '.$result->output);
            });

        $context = new WorkflowContext;
        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('reflected: raw analysis', $result->output['analyze']);
        $this->assertSame('reflected: raw conclusion', $result->output['conclude']);
    }

    #[Test]
    public function it_stops_on_failure(): void
    {
        $step1 = $this->createStep('step1', StepResult::success('ok'));
        $step2 = $this->createStep('step2', StepResult::failed('Reasoning error'));
        $step3 = $this->createStep('step3', StepResult::success('should not run'));

        $step3->expects($this->never())->method('execute');

        $pattern = ChainOfThoughtPattern::make([$step1, $step2, $step3]);

        $context = new WorkflowContext;
        $result = $pattern->execute($context);

        $this->assertTrue($result->isFailed());
        $this->assertStringContainsString('step2', $result->message);
    }

    #[Test]
    public function it_pauses_on_waiting_step(): void
    {
        $step1 = $this->createStep('step1', StepResult::success('ok'));
        $step2 = $this->createStep('step2', StepResult::waiting('Needs human input'));

        $pattern = ChainOfThoughtPattern::make([$step1, $step2]);

        $context = new WorkflowContext;
        $result = $pattern->execute($context);

        $this->assertTrue($result->shouldPause());
    }

    #[Test]
    public function it_handles_empty_steps(): void
    {
        $pattern = ChainOfThoughtPattern::make();

        $context = new WorkflowContext;
        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame([], $result->output);
    }

    #[Test]
    public function it_fails_when_exceeding_max_steps(): void
    {
        $steps = [];
        for ($i = 0; $i < 5; $i++) {
            $steps[] = $this->createStep("step{$i}", StepResult::success("thought {$i}"));
        }

        $pattern = ChainOfThoughtPattern::make($steps)->maxSteps(3);

        $context = new WorkflowContext;
        $result = $pattern->execute($context);

        $this->assertTrue($result->isFailed());
        $this->assertStringContainsString('maximum steps (3)', $result->message);
    }

    #[Test]
    public function it_includes_thought_chain_in_metadata(): void
    {
        $step1 = $this->createStep('step1', StepResult::success('output1'));

        $pattern = ChainOfThoughtPattern::make([$step1]);

        $context = new WorkflowContext;
        $result = $pattern->execute($context);

        $chain = $result->getMeta('thought_chain');
        $this->assertArrayHasKey('step1', $chain);
        $this->assertSame('output1', $chain['step1']['output']);
    }

    #[Test]
    public function it_can_exclude_thought_chain(): void
    {
        $step1 = $this->createStep('step1', StepResult::success('output1'));

        $pattern = ChainOfThoughtPattern::make([$step1])->excludeChain();

        $context = new WorkflowContext;
        $result = $pattern->execute($context);

        $this->assertNull($result->getMeta('thought_chain'));
    }

    #[Test]
    public function it_sets_pattern_name(): void
    {
        $pattern = ChainOfThoughtPattern::make()->as('reasoning-chain');

        $this->assertSame('reasoning-chain', $pattern->getName());
    }

    #[Test]
    public function it_adds_steps_fluently(): void
    {
        $step1 = $this->createStep('step1', StepResult::success('ok'));
        $step2 = $this->createStep('step2', StepResult::success('ok'));

        $pattern = ChainOfThoughtPattern::make()
            ->addStep($step1)
            ->addStep($step2);

        $context = new WorkflowContext;
        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(2, $result->getMeta('steps_completed'));
    }

    #[Test]
    public function it_passes_thought_chain_to_reflector(): void
    {
        $capturedChains = [];

        $step1 = $this->createStep('step1', StepResult::success('first'));
        $step2 = $this->createStep('step2', StepResult::success('second'));

        $pattern = ChainOfThoughtPattern::make([$step1, $step2])
            ->withReflection(function (StepResult $result, array $chain) use (&$capturedChains) {
                $capturedChains[] = $chain;

                return null; // Accept as-is
            });

        $context = new WorkflowContext;
        $pattern->execute($context);

        // First reflection has empty chain, second has step1
        $this->assertEmpty($capturedChains[0]);
        $this->assertArrayHasKey('step1', $capturedChains[1]);
    }

    #[Test]
    public function it_cleans_up_temporary_context(): void
    {
        $step1 = $this->createStep('step1', StepResult::success('ok'));

        $pattern = ChainOfThoughtPattern::make([$step1]);

        $context = new WorkflowContext;
        $pattern->execute($context);

        $this->assertFalse($context->has('thought_chain'));
        $this->assertFalse($context->has('thought_step'));
    }

    private function createStep(string $name, StepResult $result): StepInterface
    {
        $step = $this->createMock(StepInterface::class);
        $step->method('getName')->willReturn($name);
        $step->method('getDependencies')->willReturn([]);
        $step->expects($this->any())
            ->method('execute')
            ->willReturn($result);

        return $step;
    }
}
