<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation;

use AgenticOrchestrator\Contracts\AgentInterface;
use AgenticOrchestrator\Evaluation\Contracts\AssertionInterface;
use Illuminate\Support\Facades\App;
use Throwable;

/**
 * Evaluator - Runs evaluations against agents.
 */
class Evaluator
{
    /**
     * Create a new evaluator.
     *
     * @param  array<string, AssertionInterface>  $assertions
     */
    public function __construct(
        protected array $assertions = [],
        protected ?LlmJudge $judge = null,
    ) {}

    /**
     * Create a new evaluator with defaults.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Set the LLM judge.
     */
    public function withJudge(LlmJudge $judge): static
    {
        $this->judge = $judge;

        return $this;
    }

    /**
     * Register an assertion.
     */
    public function registerAssertion(AssertionInterface $assertion): static
    {
        $this->assertions[$assertion->name()] = $assertion;

        return $this;
    }

    /**
     * Evaluate an agent against a test case.
     *
     * @param  class-string<AgentInterface>  $agentClass
     */
    public function evaluate(
        string $agentClass,
        TestCase $testCase,
        int|string|object|null $team = null,
        bool $evaluateMetrics = true,
    ): TestCaseResult {
        $startTime = microtime(true);

        try {
            // Create agent instance
            $agent = $this->createAgent($agentClass, $team);

            // Run the agent
            $response = $agent->respond($testCase->input);
            $actualOutput = $response->content;

            // Evaluate assertions
            $assertionResults = $this->runAssertions($actualOutput, $testCase);

            // Evaluate metrics if enabled
            $metricResults = [];
            if ($evaluateMetrics && $testCase->hasMetrics()) {
                $metricResults = $this->runMetrics($testCase->input, $actualOutput, $testCase);
            }

            $durationMs = (microtime(true) - $startTime) * 1000;

            // Determine overall status
            $passed = $this->determineOverallStatus($assertionResults, $metricResults);

            if ($passed) {
                return TestCaseResult::passed(
                    testCase: $testCase,
                    actualOutput: $actualOutput,
                    assertionResults: $assertionResults,
                    metricResults: $metricResults,
                    durationMs: $durationMs,
                    metadata: ['response' => $response->toArray()],
                );
            }

            return TestCaseResult::failed(
                testCase: $testCase,
                actualOutput: $actualOutput,
                assertionResults: $assertionResults,
                metricResults: $metricResults,
                durationMs: $durationMs,
                metadata: ['response' => $response->toArray()],
            );
        } catch (Throwable $e) {
            $durationMs = (microtime(true) - $startTime) * 1000;

            return TestCaseResult::error(
                testCase: $testCase,
                error: $e,
                durationMs: $durationMs,
            );
        }
    }

    /**
     * Evaluate an agent against multiple test cases.
     *
     * @param  class-string<AgentInterface>  $agentClass
     * @param  array<TestCase>  $testCases
     * @return array<TestCaseResult>
     */
    public function evaluateAll(
        string $agentClass,
        array $testCases,
        int|string|object|null $team = null,
        bool $evaluateMetrics = true,
    ): array {
        $results = [];

        foreach ($testCases as $testCase) {
            $results[] = $this->evaluate($agentClass, $testCase, $team, $evaluateMetrics);
        }

        return $results;
    }

    /**
     * Create an agent instance.
     *
     * @param  class-string<AgentInterface>  $agentClass
     */
    protected function createAgent(string $agentClass, int|string|object|null $team = null): AgentInterface
    {
        $agent = App::make($agentClass);

        if ($team !== null && method_exists($agent, 'forTeam')) {
            $agent->forTeam($team);
        }

        return $agent;
    }

    /**
     * Run assertions against the output.
     *
     * @return array<string, AssertionResult>
     */
    protected function runAssertions(string $actualOutput, TestCase $testCase): array
    {
        $results = [];

        foreach ($testCase->assertions as $assertionName => $config) {
            if (isset($this->assertions[$assertionName])) {
                $assertion = $this->assertions[$assertionName];
                $results[$assertionName] = $assertion->evaluate($actualOutput, $config, $testCase);
            } else {
                // Unknown assertion
                $results[$assertionName] = AssertionResult::fail(
                    name: $assertionName,
                    message: "Unknown assertion: {$assertionName}",
                );
            }
        }

        return $results;
    }

    /**
     * Run metric evaluations.
     *
     * @return array<string, MetricResult>
     */
    protected function runMetrics(string $input, string $actualOutput, TestCase $testCase): array
    {
        $judge = $this->judge ?? LlmJudge::make();

        return $judge->evaluateAll(
            array_keys($testCase->metrics),
            $input,
            $actualOutput,
            $testCase,
        );
    }

    /**
     * Determine overall pass/fail status.
     *
     * @param  array<string, AssertionResult>  $assertionResults
     * @param  array<string, MetricResult>  $metricResults
     */
    protected function determineOverallStatus(array $assertionResults, array $metricResults): bool
    {
        // All assertions must pass
        foreach ($assertionResults as $result) {
            if (! $result->passed) {
                return false;
            }
        }

        // All metrics must pass their thresholds
        foreach ($metricResults as $result) {
            if (! $result->passes()) {
                return false;
            }
        }

        return true;
    }
}
