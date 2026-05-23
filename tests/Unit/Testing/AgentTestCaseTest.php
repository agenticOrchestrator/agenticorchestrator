<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\AgentResponse;
use AgenticOrchestrator\Testing\AgentTestCase;
use AgenticOrchestrator\Testing\FakeMemory;
use AgenticOrchestrator\Testing\FakeTool;
use AgenticOrchestrator\Tests\Fixtures\Testing\InspectableAgentTestCase;
use Illuminate\Contracts\Foundation\Application;

describe('AgentTestCase', function () {
    beforeEach(function () {
        // PHPUnit 12 marks TestCase::__construct() final, so we cannot override
        // it. Instantiate the concrete double without the constructor to skip
        // the Orchestra/PHPUnit setup, then seed properties via reflection.
        $this->testCase = (new ReflectionClass(InspectableAgentTestCase::class))
            ->newInstanceWithoutConstructor();

        // Initialize properties
        $reflection = new ReflectionClass(AgentTestCase::class);

        $prop = $reflection->getProperty('fakedAgents');
        $prop->setValue($this->testCase, []);

        $prop = $reflection->getProperty('fakedTools');
        $prop->setValue($this->testCase, []);

        $prop = $reflection->getProperty('fakeMemory');
        $prop->setValue($this->testCase, null);

        // Set the app property with a mock that returns false for bound() checks
        // This avoids triggering ToolRegistry/AgentManager registration logic
        $mockApp = Mockery::mock(Application::class);
        $mockApp->shouldReceive('bound')->andReturn(false);
        $prop = $reflection->getProperty('app');
        $prop->setValue($this->testCase, $mockApp);
    });

    it('assertResponseContains passes when text is found', function () {
        $response = new AgentResponse('This is a test response');

        $this->testCase->callAssertResponseContains($response, 'test response');

        expect(true)->toBeTrue();
    });

    it('assertResponseNotContains passes when text is not found', function () {
        $response = new AgentResponse('This is a test response');

        $this->testCase->callAssertResponseNotContains($response, 'missing content');

        expect(true)->toBeTrue();
    });

    it('assertResponseMatches passes with matching regex pattern', function () {
        $response = new AgentResponse('The answer is 42');

        $this->testCase->callAssertResponseMatches($response, '/answer is \d+/');

        expect(true)->toBeTrue();
    });

    it('assertResponseHasToolCalls passes when tool calls present', function () {
        $response = new AgentResponse('response', [
            ['id' => 'call_1', 'name' => 'tool1', 'arguments' => [], 'result' => null],
            ['id' => 'call_2', 'name' => 'tool2', 'arguments' => [], 'result' => null],
        ]);

        $this->testCase->callAssertResponseHasToolCalls($response);

        expect(true)->toBeTrue();
    });

    it('assertResponseHasToolCalls with specific count', function () {
        $response = new AgentResponse('response', [
            ['id' => 'call_1', 'name' => 'tool1', 'arguments' => [], 'result' => null],
            ['id' => 'call_2', 'name' => 'tool2', 'arguments' => [], 'result' => null],
        ]);

        $this->testCase->callAssertResponseHasToolCalls($response, 2);

        expect(true)->toBeTrue();
    });

    it('assertResponseHasNoToolCalls passes when no tool calls', function () {
        $response = new AgentResponse('response');

        $this->testCase->callAssertResponseHasNoToolCalls($response);

        expect(true)->toBeTrue();
    });

    it('assertResponseHasToolCall passes with matching tool name', function () {
        $response = new AgentResponse('response', [
            ['id' => 'call_1', 'name' => 'search_tool', 'arguments' => [], 'result' => null],
        ]);

        $this->testCase->callAssertResponseHasToolCall($response, 'search_tool');

        expect(true)->toBeTrue();
    });

    it('assertResponseTokensWithin passes when within range', function () {
        $response = new AgentResponse('response', [], [
            'prompt_tokens' => 200,
            'completion_tokens' => 300,
            'total_tokens' => 500,
        ]);

        $this->testCase->callAssertResponseTokensWithin($response, 100, 1000);

        expect(true)->toBeTrue();
    });

    it('assertAllAgentsCalled passes when no agents faked', function () {
        $this->testCase->callAssertAllAgentsCalled();

        expect(true)->toBeTrue();
    });

    it('assertAllToolsCalled passes when no tools faked', function () {
        $this->testCase->callAssertAllToolsCalled();

        expect(true)->toBeTrue();
    });

    it('fakeMemory creates FakeMemory instance', function () {
        $result = $this->testCase->callFakeMemory();

        expect($result)->toBeInstanceOf(FakeMemory::class);
    });

    it('fakeMemory returns same instance on subsequent calls', function () {
        $result1 = $this->testCase->callFakeMemory();
        $result2 = $this->testCase->callFakeMemory();

        expect($result1)->toBe($result2);
    });

    it('fakeTool creates FakeTool instance', function () {
        $result = $this->testCase->callFakeTool('test-tool');

        expect($result)->toBeInstanceOf(FakeTool::class);
    });

    it('tool returns faked tool', function () {
        $this->testCase->callFakeTool('my-tool');
        $result = $this->testCase->callTool('my-tool');

        expect($result)->toBeInstanceOf(FakeTool::class);
    });

    it('tool throws for non-faked tool', function () {
        expect(fn () => $this->testCase->callTool('non-existent'))
            ->toThrow(InvalidArgumentException::class);
    });
});
