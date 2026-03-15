<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Testing;

use AgenticOrchestrator\Agents\AgentManager;
use AgenticOrchestrator\Agents\AgentResponse;
use AgenticOrchestrator\Contracts\AgentInterface;
use AgenticOrchestrator\Tools\ToolRegistry;
use Illuminate\Foundation\Testing\TestCase;
use PHPUnit\Framework\Assert;

/**
 * Agent Test Case - Base class for agent testing with useful helpers.
 *
 * @example
 * ```php
 * class MyAgentTest extends AgentTestCase
 * {
 *     public function test_responds_to_greeting(): void
 *     {
 *         $this->fakeAgent('my-agent', 'Hello there!');
 *
 *         $response = $this->agent('my-agent')->respond('Hi');
 *
 *         $this->assertResponseContains($response, 'Hello');
 *     }
 * }
 * ```
 */
abstract class AgentTestCase extends TestCase
{
    /** @var array<string, FakeAgent> */
    protected array $fakedAgents = [];

    /** @var array<string, FakeTool> */
    protected array $fakedTools = [];

    protected ?FakeMemory $fakeMemory = null;

    /**
     * Set up the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->fakedAgents = [];
        $this->fakedTools = [];
        $this->fakeMemory = null;
    }

    /**
     * Create a fake agent that returns specified response.
     *
     * @param  AgentResponse|string|array<AgentResponse|string>  $response
     */
    protected function fakeAgent(string $name, AgentResponse|string|array $response = 'Fake response'): FakeAgent
    {
        $fake = FakeAgent::make()->respondWith($response);
        $this->fakedAgents[$name] = $fake;

        // Register with manager if available
        if ($this->app->bound(AgentManager::class)) {
            $manager = $this->app->make(AgentManager::class);
            // Agent manager should support registering instances
        }

        return $fake;
    }

    /**
     * Get a fake or real agent.
     */
    protected function agent(string $name): AgentInterface
    {
        if (isset($this->fakedAgents[$name])) {
            return $this->fakedAgents[$name];
        }

        return $this->app->make(AgentManager::class)->make($name);
    }

    /**
     * Create a fake tool.
     *
     * @param  array<string, mixed>|null  $returns
     */
    protected function fakeTool(string $name, ?array $returns = null): FakeTool
    {
        $fake = FakeTool::make($name);

        if ($returns !== null) {
            $fake->returns($returns);
        }

        $this->fakedTools[$name] = $fake;

        // Register with tool registry if available
        if ($this->app->bound(ToolRegistry::class)) {
            $registry = $this->app->make(ToolRegistry::class);
            $registry->register($name, $fake);
        }

        return $fake;
    }

    /**
     * Get a fake or real tool.
     */
    protected function tool(string $name): FakeTool
    {
        if (isset($this->fakedTools[$name])) {
            return $this->fakedTools[$name];
        }

        throw new \InvalidArgumentException("Tool '{$name}' not faked. Use fakeTool() first.");
    }

    /**
     * Use fake memory for all agents.
     */
    protected function fakeMemory(): FakeMemory
    {
        if ($this->fakeMemory === null) {
            $this->fakeMemory = FakeMemory::make();
        }

        return $this->fakeMemory;
    }

    /**
     * Assert response contains text.
     */
    protected function assertResponseContains(AgentResponse $response, string $text): void
    {
        Assert::assertStringContainsString(
            $text,
            $response->content,
            "Expected response to contain '{$text}', but it did not."
        );
    }

    /**
     * Assert response does not contain text.
     */
    protected function assertResponseNotContains(AgentResponse $response, string $text): void
    {
        Assert::assertStringNotContainsString(
            $text,
            $response->content,
            "Expected response not to contain '{$text}', but it did."
        );
    }

    /**
     * Assert response matches pattern.
     */
    protected function assertResponseMatches(AgentResponse $response, string $pattern): void
    {
        Assert::assertMatchesRegularExpression(
            $pattern,
            $response->content,
            "Expected response to match pattern '{$pattern}', but it did not."
        );
    }

    /**
     * Assert response has tool calls.
     */
    protected function assertResponseHasToolCalls(AgentResponse $response, ?int $count = null): void
    {
        Assert::assertTrue(
            $response->hasToolCalls(),
            'Expected response to have tool calls, but it did not.'
        );

        if ($count !== null) {
            Assert::assertCount(
                $count,
                $response->getToolCalls(),
                "Expected response to have {$count} tool call(s)."
            );
        }
    }

    /**
     * Assert response has specific tool call.
     */
    protected function assertResponseHasToolCall(AgentResponse $response, string $toolName): void
    {
        $toolCalls = $response->getToolCalls();

        foreach ($toolCalls as $call) {
            if ($call['name'] === $toolName) {
                return;
            }
        }

        Assert::fail("Expected response to have tool call '{$toolName}', but it did not.");
    }

    /**
     * Assert response has no tool calls.
     */
    protected function assertResponseHasNoToolCalls(AgentResponse $response): void
    {
        Assert::assertFalse(
            $response->hasToolCalls(),
            'Expected response to have no tool calls, but it did.'
        );
    }

    /**
     * Assert response tokens are within range.
     */
    protected function assertResponseTokensWithin(AgentResponse $response, int $min, int $max): void
    {
        $total = $response->getTotalTokens();

        Assert::assertGreaterThanOrEqual(
            $min,
            $total,
            "Expected at least {$min} tokens, got {$total}."
        );

        Assert::assertLessThanOrEqual(
            $max,
            $total,
            "Expected at most {$max} tokens, got {$total}."
        );
    }

    /**
     * Assert all faked agents were called.
     */
    protected function assertAllAgentsCalled(): void
    {
        foreach ($this->fakedAgents as $name => $agent) {
            if (empty($agent->getCalls())) {
                Assert::fail("Expected agent '{$name}' to be called, but it was not.");
            }
        }
    }

    /**
     * Assert all faked tools were called.
     */
    protected function assertAllToolsCalled(): void
    {
        foreach ($this->fakedTools as $name => $tool) {
            if (empty($tool->getCalls())) {
                Assert::fail("Expected tool '{$name}' to be called, but it was not.");
            }
        }
    }
}
