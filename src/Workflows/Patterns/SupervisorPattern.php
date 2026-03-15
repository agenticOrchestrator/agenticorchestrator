<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Patterns;

use AgenticOrchestrator\Agents\AgentManager;
use AgenticOrchestrator\Contracts\AgentInterface;
use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\Steps\AgentStep;
use AgenticOrchestrator\Workflows\WorkflowContext;
use Closure;

/**
 * Supervisor Pattern - An agent orchestrates other agents.
 *
 * A supervisor agent decides which worker agents to invoke
 * and in what order, based on the task at hand.
 */
class SupervisorPattern implements StepInterface
{
    /**
     * The supervisor agent.
     */
    protected AgentInterface|string $supervisor;

    /**
     * Available worker agents.
     *
     * @var array<string, AgentInterface|string>
     */
    protected array $workers = [];

    /**
     * Maximum delegation rounds.
     */
    protected int $maxRounds = 10;

    /**
     * Pattern name.
     */
    protected string $name = 'supervisor';

    /**
     * Custom routing logic.
     */
    protected ?Closure $router = null;

    /**
     * Whether supervisor can delegate to multiple workers at once.
     */
    protected bool $allowParallelDelegation = false;

    /**
     * Create a new supervisor pattern.
     *
     * @param  array<string, AgentInterface|string>  $workers
     */
    public function __construct(
        AgentInterface|string $supervisor,
        array $workers = [],
    ) {
        $this->supervisor = $supervisor;
        $this->workers = $workers;
    }

    /**
     * Create a supervisor pattern.
     *
     * @param  array<string, AgentInterface|string>  $workers
     */
    public static function make(AgentInterface|string $supervisor, array $workers = []): static
    {
        return new static($supervisor, $workers);
    }

    /**
     * Add a worker agent.
     */
    public function addWorker(string $name, AgentInterface|string $agent): static
    {
        $this->workers[$name] = $agent;

        return $this;
    }

    /**
     * Set maximum delegation rounds.
     */
    public function maxRounds(int $rounds): static
    {
        $this->maxRounds = $rounds;

        return $this;
    }

    /**
     * Set custom routing logic.
     *
     * @param  Closure(string, WorkflowContext): StepInterface|null  $router
     */
    public function withRouter(Closure $router): static
    {
        $this->router = $router;

        return $this;
    }

    /**
     * Allow parallel delegation to multiple workers.
     */
    public function allowParallel(): static
    {
        $this->allowParallelDelegation = true;

        return $this;
    }

    /**
     * Execute the supervisor pattern.
     */
    public function execute(WorkflowContext $context): StepResult
    {
        $supervisor = $this->resolveSupervisor($context);
        $results = [];
        $round = 0;

        // Build worker descriptions for supervisor context
        $workerDescriptions = $this->buildWorkerDescriptions();

        // Initial task from context
        $task = $context->get('task') ?? $context->get('message') ?? '';

        while ($round < $this->maxRounds) {
            $round++;

            // Ask supervisor what to do
            $supervisorPrompt = $this->buildSupervisorPrompt($task, $workerDescriptions, $results);

            $supervisorResponse = $supervisor->respond($supervisorPrompt, [
                'workers' => array_keys($this->workers),
                'round' => $round,
                'previous_results' => $results,
            ]);

            // Parse supervisor decision
            $decision = $this->parseSupervisorDecision($supervisorResponse->content);

            // Check if supervisor says we're done
            if ($decision['action'] === 'complete') {
                return StepResult::success([
                    'final_response' => $decision['response'] ?? $supervisorResponse->content,
                    'rounds' => $round,
                    'worker_results' => $results,
                ]);
            }

            // Check if supervisor needs human input
            if ($decision['action'] === 'escalate') {
                return StepResult::waiting(
                    $decision['reason'] ?? 'Supervisor escalated to human',
                    [
                        'escalation_reason' => $decision['reason'],
                        'current_state' => $results,
                    ]
                );
            }

            // Delegate to worker(s)
            if ($decision['action'] === 'delegate') {
                $delegations = (array) ($decision['workers'] ?? [$decision['worker'] ?? null]);
                $delegationTask = $decision['task'] ?? $task;

                foreach ($delegations as $workerName) {
                    if (! isset($this->workers[$workerName])) {
                        $results["round_{$round}_{$workerName}"] = [
                            'error' => "Unknown worker: {$workerName}",
                        ];

                        continue;
                    }

                    $workerResult = $this->executeWorker(
                        $workerName,
                        $delegationTask,
                        $context
                    );

                    $results["round_{$round}_{$workerName}"] = $workerResult;
                }

                // Update task with worker results for next round
                $task = $delegationTask;
            }
        }

        // Reached max rounds
        return StepResult::failed(
            "Supervisor exceeded maximum rounds ({$this->maxRounds})",
            metadata: ['rounds' => $round, 'worker_results' => $results]
        );
    }

    /**
     * Resolve the supervisor agent.
     */
    protected function resolveSupervisor(WorkflowContext $context): AgentInterface
    {
        if ($this->supervisor instanceof AgentInterface) {
            $agent = $this->supervisor;
        } else {
            $manager = app(AgentManager::class);
            $tenant = $context->getTenant();

            $agent = $tenant
                ? $manager->makeForTeam($this->supervisor, $tenant->getTenantKey())
                : $manager->make($this->supervisor);
        }

        // Apply scopes
        if ($context->getTenant() && method_exists($agent, 'forTeam')) {
            $agent = $agent->forTeam($context->getTenant()->getModel());
        }

        if ($context->getUser() && method_exists($agent, 'forUser')) {
            $agent = $agent->forUser($context->getUser());
        }

        return $agent;
    }

    /**
     * Build descriptions of available workers.
     *
     * @return array<string, string>
     */
    protected function buildWorkerDescriptions(): array
    {
        $descriptions = [];

        foreach ($this->workers as $name => $worker) {
            if ($worker instanceof AgentInterface) {
                $descriptions[$name] = method_exists($worker, 'getDescription')
                    ? $worker->getDescription()
                    : "Worker agent: {$name}";
            } else {
                $descriptions[$name] = "Worker agent: {$name}";
            }
        }

        return $descriptions;
    }

    /**
     * Build the supervisor prompt.
     *
     * @param  array<string, string>  $workerDescriptions
     * @param  array<string, mixed>  $previousResults
     */
    protected function buildSupervisorPrompt(
        string $task,
        array $workerDescriptions,
        array $previousResults
    ): string {
        $workersSection = '';
        foreach ($workerDescriptions as $name => $desc) {
            $workersSection .= "- {$name}: {$desc}\n";
        }

        $resultsSection = '';
        if (! empty($previousResults)) {
            $resultsSection = "\n\nPrevious worker results:\n".json_encode($previousResults, JSON_PRETTY_PRINT);
        }

        return <<<PROMPT
You are a supervisor agent coordinating a team of worker agents.

Current task: {$task}

Available workers:
{$workersSection}
{$resultsSection}

Decide the next action. Respond with a JSON object:
- To delegate: {"action": "delegate", "worker": "worker_name", "task": "specific task for worker"}
- To complete: {"action": "complete", "response": "final response to user"}
- To escalate: {"action": "escalate", "reason": "why human input is needed"}
PROMPT;
    }

    /**
     * Parse the supervisor's decision.
     *
     * @return array{action: string, worker?: string, workers?: array, task?: string, response?: string, reason?: string}
     */
    protected function parseSupervisorDecision(string $response): array
    {
        // Try to extract JSON from response
        if (preg_match('/\{[^{}]*\}/s', $response, $matches)) {
            $decoded = json_decode($matches[0], true);

            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['action'])) {
                return $decoded;
            }
        }

        // Default: assume the response is the final answer
        return [
            'action' => 'complete',
            'response' => $response,
        ];
    }

    /**
     * Execute a worker agent.
     *
     * @return array<string, mixed>
     */
    protected function executeWorker(
        string $workerName,
        string $task,
        WorkflowContext $context
    ): array {
        $worker = $this->workers[$workerName];

        // Use custom router if provided
        if ($this->router !== null) {
            $step = ($this->router)($workerName, $context);

            if ($step instanceof StepInterface) {
                $result = $step->execute($context->with(['task' => $task]));

                return [
                    'status' => $result->status,
                    'output' => $result->output,
                    'message' => $result->message,
                ];
            }
        }

        // Create agent step for worker
        $step = AgentStep::make($worker, $task);

        $result = $step->execute($context);

        return [
            'status' => $result->status,
            'output' => $result->output,
            'message' => $result->message,
        ];
    }

    /**
     * Get the pattern name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the pattern name.
     */
    public function as(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the output key.
     */
    public function getOutputKey(): ?string
    {
        return null;
    }

    /**
     * Check if retryable.
     */
    public function isRetryable(): bool
    {
        return true;
    }

    /**
     * Get max retries.
     */
    public function getMaxRetries(): int
    {
        return 1;
    }

    /**
     * Get timeout.
     */
    public function getTimeout(): ?int
    {
        return null;
    }

    /**
     * Check if requires human approval.
     */
    public function requiresHumanApproval(): bool
    {
        return false;
    }

    /**
     * Get dependencies.
     *
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return [];
    }
}
