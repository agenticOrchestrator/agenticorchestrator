<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation;

use AgenticOrchestrator\Contracts\AgentInterface;
use AgenticOrchestrator\Evaluation\Assertions\ContainsAssertion;
use AgenticOrchestrator\Evaluation\Assertions\JsonAssertion;
use AgenticOrchestrator\Evaluation\Assertions\LengthAssertion;
use AgenticOrchestrator\Evaluation\Assertions\MatchesPatternAssertion;
use AgenticOrchestrator\Evaluation\Assertions\NotContainsAssertion;
use AgenticOrchestrator\Evaluation\Contracts\AssertionInterface;

/**
 * Test Suite - Base class for agent evaluation test suites.
 */
abstract class TestSuite
{
    /**
     * The agent class to test.
     *
     * @var class-string<AgentInterface>
     */
    protected string $agent;

    /**
     * Team to scope the agent to.
     */
    protected int|string|object|null $team = null;

    /**
     * The LLM judge instance.
     */
    protected ?LlmJudge $judge = null;

    /**
     * Available assertions.
     *
     * @var array<string, AssertionInterface>
     */
    protected array $assertions = [];

    /**
     * Whether to run metrics evaluation.
     */
    protected bool $evaluateMetrics = true;

    /**
     * Create a new test suite.
     */
    public function __construct()
    {
        $this->registerDefaultAssertions();
    }

    /**
     * Create a new test suite instance.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Define test cases for the suite.
     *
     * @return array<TestCase>
     */
    abstract public function testCases(): array;

    /**
     * Set the team for scoping.
     */
    public function forTeam(int|string|object $team): static
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Set the LLM judge instance.
     */
    public function withJudge(LlmJudge $judge): static
    {
        $this->judge = $judge;

        return $this;
    }

    /**
     * Disable metrics evaluation (assertions only).
     */
    public function withoutMetrics(): static
    {
        $this->evaluateMetrics = false;

        return $this;
    }

    /**
     * Register a custom assertion.
     */
    public function registerAssertion(AssertionInterface $assertion): static
    {
        $this->assertions[$assertion->name()] = $assertion;

        return $this;
    }

    /**
     * Run the test suite.
     */
    public function run(): EvaluationResult
    {
        $startTime = microtime(true);
        $results = [];

        $evaluator = new Evaluator(
            assertions: $this->assertions,
            judge: $this->getJudge(),
        );

        foreach ($this->testCases() as $testCase) {
            $results[] = $evaluator->evaluate(
                agentClass: $this->agent,
                testCase: $testCase,
                team: $this->team,
                evaluateMetrics: $this->evaluateMetrics,
            );
        }

        $totalDuration = (microtime(true) - $startTime) * 1000;

        return new EvaluationResult(
            suiteClass: static::class,
            agentClass: $this->agent,
            results: $results,
            totalDurationMs: $totalDuration,
            metadata: [
                'team' => $this->team,
                'metrics_enabled' => $this->evaluateMetrics,
            ],
        );
    }

    /**
     * Run a specific test case by name.
     */
    public function runCase(string $name): ?TestCaseResult
    {
        $evaluator = new Evaluator(
            assertions: $this->assertions,
            judge: $this->getJudge(),
        );

        foreach ($this->testCases() as $testCase) {
            if ($testCase->name === $name) {
                return $evaluator->evaluate(
                    agentClass: $this->agent,
                    testCase: $testCase,
                    team: $this->team,
                    evaluateMetrics: $this->evaluateMetrics,
                );
            }
        }

        return null;
    }

    /**
     * Get the agent class.
     */
    public function getAgentClass(): string
    {
        return $this->agent;
    }

    /**
     * Get the LLM judge.
     */
    protected function getJudge(): LlmJudge
    {
        return $this->judge ?? LlmJudge::make();
    }

    /**
     * Register default assertions.
     */
    protected function registerDefaultAssertions(): void
    {
        $this->assertions = [
            'contains' => new ContainsAssertion,
            'not_contains' => new NotContainsAssertion,
            'matches' => new MatchesPatternAssertion,
            'length' => new LengthAssertion,
            'json' => new JsonAssertion,
        ];
    }

    /**
     * Set up method called before running tests.
     */
    protected function setUp(): void
    {
        // Override in subclass for custom setup
    }

    /**
     * Tear down method called after running tests.
     */
    protected function tearDown(): void
    {
        // Override in subclass for custom teardown
    }
}
