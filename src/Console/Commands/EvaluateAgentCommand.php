<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Console\Commands;

use AgenticOrchestrator\Evaluation\EvaluationResult;
use AgenticOrchestrator\Evaluation\Models\AgentEvaluation;
use AgenticOrchestrator\Evaluation\TestSuite;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;
use Throwable;

/**
 * Evaluate Agent Command - Runs evaluation test suites against agents.
 */
class EvaluateAgentCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'agent:evaluate
                            {suite : The test suite class to run}
                            {--team= : Team ID to scope the agent to}
                            {--case= : Run a specific test case by name}
                            {--no-metrics : Skip LLM metric evaluation}
                            {--json : Output results as JSON}
                            {--save : Save results to database}
                            {--verbose : Show detailed output}';

    /**
     * The console command description.
     */
    protected $description = 'Run evaluation tests against an agent';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $suiteClass = (string) $this->argument('suite');

        if (! class_exists($suiteClass)) {
            $this->error("Test suite class not found: {$suiteClass}");

            return self::FAILURE;
        }

        if (! is_subclass_of($suiteClass, TestSuite::class)) {
            $this->error("Class must extend TestSuite: {$suiteClass}");

            return self::FAILURE;
        }

        try {
            /** @var TestSuite $suite */
            $suite = new $suiteClass;

            // Apply options
            $teamId = $this->option('team') ? (string) $this->option('team') : null;
            if ($teamId) {
                $suite->forTeam((int) $teamId);
            }

            if ($this->option('no-metrics')) {
                $suite->withoutMetrics();
            }

            // Create database record if saving
            $evaluation = null;
            if ($this->option('save')) {
                $evaluation = AgentEvaluation::createForEvaluation(
                    suiteClass: $suiteClass,
                    agentClass: $suite->getAgentClass(),
                    teamId: $teamId ? (int) $teamId : null,
                );
            }

            $this->info("Running evaluation: {$suiteClass}");
            $this->info("Agent: {$suite->getAgentClass()}");
            $this->newLine();

            // Run specific case or full suite
            $caseName = $this->option('case') ? (string) $this->option('case') : null;
            if ($caseName) {
                $result = $suite->runCase($caseName);

                if ($result === null) {
                    $this->error("Test case not found: {$caseName}");

                    return self::FAILURE;
                }

                // Wrap single result in EvaluationResult
                $evaluationResult = new EvaluationResult(
                    suiteClass: $suiteClass,
                    agentClass: $suite->getAgentClass(),
                    results: [$result],
                    totalDurationMs: $result->durationMs,
                );
            } else {
                $evaluationResult = $suite->run();
            }

            // Output results
            if ($this->option('json')) {
                $this->outputJson($evaluationResult);
            } else {
                $this->outputTable($evaluationResult);
            }

            // Save to database
            if ($evaluation !== null) {
                $evaluation->markCompleted($evaluationResult);
                $this->info("Results saved: {$evaluation->evaluation_id}");
            }

            return $evaluationResult->allPassed() ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $e) {
            $this->error("Evaluation failed: {$e->getMessage()}");

            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            if (isset($evaluation)) {
                $evaluation->markFailed($e->getMessage());
            }

            return self::FAILURE;
        }
    }

    /**
     * Output results as a table.
     */
    protected function outputTable(EvaluationResult $result): void
    {
        // Summary
        $summary = $result->summary();
        $this->info('Results Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Tests', $summary['total']],
                ['Passed', "<fg=green>{$summary['passed']}</>"],
                ['Failed', $summary['failed'] > 0 ? "<fg=red>{$summary['failed']}</>" : $summary['failed']],
                ['Errors', $summary['errors'] > 0 ? "<fg=yellow>{$summary['errors']}</>" : $summary['errors']],
                ['Pass Rate', "{$summary['pass_rate']}%"],
                ['Avg Metric Score', round($summary['average_metric_score'] * 100, 1).'%'],
                ['Duration', round($summary['duration_ms']).'ms'],
            ]
        );

        $this->newLine();

        // Individual test results
        $rows = [];
        foreach ($result->results as $testResult) {
            $status = match ($testResult->status) {
                'passed' => '<fg=green>✓ PASS</>',
                'failed' => '<fg=red>✗ FAIL</>',
                'error' => '<fg=yellow>⚠ ERROR</>',
                'skipped' => '<fg=gray>○ SKIP</>',
                default => $testResult->status,
            };

            $details = [];
            foreach ($testResult->getFailedAssertions() as $name => $assertionResult) {
                $details[] = "{$name}: {$assertionResult->message}";
            }

            $rows[] = [
                $testResult->testCase->name,
                $status,
                round($testResult->durationMs).'ms',
                $testResult->getAverageMetricScore() > 0
                    ? round($testResult->getAverageMetricScore() * 100, 1).'%'
                    : '-',
                implode('; ', $details) ?: '-',
            ];
        }

        $this->table(
            ['Test Case', 'Status', 'Duration', 'Score', 'Details'],
            $rows
        );

        // Metric averages
        $metricAverages = $result->averageMetricsByName();
        if (! empty($metricAverages)) {
            $this->newLine();
            $this->info('Metric Scores:');
            $metricRows = [];
            foreach ($metricAverages as $name => $score) {
                $percentage = round($score * 100, 1);
                $color = $score >= 0.7 ? 'green' : ($score >= 0.5 ? 'yellow' : 'red');
                $metricRows[] = [ucfirst($name), "<fg={$color}>{$percentage}%</>"];
            }
            $this->table(['Metric', 'Average Score'], $metricRows);
        }

        // Verbose output
        if ($this->option('verbose')) {
            $this->newLine();
            foreach ($result->failed() as $failedResult) {
                $this->error("Failed: {$failedResult->testCase->name}");
                $this->line("  Input: {$failedResult->testCase->input}");
                $this->line('  Output: '.substr($failedResult->actualOutput ?? '', 0, 200));

                foreach ($failedResult->assertionResults as $name => $assertion) {
                    if (! $assertion->passed) {
                        $this->line("  <fg=red>{$name}: {$assertion->message}</>");
                    }
                }

                foreach ($failedResult->metricResults as $name => $metric) {
                    if (! $metric->passes()) {
                        $this->line("  <fg=red>{$name}: {$metric->reasoning}</>");
                    }
                }
            }

            foreach ($result->errors() as $errorResult) {
                $this->error("Error: {$errorResult->testCase->name}");
                $this->line("  {$errorResult->error?->getMessage()}");
            }
        }
    }

    /**
     * Output results as JSON.
     */
    protected function outputJson(EvaluationResult $result): void
    {
        $this->line(json_encode($result->toArray(), JSON_PRETTY_PRINT));
    }
}
