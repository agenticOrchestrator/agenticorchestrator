<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Fixtures\Testing;

use AgenticOrchestrator\Agents\AgentResponse;
use AgenticOrchestrator\Testing\AgentTestCase;
use AgenticOrchestrator\Testing\FakeMemory;
use AgenticOrchestrator\Testing\FakeTool;

/**
 * Concrete AgentTestCase double exposing its protected helpers for testing.
 *
 * Instantiate with ReflectionClass::newInstanceWithoutConstructor(): PHPUnit 12
 * marks TestCase::__construct() final, so it cannot be overridden, and the real
 * constructor would trigger full Orchestra/PHPUnit setup. Properties are seeded
 * via reflection in the test's beforeEach.
 */
class InspectableAgentTestCase extends AgentTestCase
{
    protected function getPackageProviders($app): array
    {
        return [];
    }

    public function callAssertResponseContains(AgentResponse $response, string $text): void
    {
        $this->assertResponseContains($response, $text);
    }

    public function callAssertResponseNotContains(AgentResponse $response, string $text): void
    {
        $this->assertResponseNotContains($response, $text);
    }

    public function callAssertResponseMatches(AgentResponse $response, string $pattern): void
    {
        $this->assertResponseMatches($response, $pattern);
    }

    public function callAssertResponseHasToolCalls(AgentResponse $response, ?int $count = null): void
    {
        $this->assertResponseHasToolCalls($response, $count);
    }

    public function callAssertResponseHasToolCall(AgentResponse $response, string $toolName): void
    {
        $this->assertResponseHasToolCall($response, $toolName);
    }

    public function callAssertResponseHasNoToolCalls(AgentResponse $response): void
    {
        $this->assertResponseHasNoToolCalls($response);
    }

    public function callAssertResponseTokensWithin(AgentResponse $response, int $min, int $max): void
    {
        $this->assertResponseTokensWithin($response, $min, $max);
    }

    public function callAssertAllAgentsCalled(): void
    {
        $this->assertAllAgentsCalled();
    }

    public function callAssertAllToolsCalled(): void
    {
        $this->assertAllToolsCalled();
    }

    public function callFakeMemory(): FakeMemory
    {
        return $this->fakeMemory();
    }

    public function callTool(string $name): FakeTool
    {
        return $this->tool($name);
    }

    public function callFakeTool(string $name, ?array $returns = null): FakeTool
    {
        return $this->fakeTool($name, $returns);
    }
}
