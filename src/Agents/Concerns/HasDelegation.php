<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Agents\Concerns;

use AgenticOrchestrator\Agents\AgentManager;
use AgenticOrchestrator\Agents\AgentResponse;
use AgenticOrchestrator\Contracts\AgentInterface;
use AgenticOrchestrator\Workflows\Events\AgentDelegated;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * HasDelegation - Enables agents to delegate tasks to other agents.
 *
 * Provides capability for agents to invoke other specialized agents
 * to handle sub-tasks, enabling hierarchical agent architectures.
 */
trait HasDelegation
{
    /**
     * Whether delegation is enabled.
     */
    protected bool $delegationEnabled = true;

    /**
     * Maximum delegation depth to prevent infinite loops.
     */
    protected int $maxDelegationDepth = 5;

    /**
     * Current delegation depth.
     */
    protected int $currentDelegationDepth = 0;

    /**
     * Delegation history for this execution.
     *
     * @var array<int, array{agent: string, message: string, result: string}>
     */
    protected array $delegationHistory = [];

    /**
     * Parent agent if this is a delegated call.
     */
    protected ?AgentInterface $parentAgent = null;

    /**
     * Delegate a task to another agent.
     *
     * @param  AgentInterface|string  $agent  The agent to delegate to
     * @param  string  $message  The message/task for the agent
     * @param  array<string, mixed>  $context  Additional context
     */
    public function delegate(
        AgentInterface|string $agent,
        string $message,
        array $context = [],
    ): AgentResponse {
        // Check if delegation is enabled
        if (! $this->delegationEnabled) {
            throw new RuntimeException('Delegation is disabled for this agent.');
        }

        // Check delegation depth
        if ($this->currentDelegationDepth >= $this->maxDelegationDepth) {
            throw new RuntimeException(
                "Maximum delegation depth ({$this->maxDelegationDepth}) exceeded."
            );
        }

        // Resolve agent instance
        $resolvedAgent = $this->resolveAgent($agent);

        // Set up delegation context
        $resolvedAgent->setDelegationContext(
            parent: $this,
            depth: $this->currentDelegationDepth + 1,
        );

        // Dispatch delegation event
        $this->dispatchDelegationEvent($resolvedAgent, $message);

        // Log delegation
        Log::debug('Agent delegation', [
            'from' => $this->getName(),
            'to' => $resolvedAgent->getName(),
            'depth' => $this->currentDelegationDepth + 1,
        ]);

        // Execute the delegation
        try {
            $response = $resolvedAgent->respond($message, $context);

            // Record in history
            $this->delegationHistory[] = [
                'agent' => $resolvedAgent->getName(),
                'message' => $message,
                'result' => $response->content ?? '',
            ];

            return $response;
        } catch (\Throwable $e) {
            Log::error('Delegation failed', [
                'from' => $this->getName(),
                'to' => $resolvedAgent->getName(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Delegate to multiple agents in parallel.
     *
     * @param  array<AgentInterface|string>  $agents
     * @param  array<string>  $messages  Messages for each agent (or single message for all)
     * @param  array<string, mixed>  $context  Additional context
     * @return array<string, AgentResponse>
     */
    public function delegateParallel(
        array $agents,
        array $messages,
        array $context = [],
    ): array {
        // If single message, use for all agents
        if (count($messages) === 1 && count($agents) > 1) {
            $messages = array_fill(0, count($agents), $messages[0]);
        }

        if (count($agents) !== count($messages)) {
            throw new RuntimeException(
                'Number of agents must match number of messages.'
            );
        }

        $results = [];

        // In a real implementation, this could use async/parallel execution
        foreach ($agents as $index => $agent) {
            $resolvedAgent = $this->resolveAgent($agent);
            $results[$resolvedAgent->getName()] = $this->delegate(
                $agent,
                $messages[$index],
                $context,
            );
        }

        return $results;
    }

    /**
     * Delegate with retry on failure.
     *
     * @param  AgentInterface|string  $agent  The agent to delegate to
     * @param  string  $message  The message/task
     * @param  array<string, mixed>  $context  Additional context
     * @param  int  $maxRetries  Maximum retry attempts
     * @param  int  $delayMs  Delay between retries in milliseconds
     */
    public function delegateWithRetry(
        AgentInterface|string $agent,
        string $message,
        array $context = [],
        int $maxRetries = 3,
        int $delayMs = 1000,
    ): AgentResponse {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $this->delegate($agent, $message, $context);
            } catch (\Throwable $e) {
                $lastException = $e;

                Log::warning("Delegation attempt {$attempt} failed", [
                    'agent' => is_string($agent) ? $agent : $agent->getName(),
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $maxRetries) {
                    usleep($delayMs * 1000);
                }
            }
        }

        throw $lastException;
    }

    /**
     * Check if this agent can delegate.
     */
    public function canDelegate(): bool
    {
        return $this->delegationEnabled
            && $this->currentDelegationDepth < $this->maxDelegationDepth;
    }

    /**
     * Check if this agent can be delegated to.
     *
     * This method checks if the agent can accept delegated tasks
     * from other agents. Override this to customize delegation acceptance.
     */
    public function canBeDelegate(): bool
    {
        return $this->capabilities['can_be_delegate'] ?? true;
    }

    /**
     * Enable delegation.
     */
    public function enableDelegation(): static
    {
        $this->delegationEnabled = true;

        return $this;
    }

    /**
     * Disable delegation.
     */
    public function disableDelegation(): static
    {
        $this->delegationEnabled = false;

        return $this;
    }

    /**
     * Set maximum delegation depth.
     */
    public function maxDelegationDepth(int $depth): static
    {
        $this->maxDelegationDepth = $depth;

        return $this;
    }

    /**
     * Set delegation context (called when this agent is delegated to).
     */
    public function setDelegationContext(AgentInterface $parent, int $depth): void
    {
        $this->parentAgent = $parent;
        $this->currentDelegationDepth = $depth;
    }

    /**
     * Get the parent agent if this is a delegated call.
     */
    public function getParentAgent(): ?AgentInterface
    {
        return $this->parentAgent;
    }

    /**
     * Get current delegation depth.
     */
    public function getDelegationDepth(): int
    {
        return $this->currentDelegationDepth;
    }

    /**
     * Check if this is a delegated execution.
     */
    public function isDelegated(): bool
    {
        return $this->parentAgent !== null;
    }

    /**
     * Get delegation history.
     *
     * @return array<int, array{agent: string, message: string, result: string}>
     */
    public function getDelegationHistory(): array
    {
        return $this->delegationHistory;
    }

    /**
     * Clear delegation history.
     */
    public function clearDelegationHistory(): void
    {
        $this->delegationHistory = [];
    }

    /**
     * Resolve an agent instance.
     */
    protected function resolveAgent(AgentInterface|string $agent): AgentInterface
    {
        if ($agent instanceof AgentInterface) {
            return $agent;
        }

        // Resolve through agent manager
        $manager = app(AgentManager::class);

        // Pass team scope if available
        if (method_exists($this, 'getTeamId') && $this->getTeamId() !== null) {
            return $manager->makeForTeam($agent, $this->getTeamId());
        }

        return $manager->make($agent);
    }

    /**
     * Dispatch delegation event.
     */
    protected function dispatchDelegationEvent(AgentInterface $target, string $message): void
    {
        if (! app()->bound(Dispatcher::class)) {
            return;
        }

        $events = app(Dispatcher::class);

        $events->dispatch(new AgentDelegated(
            fromAgent: $this->getName(),
            toAgent: $target->getName(),
            message: $message,
            depth: $this->currentDelegationDepth + 1,
        ));
    }
}
