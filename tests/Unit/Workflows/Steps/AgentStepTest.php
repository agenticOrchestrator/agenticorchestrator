<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows\Steps;

use AgenticOrchestrator\Agents\AgentResponse;
use AgenticOrchestrator\Contracts\AgentInterface;
use AgenticOrchestrator\Workflows\Steps\AgentStep;
use AgenticOrchestrator\Workflows\WorkflowContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(AgentStep::class)]
class AgentStepTest extends TestCase
{
    #[Test]
    public function it_creates_via_static_make(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $step = AgentStep::make($agent, 'Hello');

        $this->assertInstanceOf(AgentStep::class, $step);
    }

    #[Test]
    public function it_executes_agent_with_message(): void
    {
        $response = new AgentResponse(
            content: 'Agent reply',
            toolCalls: [],
            usage: ['prompt_tokens' => 50, 'completion_tokens' => 50, 'total_tokens' => 100],
            latency: 0.5,
        );

        $agent = $this->createMock(AgentInterface::class);
        $agent->expects($this->once())
            ->method('respond')
            ->with('Process this task', $this->anything())
            ->willReturn($response);

        $step = AgentStep::make($agent, 'Process this task');

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Agent reply', $result->output['content']);
        $this->assertSame([], $result->output['tool_calls']);
        $this->assertSame(['prompt_tokens' => 50, 'completion_tokens' => 50, 'total_tokens' => 100], $result->output['usage']);
        $this->assertSame(0.5, $result->output['latency']);
    }

    #[Test]
    public function it_substitutes_context_variables_in_message(): void
    {
        $response = new AgentResponse(content: 'Done');

        $capturedMessage = null;
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('respond')
            ->willReturnCallback(function (string $msg) use (&$capturedMessage, $response) {
                $capturedMessage = $msg;

                return $response;
            });

        $step = AgentStep::make($agent, 'Summarize {document_name} for {user}');

        $context = new WorkflowContext;
        $context->set('document_name', 'report.pdf');
        $context->set('user', 'Alice');

        $step->execute($context);

        $this->assertSame('Summarize report.pdf for Alice', $capturedMessage);
    }

    #[Test]
    public function it_supports_closure_message(): void
    {
        $response = new AgentResponse(content: 'Done');

        $capturedMessage = null;
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('respond')
            ->willReturnCallback(function (string $msg) use (&$capturedMessage, $response) {
                $capturedMessage = $msg;

                return $response;
            });

        $step = AgentStep::make($agent, function (WorkflowContext $ctx) {
            return 'Custom: '.$ctx->get('data');
        });

        $context = new WorkflowContext;
        $context->set('data', 'test_value');

        $step->execute($context);

        $this->assertSame('Custom: test_value', $capturedMessage);
    }

    #[Test]
    public function it_passes_agent_context_as_array(): void
    {
        $response = new AgentResponse(content: 'Done');

        $capturedContext = null;
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('respond')
            ->willReturnCallback(function (string $msg, array $ctx) use (&$capturedContext, $response) {
                $capturedContext = $ctx;

                return $response;
            });

        $step = AgentStep::make($agent, 'Do something')
            ->withContext(['extra_key' => 'extra_value']);

        $context = new WorkflowContext;
        $step->execute($context);

        $this->assertSame(['extra_key' => 'extra_value'], $capturedContext);
    }

    #[Test]
    public function it_supports_closure_agent_context(): void
    {
        $response = new AgentResponse(content: 'Done');

        $capturedContext = null;
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('respond')
            ->willReturnCallback(function (string $msg, array $ctx) use (&$capturedContext, $response) {
                $capturedContext = $ctx;

                return $response;
            });

        $step = AgentStep::make($agent, 'Do something')
            ->withContext(fn (WorkflowContext $ctx) => [
                'dynamic_key' => $ctx->get('value'),
            ]);

        $context = new WorkflowContext;
        $context->set('value', 'dynamic_value');

        $step->execute($context);

        $this->assertSame(['dynamic_key' => 'dynamic_value'], $capturedContext);
    }

    #[Test]
    public function it_wraps_agent_exceptions_in_failed_result(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('respond')
            ->willThrowException(new RuntimeException('Agent crashed'));

        $step = AgentStep::make($agent, 'Do something');

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isFailed());
        $this->assertSame('Agent crashed', $result->message);
    }

    #[Test]
    public function it_has_correct_auto_generated_name(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $step = AgentStep::make($agent, 'test');

        $this->assertSame('agent', $step->getName());
    }

    #[Test]
    public function it_supports_custom_name(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $step = AgentStep::make($agent, 'test')->as('summarizer');

        $this->assertSame('summarizer', $step->getName());
    }

    #[Test]
    public function it_stores_output_when_output_key_is_set(): void
    {
        $response = new AgentResponse(content: 'Result');

        $agent = $this->createMock(AgentInterface::class);
        $agent->method('respond')->willReturn($response);

        $step = AgentStep::make($agent, 'test')
            ->outputAs('agent_output');

        $context = new WorkflowContext;
        $step->execute($context);

        $this->assertIsArray($context->get('agent_output'));
        $this->assertSame('Result', $context->get('agent_output')['content']);
    }

    #[Test]
    public function it_enables_streaming_mode(): void
    {
        $step = AgentStep::make($this->createMock(AgentInterface::class), 'test')
            ->streaming();

        $this->assertInstanceOf(AgentStep::class, $step);
    }

    #[Test]
    public function it_checks_dependencies_before_executing(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->expects($this->never())->method('respond');

        $step = AgentStep::make($agent, 'test')
            ->dependsOn(['required_input']);

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isFailed());
        $this->assertStringContainsString('required_input', $result->message);
    }

    #[Test]
    public function it_does_not_substitute_non_string_values(): void
    {
        $response = new AgentResponse(content: 'Done');

        $capturedMessage = null;
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('respond')
            ->willReturnCallback(function (string $msg) use (&$capturedMessage, $response) {
                $capturedMessage = $msg;

                return $response;
            });

        $step = AgentStep::make($agent, 'Value is {array_value} and {num}');

        $context = new WorkflowContext;
        $context->set('array_value', ['not', 'a', 'string']);
        $context->set('num', 42);

        $step->execute($context);

        // Array value should not be substituted, numeric should be
        $this->assertStringContainsString('{array_value}', $capturedMessage);
        $this->assertStringContainsString('42', $capturedMessage);
    }
}
