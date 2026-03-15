<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Evaluation;

use AgenticOrchestrator\Evaluation\EvaluationResult;
use AgenticOrchestrator\Evaluation\MetricResult;
use AgenticOrchestrator\Evaluation\TestCase;
use AgenticOrchestrator\Evaluation\TestCaseResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

#[CoversClass(EvaluationResult::class)]
class EvaluationResultTest extends PHPUnitTestCase
{
    private function createTestCase(string $name): TestCase
    {
        return new TestCase(name: $name, input: 'test input');
    }

    #[Test]
    public function it_calculates_pass_rate(): void
    {
        $results = [
            TestCaseResult::passed(
                $this->createTestCase('test-1'),
                'output 1',
            ),
            TestCaseResult::passed(
                $this->createTestCase('test-2'),
                'output 2',
            ),
            TestCaseResult::failed(
                $this->createTestCase('test-3'),
                'output 3',
            ),
        ];

        $evaluation = new EvaluationResult(
            suiteClass: 'TestSuite',
            agentClass: 'TestAgent',
            results: $results,
        );

        $this->assertSame(3, $evaluation->total());
        $this->assertSame(2, $evaluation->passedCount());
        $this->assertSame(1, $evaluation->failedCount());
        $this->assertEqualsWithDelta(66.67, $evaluation->passRate(), 0.01);
    }

    #[Test]
    public function it_detects_all_passed(): void
    {
        $results = [
            TestCaseResult::passed($this->createTestCase('test-1'), 'output'),
            TestCaseResult::passed($this->createTestCase('test-2'), 'output'),
        ];

        $evaluation = new EvaluationResult(
            suiteClass: 'TestSuite',
            agentClass: 'TestAgent',
            results: $results,
        );

        $this->assertTrue($evaluation->allPassed());
        $this->assertFalse($evaluation->hasFailed());
    }

    #[Test]
    public function it_calculates_average_metric_score(): void
    {
        $testCase = $this->createTestCase('test-1');

        $results = [
            new TestCaseResult(
                testCase: $testCase,
                status: TestCaseResult::STATUS_PASSED,
                actualOutput: 'output',
                metricResults: [
                    'relevance' => new MetricResult('relevance', 0.8),
                    'accuracy' => new MetricResult('accuracy', 0.9),
                ],
            ),
        ];

        $evaluation = new EvaluationResult(
            suiteClass: 'TestSuite',
            agentClass: 'TestAgent',
            results: $results,
        );

        $this->assertEqualsWithDelta(0.85, $evaluation->averageMetricScore(), 0.001);
    }

    #[Test]
    public function it_groups_metrics_by_name(): void
    {
        $results = [
            new TestCaseResult(
                testCase: $this->createTestCase('test-1'),
                status: TestCaseResult::STATUS_PASSED,
                actualOutput: 'output',
                metricResults: [
                    'relevance' => new MetricResult('relevance', 0.8),
                ],
            ),
            new TestCaseResult(
                testCase: $this->createTestCase('test-2'),
                status: TestCaseResult::STATUS_PASSED,
                actualOutput: 'output',
                metricResults: [
                    'relevance' => new MetricResult('relevance', 0.6),
                ],
            ),
        ];

        $evaluation = new EvaluationResult(
            suiteClass: 'TestSuite',
            agentClass: 'TestAgent',
            results: $results,
        );

        $averages = $evaluation->averageMetricsByName();

        $this->assertEqualsWithDelta(0.7, $averages['relevance'], 0.001);
    }

    #[Test]
    public function it_returns_failed_results(): void
    {
        $results = [
            TestCaseResult::passed($this->createTestCase('test-1'), 'output'),
            TestCaseResult::failed($this->createTestCase('test-2'), 'output'),
            TestCaseResult::error($this->createTestCase('test-3'), new \Exception('Error')),
        ];

        $evaluation = new EvaluationResult(
            suiteClass: 'TestSuite',
            agentClass: 'TestAgent',
            results: $results,
        );

        $this->assertCount(1, $evaluation->failed());
        $this->assertCount(1, $evaluation->errors());
        $this->assertTrue($evaluation->hasErrors());
    }

    #[Test]
    public function it_generates_summary(): void
    {
        $results = [
            TestCaseResult::passed($this->createTestCase('test-1'), 'output'),
        ];

        $evaluation = new EvaluationResult(
            suiteClass: 'App\\Tests\\MySuite',
            agentClass: 'App\\Agents\\MyAgent',
            results: $results,
            totalDurationMs: 150.5,
        );

        $summary = $evaluation->summary();

        $this->assertSame('MySuite', $summary['suite']);
        $this->assertSame('MyAgent', $summary['agent']);
        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['passed']);
        $this->assertSame(100.0, $summary['pass_rate']);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $results = [
            TestCaseResult::passed($this->createTestCase('test-1'), 'output'),
        ];

        $evaluation = new EvaluationResult(
            suiteClass: 'TestSuite',
            agentClass: 'TestAgent',
            results: $results,
        );

        $array = $evaluation->toArray();

        $this->assertArrayHasKey('suite_class', $array);
        $this->assertArrayHasKey('summary', $array);
        $this->assertArrayHasKey('results', $array);
    }
}
