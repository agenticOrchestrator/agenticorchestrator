<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows;

use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use AgenticOrchestrator\Workflows\WorkflowDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkflowDefinition::class)]
class WorkflowDefinitionTest extends TestCase
{
    #[Test]
    public function it_creates_empty_definition(): void
    {
        $definition = WorkflowDefinition::create();

        $this->assertInstanceOf(WorkflowDefinition::class, $definition);
        $this->assertEmpty($definition->getSteps());
        $this->assertSame(0, $definition->count());
    }

    #[Test]
    public function it_adds_step_with_name(): void
    {
        $step = $this->createMockStep('test-step');

        $definition = WorkflowDefinition::create()
            ->addStep('my-step', $step);

        $this->assertSame(1, $definition->count());
        $this->assertTrue($definition->hasStep('my-step'));
        $this->assertSame($step, $definition->getStep('my-step'));
    }

    #[Test]
    public function it_adds_step_directly(): void
    {
        $step = $this->createMockStep('direct-step');

        $definition = WorkflowDefinition::create()
            ->addStep($step);

        $this->assertSame(1, $definition->count());
        $this->assertTrue($definition->hasStep('direct-step'));
    }

    #[Test]
    public function it_adds_callback_step(): void
    {
        $definition = WorkflowDefinition::create()
            ->callback('transform', function (WorkflowContext $ctx) {
                return ['transformed' => true];
            });

        $this->assertTrue($definition->hasStep('transform'));
        $this->assertSame(1, $definition->count());
    }

    #[Test]
    public function it_sets_metadata(): void
    {
        $definition = WorkflowDefinition::create()
            ->name('My Workflow')
            ->description('A test workflow')
            ->metadata(['version' => '1.0']);

        $metadata = $definition->getMetadata();

        $this->assertSame('My Workflow', $metadata['name']);
        $this->assertSame('A test workflow', $metadata['description']);
        $this->assertSame('1.0', $metadata['version']);
    }

    #[Test]
    public function it_defines_step_dependencies(): void
    {
        $step1 = $this->createMockStep('step1');
        $step2 = $this->createMockStep('step2');

        $definition = WorkflowDefinition::create()
            ->addStep('step1', $step1)
            ->addStep('step2', $step2)
            ->after('step2', ['step1']);

        $dependencies = $definition->getDependencies();

        $this->assertArrayHasKey('step2', $dependencies);
        $this->assertContains('step1', $dependencies['step2']);
    }

    #[Test]
    public function it_returns_null_for_missing_step(): void
    {
        $definition = WorkflowDefinition::create();

        $this->assertNull($definition->getStep('nonexistent'));
        $this->assertFalse($definition->hasStep('nonexistent'));
    }

    #[Test]
    public function it_gets_all_steps(): void
    {
        $step1 = $this->createMockStep('step1');
        $step2 = $this->createMockStep('step2');

        $definition = WorkflowDefinition::create()
            ->addStep($step1)
            ->addStep($step2);

        $steps = $definition->getSteps();

        $this->assertCount(2, $steps);
    }

    #[Test]
    public function it_gets_named_steps(): void
    {
        $step1 = $this->createMockStep('step1');
        $step2 = $this->createMockStep('step2');

        $definition = WorkflowDefinition::create()
            ->addStep('first', $step1)
            ->addStep('second', $step2);

        $namedSteps = $definition->getNamedSteps();

        $this->assertArrayHasKey('first', $namedSteps);
        $this->assertArrayHasKey('second', $namedSteps);
    }

    #[Test]
    public function it_throws_when_step_missing_for_named_add(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Step instance required');

        WorkflowDefinition::create()->addStep('name', null);
    }

    /**
     * Create a mock step for testing.
     */
    private function createMockStep(string $name): StepInterface
    {
        $step = $this->createMock(StepInterface::class);
        $step->method('getName')->willReturn($name);
        $step->method('execute')->willReturn(StepResult::success(['mocked' => true]));

        return $step;
    }
}
