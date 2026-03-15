<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows\Patterns;

use AgenticOrchestrator\Agents\AgentResponse;
use AgenticOrchestrator\Contracts\AgentInterface;
use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\Patterns\SupervisorPattern;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SupervisorPattern::class)]
class SupervisorPatternTest extends TestCase
{
    #[Test]
    public function it_creates_with_static_make(): void
    {
        $supervisor = $this->createMock(AgentInterface::class);
        $pattern = SupervisorPattern::make($supervisor);

        $this->assertInstanceOf(SupervisorPattern::class, $pattern);
    }

    #[Test]
    public function it_completes_when_supervisor_decides_complete(): void
    {
        $supervisor = $this->createMock(AgentInterface::class);
        $supervisor->expects($this->once())
            ->method('respond')
            ->willReturn(new AgentResponse(
                content: '{"action": "complete", "response": "All done"}'
            ));

        $pattern = SupervisorPattern::make($supervisor);

        $context = new WorkflowContext;
        $context->set('task', 'Do something');

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('All done', $result->output['final_response']);
        $this->assertSame(1, $result->output['rounds']);
    }

    #[Test]
    public function it_escalates_when_supervisor_decides_to_escalate(): void
    {
        $supervisor = $this->createMock(AgentInterface::class);
        $supervisor->method('respond')->willReturn(new AgentResponse(
            content: '{"action": "escalate", "reason": "Need human decision"}'
        ));

        $pattern = SupervisorPattern::make($supervisor);

        $context = new WorkflowContext;
        $context->set('task', 'Complex decision');

        $result = $pattern->execute($context);

        $this->assertTrue($result->isWaiting());
        $this->assertSame('Need human decision', $result->message);
    }

    #[Test]
    public function it_delegates_to_worker_and_completes(): void
    {
        $worker = $this->createMock(AgentInterface::class);
        $worker->method('respond')->willReturn(new AgentResponse(
            content: 'Worker result content'
        ));

        $supervisor = $this->createMock(AgentInterface::class);
        $supervisor->expects($this->exactly(2))
            ->method('respond')
            ->willReturnOnConsecutiveCalls(
                new AgentResponse(content: '{"action": "delegate", "worker": "researcher", "task": "Research topic X"}'),
                new AgentResponse(content: '{"action": "complete", "response": "Final answer based on research"}')
            );

        $pattern = SupervisorPattern::make($supervisor, ['researcher' => $worker]);

        $context = new WorkflowContext;
        $context->set('task', 'Research and summarize');

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Final answer based on research', $result->output['final_response']);
        $this->assertSame(2, $result->output['rounds']);
        $this->assertNotEmpty($result->output['worker_results']);
    }

    #[Test]
    public function it_handles_unknown_worker(): void
    {
        $supervisor = $this->createMock(AgentInterface::class);
        $supervisor->expects($this->exactly(2))
            ->method('respond')
            ->willReturnOnConsecutiveCalls(
                new AgentResponse(content: '{"action": "delegate", "worker": "nonexistent", "task": "Do something"}'),
                new AgentResponse(content: '{"action": "complete", "response": "Done despite error"}')
            );

        $pattern = SupervisorPattern::make($supervisor);

        $context = new WorkflowContext;
        $context->set('task', 'Test');

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('round_1_nonexistent', $result->output['worker_results']);
        $this->assertArrayHasKey('error', $result->output['worker_results']['round_1_nonexistent']);
    }

    #[Test]
    public function it_fails_when_max_rounds_exceeded(): void
    {
        $worker = $this->createMock(AgentInterface::class);
        $worker->method('respond')->willReturn(new AgentResponse(content: 'Worker output'));

        $supervisor = $this->createMock(AgentInterface::class);
        $supervisor->method('respond')->willReturn(new AgentResponse(
            content: '{"action": "delegate", "worker": "worker1", "task": "Keep going"}'
        ));

        $pattern = SupervisorPattern::make($supervisor, ['worker1' => $worker])
            ->maxRounds(3);

        $context = new WorkflowContext;
        $context->set('task', 'Infinite loop task');

        $result = $pattern->execute($context);

        $this->assertTrue($result->isFailed());
        $this->assertStringContainsString('maximum rounds (3)', $result->message);
        $this->assertSame(3, $result->getMeta('rounds'));
    }

    #[Test]
    public function it_treats_non_json_response_as_complete(): void
    {
        $supervisor = $this->createMock(AgentInterface::class);
        $supervisor->method('respond')->willReturn(new AgentResponse(
            content: 'This is just a plain text response from the supervisor.'
        ));

        $pattern = SupervisorPattern::make($supervisor);

        $context = new WorkflowContext;
        $context->set('task', 'Simple query');

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(
            'This is just a plain text response from the supervisor.',
            $result->output['final_response']
        );
    }

    #[Test]
    public function it_adds_worker_fluently(): void
    {
        $supervisor = $this->createMock(AgentInterface::class);
        $supervisor->method('respond')->willReturn(new AgentResponse(
            content: '{"action": "complete", "response": "Done"}'
        ));

        $worker = $this->createMock(AgentInterface::class);

        $pattern = SupervisorPattern::make($supervisor)
            ->addWorker('analyst', $worker);

        $context = new WorkflowContext;
        $context->set('task', 'Test');

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_uses_custom_router_for_worker_execution(): void
    {
        $supervisor = $this->createMock(AgentInterface::class);
        $supervisor->expects($this->exactly(2))
            ->method('respond')
            ->willReturnOnConsecutiveCalls(
                new AgentResponse(content: '{"action": "delegate", "worker": "custom_worker", "task": "Route this"}'),
                new AgentResponse(content: '{"action": "complete", "response": "Routed and done"}')
            );

        $customStep = $this->createMock(StepInterface::class);
        $customStep->method('execute')
            ->willReturn(StepResult::success(['routed' => true]));

        $worker = $this->createMock(AgentInterface::class);

        $pattern = SupervisorPattern::make($supervisor, ['custom_worker' => $worker])
            ->withRouter(function (string $workerName, WorkflowContext $ctx) use ($customStep) {
                return $customStep;
            });

        $context = new WorkflowContext;
        $context->set('task', 'Use custom router');

        $result = $pattern->execute($context);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_has_default_name_supervisor(): void
    {
        $supervisor = $this->createMock(AgentInterface::class);
        $pattern = SupervisorPattern::make($supervisor);

        $this->assertSame('supervisor', $pattern->getName());
    }

    #[Test]
    public function it_supports_custom_name(): void
    {
        $supervisor = $this->createMock(AgentInterface::class);
        $pattern = SupervisorPattern::make($supervisor)->as('my-supervisor');

        $this->assertSame('my-supervisor', $pattern->getName());
    }

    #[Test]
    public function it_returns_null_output_key(): void
    {
        $supervisor = $this->createMock(AgentInterface::class);
        $pattern = SupervisorPattern::make($supervisor);

        $this->assertNull($pattern->getOutputKey());
    }

    #[Test]
    public function it_is_retryable(): void
    {
        $supervisor = $this->createMock(AgentInterface::class);
        $pattern = SupervisorPattern::make($supervisor);

        $this->assertTrue($pattern->isRetryable());
    }

    #[Test]
    public function it_has_max_retries_of_one(): void
    {
        $supervisor = $this->createMock(AgentInterface::class);
        $pattern = SupervisorPattern::make($supervisor);

        $this->assertSame(1, $pattern->getMaxRetries());
    }

    #[Test]
    public function it_returns_null_timeout(): void
    {
        $supervisor = $this->createMock(AgentInterface::class);
        $pattern = SupervisorPattern::make($supervisor);

        $this->assertNull($pattern->getTimeout());
    }

    #[Test]
    public function it_does_not_require_human_approval(): void
    {
        $supervisor = $this->createMock(AgentInterface::class);
        $pattern = SupervisorPattern::make($supervisor);

        $this->assertFalse($pattern->requiresHumanApproval());
    }

    #[Test]
    public function it_has_no_dependencies(): void
    {
        $supervisor = $this->createMock(AgentInterface::class);
        $pattern = SupervisorPattern::make($supervisor);

        $this->assertSame([], $pattern->getDependencies());
    }

    #[Test]
    public function it_reads_task_from_message_key_when_task_is_missing(): void
    {
        $capturedPrompt = null;

        $supervisor = $this->createMock(AgentInterface::class);
        $supervisor->method('respond')
            ->willReturnCallback(function (string $prompt) use (&$capturedPrompt) {
                $capturedPrompt = $prompt;

                return new AgentResponse(content: '{"action": "complete", "response": "Done"}');
            });

        $pattern = SupervisorPattern::make($supervisor);

        $context = new WorkflowContext;
        $context->set('message', 'Message-based task');

        $pattern->execute($context);

        $this->assertStringContainsString('Message-based task', $capturedPrompt);
    }

    #[Test]
    public function it_includes_worker_descriptions_in_supervisor_prompt(): void
    {
        $capturedPrompt = null;

        $worker = $this->createMock(AgentInterface::class);
        $worker->method('getDescription')->willReturn('Analyzes data sets');

        $supervisor = $this->createMock(AgentInterface::class);
        $supervisor->method('respond')
            ->willReturnCallback(function (string $prompt) use (&$capturedPrompt) {
                $capturedPrompt = $prompt;

                return new AgentResponse(content: '{"action": "complete", "response": "Done"}');
            });

        $pattern = SupervisorPattern::make($supervisor, ['analyst' => $worker]);

        $context = new WorkflowContext;
        $context->set('task', 'Analyze sales');

        $pattern->execute($context);

        $this->assertStringContainsString('analyst', $capturedPrompt);
        $this->assertStringContainsString('Analyzes data sets', $capturedPrompt);
    }

    #[Test]
    public function it_uses_default_description_for_string_workers(): void
    {
        $capturedPrompt = null;

        $supervisor = $this->createMock(AgentInterface::class);
        $supervisor->method('respond')
            ->willReturnCallback(function (string $prompt) use (&$capturedPrompt) {
                $capturedPrompt = $prompt;

                return new AgentResponse(content: '{"action": "complete", "response": "Done"}');
            });

        $pattern = SupervisorPattern::make($supervisor, ['my_worker' => 'worker-agent-name']);

        $context = new WorkflowContext;
        $context->set('task', 'Test');

        $pattern->execute($context);

        $this->assertStringContainsString('Worker agent: my_worker', $capturedPrompt);
    }
}
