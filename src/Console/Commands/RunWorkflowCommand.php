<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Console\Commands;

use AgenticOrchestrator\Contracts\WorkflowInterface;
use AgenticOrchestrator\MultiTenancy\TenantManager;
use AgenticOrchestrator\Workflows\WorkflowResult;
use AgenticOrchestrator\Workflows\WorkflowRunner;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

/**
 * Run a workflow from the command line.
 */
#[AsCommand(name: 'workflow:run')]
class RunWorkflowCommand extends Command
{
    /**
     * The console command signature.
     */
    protected $signature = 'workflow:run
                            {workflow : The workflow class name to run}
                            {--input=* : Input parameters as key=value pairs}
                            {--team= : Team ID to scope the workflow}
                            {--resume= : Execution ID to resume a paused workflow}
                            {--state= : JSON state for resumption}
                            {--json : Output result as JSON}';

    /**
     * The console command description.
     */
    protected $description = 'Run or resume a workflow';

    /**
     * Execute the console command.
     */
    public function handle(Container $container): int
    {
        $workflowArg = (string) $this->argument('workflow');
        $workflowClass = $this->resolveWorkflowClass($workflowArg);

        if ($workflowClass === null) {
            $this->error("Workflow class not found: {$workflowArg}");

            return self::FAILURE;
        }

        // Parse input parameters
        $input = $this->parseInputParameters();

        // Apply team scope if specified
        $teamOption = $this->option('team');
        if ($teamOption) {
            $this->applyTeamScope($container, (string) $teamOption);
        }

        // Create workflow runner
        $runner = $container->make(WorkflowRunner::class);

        try {
            // Check if resuming
            if ($this->option('resume')) {
                $result = $this->resumeWorkflow($runner, $workflowClass, $input);
            } else {
                $result = $runner->run($workflowClass, $input);
            }

            // Output result
            if ($this->option('json')) {
                $this->line(json_encode($result->toArray(), JSON_PRETTY_PRINT));
            } else {
                $this->displayResult($result);
            }

            return $result->isSuccess() ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $e) {
            $this->error("Workflow execution failed: {$e->getMessage()}");

            if ($this->getOutput()->isVerbose()) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * Resolve the workflow class name.
     *
     * @return class-string<WorkflowInterface>|null
     */
    protected function resolveWorkflowClass(string $workflow): ?string
    {
        // Try as fully qualified class name
        if (class_exists($workflow)) {
            return $workflow;
        }

        // Try in App\Workflows namespace
        $appClass = "App\\Workflows\\{$workflow}";
        if (class_exists($appClass)) {
            return $appClass;
        }

        // Try adding 'Workflow' suffix
        $withSuffix = "App\\Workflows\\{$workflow}Workflow";
        if (class_exists($withSuffix)) {
            return $withSuffix;
        }

        return null;
    }

    /**
     * Parse input parameters from command options.
     *
     * @return array<string, mixed>
     */
    protected function parseInputParameters(): array
    {
        $input = [];

        /** @var array<string> $inputOptions */
        $inputOptions = $this->option('input') ?? [];
        foreach ($inputOptions as $param) {
            if (str_contains($param, '=')) {
                [$key, $value] = explode('=', $param, 2);
                $input[$key] = $this->parseValue($value);
            }
        }

        return $input;
    }

    /**
     * Parse a value to its appropriate type.
     */
    protected function parseValue(string $value): mixed
    {
        // Try JSON decode for complex values
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Boolean
        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }

        // Numeric
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    /**
     * Apply team scope.
     */
    protected function applyTeamScope(Container $container, string $teamId): void
    {
        if (! $container->bound(TenantManager::class)) {
            $this->warn('Multi-tenancy not configured. Team scope will be ignored.');

            return;
        }

        $tenantManager = $container->make(TenantManager::class);
        $tenant = $tenantManager->find($teamId);

        if ($tenant === null) {
            $this->warn("Team not found: {$teamId}. Team scope will be ignored.");

            return;
        }

        $tenantManager->setCurrent($tenant);
    }

    /**
     * Resume a paused workflow.
     */
    protected function resumeWorkflow(
        WorkflowRunner $runner,
        string $workflowClass,
        array $resumeData
    ): WorkflowResult {
        $stateJson = $this->option('state') ? (string) $this->option('state') : null;

        if ($stateJson === null) {
            throw new \InvalidArgumentException(
                'State is required for resumption. Use --state=\'{"key":"value"}\''
            );
        }

        $state = json_decode($stateJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException(
                'Invalid JSON state: '.json_last_error_msg()
            );
        }

        return $runner->resume($workflowClass, $state, $resumeData);
    }

    /**
     * Display the workflow result.
     */
    protected function displayResult(WorkflowResult $result): void
    {
        $this->newLine();

        // Status
        $statusColor = match ($result->status) {
            'success' => 'green',
            'failed' => 'red',
            'pending', 'waiting' => 'yellow',
            default => 'white',
        };

        $this->line("<fg={$statusColor};options=bold>Workflow Status: ".strtoupper($result->status).'</>');

        // Execution info
        $this->line("Execution ID: {$result->executionId}");
        $this->line(sprintf('Duration: %.2f ms', $result->duration));

        // Completed steps
        $completedSteps = $result->getCompletedSteps();
        if (! empty($completedSteps)) {
            $this->newLine();
            $this->info('Completed Steps:');
            foreach ($completedSteps as $step) {
                $this->line("  ✓ {$step}");
            }
        }

        // Failed steps
        $failedSteps = $result->getFailedSteps();
        if (! empty($failedSteps)) {
            $this->newLine();
            $this->error('Failed Steps:');
            foreach ($failedSteps as $step => $info) {
                $this->line("  ✗ {$step}: {$info['message']}");
            }
        }

        // Error
        if ($result->error) {
            $this->newLine();
            $this->error("Error: {$result->error}");
        }

        // Output
        if ($result->output !== null) {
            $this->newLine();
            $this->info('Output:');

            if (is_array($result->output)) {
                $this->line(json_encode($result->output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->line((string) $result->output);
            }
        }

        // Paused state
        if ($result->isPaused()) {
            $this->newLine();
            $this->warn('Workflow is paused. Resume with:');
            $this->line("  php artisan workflow:run {$this->argument('workflow')} --resume={$result->executionId} --state='...'");
        }
    }
}
