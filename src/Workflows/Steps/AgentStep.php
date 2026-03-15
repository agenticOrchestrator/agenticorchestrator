<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Steps;

use AgenticOrchestrator\Agents\AgentManager;
use AgenticOrchestrator\Contracts\AgentInterface;
use AgenticOrchestrator\Workflows\WorkflowContext;
use Closure;

/**
 * Agent Step - Executes an AI agent as part of a workflow.
 *
 * Integrates agents seamlessly into workflow orchestration.
 */
class AgentStep extends Step
{
    /**
     * The agent instance or name.
     */
    protected AgentInterface|string $agent;

    /**
     * Message template or callback.
     *
     * @var string|Closure(WorkflowContext): string
     */
    protected string|Closure $message;

    /**
     * Additional context for the agent.
     *
     * @var array<string, mixed>|Closure(WorkflowContext): array<string, mixed>
     */
    protected array|Closure $agentContext = [];

    /**
     * Whether to use streaming.
     */
    protected bool $stream = false;

    /**
     * Create a new agent step.
     *
     * @param  AgentInterface|string  $agent  Agent instance or registered name
     * @param  string|Closure  $message  Message template or callback
     */
    public function __construct(AgentInterface|string $agent, string|Closure $message)
    {
        $this->agent = $agent;
        $this->message = $message;
    }

    /**
     * Create an agent step.
     */
    public static function make(AgentInterface|string $agent, string|Closure $message): static
    {
        return new static($agent, $message);
    }

    /**
     * Set additional context for the agent.
     *
     * @param  array<string, mixed>|Closure  $context
     */
    public function withContext(array|Closure $context): static
    {
        $this->agentContext = $context;

        return $this;
    }

    /**
     * Enable streaming mode.
     */
    public function streaming(): static
    {
        $this->stream = true;

        return $this;
    }

    /**
     * Execute the agent step.
     */
    protected function handle(WorkflowContext $context): mixed
    {
        $agent = $this->resolveAgent($context);

        // Apply tenant scope if available
        $tenant = $context->getTenant();
        if ($tenant && method_exists($agent, 'forTeam')) {
            $agent = $agent->forTeam($tenant->getModel());
        }

        // Apply user scope if available
        $user = $context->getUser();
        if ($user && method_exists($agent, 'forUser')) {
            $agent = $agent->forUser($user);
        }

        // Build the message
        $message = $this->buildMessage($context);

        // Build agent context
        $agentContext = $this->buildAgentContext($context);

        // Execute agent
        if ($this->stream) {
            $response = $agent->stream($message, $agentContext);

            // For workflows, we collect the full stream
            $content = '';
            foreach ($response as $chunk) {
                $content .= $chunk->content;
            }

            return [
                'content' => $content,
                'streamed' => true,
            ];
        }

        $response = $agent->respond($message, $agentContext);

        return [
            'content' => $response->content,
            'tool_calls' => $response->getToolCalls(),
            'usage' => $response->usage,
            'latency' => $response->latency,
        ];
    }

    /**
     * Resolve the agent instance.
     */
    protected function resolveAgent(WorkflowContext $context): AgentInterface
    {
        if ($this->agent instanceof AgentInterface) {
            return $this->agent;
        }

        // Resolve from agent manager
        $manager = app(AgentManager::class);
        $tenant = $context->getTenant();

        if ($tenant) {
            return $manager->makeForTeam($this->agent, $tenant->getTenantKey());
        }

        return $manager->make($this->agent);
    }

    /**
     * Build the message from template or callback.
     */
    protected function buildMessage(WorkflowContext $context): string
    {
        if ($this->message instanceof Closure) {
            return ($this->message)($context);
        }

        // Simple variable substitution
        $message = $this->message;

        foreach ($context->getData() as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $message = str_replace("{{$key}}", (string) $value, $message);
            }
        }

        return $message;
    }

    /**
     * Build the agent context.
     *
     * @return array<string, mixed>
     */
    protected function buildAgentContext(WorkflowContext $context): array
    {
        if ($this->agentContext instanceof Closure) {
            return ($this->agentContext)($context);
        }

        return $this->agentContext;
    }
}
