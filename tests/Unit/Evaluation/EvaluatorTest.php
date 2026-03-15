<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\AgentResponse;
use AgenticOrchestrator\Contracts\AgentInterface;
use AgenticOrchestrator\Evaluation\AssertionResult;
use AgenticOrchestrator\Evaluation\Contracts\AssertionInterface;
use AgenticOrchestrator\Evaluation\Evaluator;
use AgenticOrchestrator\Evaluation\LlmJudge;
use AgenticOrchestrator\Evaluation\MetricResult;
use AgenticOrchestrator\Evaluation\TestCase as EvalTestCase;
use AgenticOrchestrator\Testing\FakeAgent;

describe('Evaluator', function () {
    it('can be created with static make method', function () {
        $evaluator = Evaluator::make();

        expect($evaluator)->toBeInstanceOf(Evaluator::class);
    });

    it('can set an LLM judge', function () {
        $judge = Mockery::mock(LlmJudge::class);
        $evaluator = Evaluator::make()->withJudge($judge);

        expect($evaluator)->toBeInstanceOf(Evaluator::class);
    });

    it('can register assertions', function () {
        $assertion = Mockery::mock(AssertionInterface::class);
        $assertion->shouldReceive('name')->once()->andReturn('contains');

        $evaluator = Evaluator::make()->registerAssertion($assertion);

        expect($evaluator)->toBeInstanceOf(Evaluator::class);
    });

    it('evaluates a passing test case with assertions', function () {
        $response = new AgentResponse(
            content: 'The answer is 42',
            usage: ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        );

        $agent = Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('respond')
            ->with('What is the answer?')
            ->once()
            ->andReturn($response);

        $this->app->instance(AgentInterface::class, $agent);
        $this->app->bind(get_class($agent), fn () => $agent);

        $assertion = Mockery::mock(AssertionInterface::class);
        $assertion->shouldReceive('name')->andReturn('contains');
        $assertion->shouldReceive('evaluate')
            ->once()
            ->andReturn(AssertionResult::pass('contains', 'Found "42" in output'));

        $testCase = new EvalTestCase(
            name: 'answer-test',
            input: 'What is the answer?',
            assertions: ['contains' => '42'],
        );

        $evaluator = Evaluator::make()->registerAssertion($assertion);

        $result = $evaluator->evaluate(get_class($agent), $testCase);

        expect($result->hasPassed())->toBeTrue()
            ->and($result->actualOutput)->toBe('The answer is 42')
            ->and($result->durationMs)->toBeGreaterThan(0);
    });

    it('evaluates a failing test case when assertion fails', function () {
        $response = new AgentResponse(content: 'I do not know');

        $agent = Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('respond')->once()->andReturn($response);

        $this->app->bind(get_class($agent), fn () => $agent);

        $assertion = Mockery::mock(AssertionInterface::class);
        $assertion->shouldReceive('name')->andReturn('contains');
        $assertion->shouldReceive('evaluate')
            ->once()
            ->andReturn(AssertionResult::fail('contains', 'Did not find "42"'));

        $testCase = new EvalTestCase(
            name: 'fail-test',
            input: 'What is the answer?',
            assertions: ['contains' => '42'],
        );

        $evaluator = Evaluator::make()->registerAssertion($assertion);
        $result = $evaluator->evaluate(get_class($agent), $testCase);

        expect($result->hasFailed())->toBeTrue();
    });

    it('returns error result when agent throws exception', function () {
        $agent = Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('respond')
            ->once()
            ->andThrow(new RuntimeException('LLM provider error'));

        $this->app->bind(get_class($agent), fn () => $agent);

        $testCase = new EvalTestCase(
            name: 'error-test',
            input: 'Trigger error',
        );

        $evaluator = Evaluator::make();
        $result = $evaluator->evaluate(get_class($agent), $testCase);

        expect($result->hasErrored())->toBeTrue()
            ->and($result->error)->toBeInstanceOf(RuntimeException::class)
            ->and($result->error->getMessage())->toBe('LLM provider error');
    });

    it('handles unknown assertions gracefully', function () {
        $response = new AgentResponse(content: 'Some output');

        $agent = Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('respond')->once()->andReturn($response);

        $this->app->bind(get_class($agent), fn () => $agent);

        $testCase = new EvalTestCase(
            name: 'unknown-assertion-test',
            input: 'Test input',
            assertions: ['nonexistent_assertion' => 'config'],
        );

        $evaluator = Evaluator::make();
        $result = $evaluator->evaluate(get_class($agent), $testCase);

        expect($result->hasFailed())->toBeTrue()
            ->and($result->assertionResults)->toHaveKey('nonexistent_assertion')
            ->and($result->assertionResults['nonexistent_assertion']->passed)->toBeFalse()
            ->and($result->assertionResults['nonexistent_assertion']->message)->toContain('Unknown assertion');
    });

    it('evaluates metrics when test case has them', function () {
        $response = new AgentResponse(
            content: 'Helpful answer',
            usage: ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        );

        $agent = Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('respond')->once()->andReturn($response);

        $this->app->bind(get_class($agent), fn () => $agent);

        $metricResult = new MetricResult(
            name: 'helpfulness',
            score: 0.9,
            reasoning: 'Very helpful response',
        );

        $judge = Mockery::mock(LlmJudge::class);
        $judge->shouldReceive('evaluateAll')
            ->once()
            ->andReturn(['helpfulness' => $metricResult]);

        $testCase = new EvalTestCase(
            name: 'metric-test',
            input: 'Help me',
            metrics: ['helpfulness' => ['threshold' => 0.7]],
        );

        $evaluator = Evaluator::make()->withJudge($judge);
        $result = $evaluator->evaluate(get_class($agent), $testCase);

        expect($result->hasPassed())->toBeTrue()
            ->and($result->metricResults)->toHaveKey('helpfulness')
            ->and($result->metricResults['helpfulness']->score)->toBe(0.9);
    });

    it('skips metrics when evaluateMetrics is false', function () {
        $response = new AgentResponse(content: 'Answer');

        $agent = Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('respond')->once()->andReturn($response);

        $this->app->bind(get_class($agent), fn () => $agent);

        $judge = Mockery::mock(LlmJudge::class);
        $judge->shouldNotReceive('evaluateAll');

        $testCase = new EvalTestCase(
            name: 'skip-metrics-test',
            input: 'Test',
            metrics: ['relevance' => ['threshold' => 0.5]],
        );

        $evaluator = Evaluator::make()->withJudge($judge);
        $result = $evaluator->evaluate(get_class($agent), $testCase, evaluateMetrics: false);

        expect($result->hasPassed())->toBeTrue()
            ->and($result->metricResults)->toBeEmpty();
    });

    it('fails when metric does not pass threshold', function () {
        $response = new AgentResponse(content: 'Poor answer');

        $agent = Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('respond')->once()->andReturn($response);

        $this->app->bind(get_class($agent), fn () => $agent);

        $metricResult = new MetricResult(
            name: 'relevance',
            score: 0.3,
            reasoning: 'Not relevant',
            threshold: 0.7,
        );

        $judge = Mockery::mock(LlmJudge::class);
        $judge->shouldReceive('evaluateAll')
            ->once()
            ->andReturn(['relevance' => $metricResult]);

        $testCase = new EvalTestCase(
            name: 'low-metric-test',
            input: 'Test',
            metrics: ['relevance' => ['threshold' => 0.7]],
        );

        $evaluator = Evaluator::make()->withJudge($judge);
        $result = $evaluator->evaluate(get_class($agent), $testCase);

        expect($result->hasFailed())->toBeTrue();
    });

    it('evaluates multiple test cases with evaluateAll', function () {
        $response = new AgentResponse(content: 'Answer');

        $agent = Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('respond')->times(3)->andReturn($response);

        $this->app->bind(get_class($agent), fn () => $agent);

        $testCases = [
            new EvalTestCase(name: 'test-1', input: 'Q1'),
            new EvalTestCase(name: 'test-2', input: 'Q2'),
            new EvalTestCase(name: 'test-3', input: 'Q3'),
        ];

        $evaluator = Evaluator::make();
        $results = $evaluator->evaluateAll(get_class($agent), $testCases);

        expect($results)->toHaveCount(3)
            ->and($results[0]->hasPassed())->toBeTrue()
            ->and($results[1]->hasPassed())->toBeTrue()
            ->and($results[2]->hasPassed())->toBeTrue();
    });

    it('applies team scope when agent has forTeam method and team is provided', function () {
        // FakeAgent implements AgentInterface and has forTeam method
        $fakeAgent = FakeAgent::make()->respondWith('Team response');

        $this->app->bind(FakeAgent::class, fn () => $fakeAgent);

        $testCase = new EvalTestCase(name: 'team-test', input: 'Test');

        $evaluator = Evaluator::make();
        $result = $evaluator->evaluate(FakeAgent::class, $testCase, team: 5);

        expect($result->hasPassed())->toBeTrue()
            ->and($result->actualOutput)->toBe('Team response');
    });
});
