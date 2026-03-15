<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Agents;

use AgenticOrchestrator\Contracts\AgentInterface;
use AgenticOrchestrator\Conversations\Message;
use Illuminate\Support\Arr;

/**
 * Execution context for an agent invocation.
 *
 * Contains all the information needed to execute an agent request,
 * including the message, conversation history, tool results, and
 * team/user scoping.
 */
class AgentContext
{
    /**
     * Tool calls made during this execution.
     *
     * @var array<int, array{id: string, name: string, arguments: array<string, mixed>, result: mixed}>
     */
    protected array $toolCalls = [];

    /**
     * Tool results to include in next iteration.
     *
     * @var array<int, array{tool_call_id: string, content: string}>
     */
    protected array $toolResults = [];

    /**
     * Current iteration count (for tool call loops).
     */
    protected int $iteration = 0;

    /**
     * @param  AgentInterface  $agent  The agent being executed
     * @param  string  $message  The user's input message
     * @param  object|null  $team  The team scope
     * @param  object|null  $user  The user scope
     * @param  array<int, Message>  $history  Conversation history
     * @param  array<string, mixed>  $additionalContext  Additional context data
     */
    public function __construct(
        protected readonly AgentInterface $agent,
        protected readonly string $message,
        protected readonly ?object $team = null,
        protected readonly ?object $user = null,
        protected array $history = [],
        protected array $additionalContext = [],
    ) {}

    /**
     * Get the agent instance.
     */
    public function getAgent(): AgentInterface
    {
        return $this->agent;
    }

    /**
     * Get the user's message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get the team scope.
     */
    public function getTeam(): ?object
    {
        return $this->team;
    }

    /**
     * Get the user scope.
     */
    public function getUser(): ?object
    {
        return $this->user;
    }

    /**
     * Get conversation history.
     *
     * @return array<int, Message>
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    /**
     * Add a message to the history.
     */
    public function addToHistory(Message $message): self
    {
        $this->history[] = $message;

        return $this;
    }

    /**
     * Get additional context value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->additionalContext, $key, $default);
    }

    /**
     * Set additional context value.
     */
    public function set(string $key, mixed $value): self
    {
        Arr::set($this->additionalContext, $key, $value);

        return $this;
    }

    /**
     * Check if context has a key.
     */
    public function has(string $key): bool
    {
        return Arr::has($this->additionalContext, $key);
    }

    /**
     * Get all additional context.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->additionalContext;
    }

    /**
     * Add tool results for the next iteration.
     *
     * @param  array<int, array{tool_call_id: string, name: string, result: mixed}>  $results
     */
    public function addToolResults(array $results): self
    {
        foreach ($results as $result) {
            $this->toolCalls[] = [
                'id' => $result['tool_call_id'],
                'name' => $result['name'],
                'arguments' => $result['arguments'] ?? [],
                'result' => $result['result'],
            ];

            $this->toolResults[] = [
                'tool_call_id' => $result['tool_call_id'],
                'content' => is_string($result['result'])
                    ? $result['result']
                    : json_encode($result['result'], JSON_THROW_ON_ERROR),
            ];
        }

        return $this;
    }

    /**
     * Get all tool calls made in this execution.
     *
     * @return array<int, array{id: string, name: string, arguments: array<string, mixed>, result: mixed}>
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    /**
     * Get tool results for the next LLM call.
     *
     * @return array<int, array{tool_call_id: string, content: string}>
     */
    public function getToolResults(): array
    {
        return $this->toolResults;
    }

    /**
     * Clear tool results after they've been sent.
     */
    public function clearToolResults(): self
    {
        $this->toolResults = [];

        return $this;
    }

    /**
     * Increment iteration counter.
     */
    public function incrementIteration(): int
    {
        return ++$this->iteration;
    }

    /**
     * Get current iteration.
     */
    public function getIteration(): int
    {
        return $this->iteration;
    }

    /**
     * Build messages array for LLM request.
     *
     * @return array<int, array{role: string, content: string, tool_calls?: array, tool_call_id?: string}>
     */
    public function buildMessages(string $systemPrompt): array
    {
        $messages = [];

        // System message
        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt,
        ];

        // Conversation history
        foreach ($this->history as $historyMessage) {
            $messages[] = [
                'role' => $historyMessage->role->value,
                'content' => $historyMessage->content,
            ];
        }

        // Current user message
        $messages[] = [
            'role' => 'user',
            'content' => $this->message,
        ];

        // Tool results if any
        foreach ($this->toolResults as $result) {
            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => $result['tool_call_id'],
                'content' => $result['content'],
            ];
        }

        return $messages;
    }

    /**
     * Create context for a delegation.
     */
    public function forDelegation(AgentInterface $delegateAgent, string $task): self
    {
        return new self(
            agent: $delegateAgent,
            message: $task,
            team: $this->team,
            user: $this->user,
            history: [], // Fresh history for delegation
            additionalContext: [
                'delegated_from' => $this->agent->getId(),
                'original_context' => $this->additionalContext,
            ],
        );
    }
}
