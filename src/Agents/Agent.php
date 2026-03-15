<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Agents;

use AgenticOrchestrator\Agents\Concerns\HasDelegation;
use AgenticOrchestrator\Agents\Concerns\HasMemory;
use AgenticOrchestrator\Agents\Concerns\HasStreaming;
use AgenticOrchestrator\Agents\Concerns\HasTeamScope;
use AgenticOrchestrator\Agents\Concerns\HasTools;
use AgenticOrchestrator\Contracts\AgentInterface;
use AgenticOrchestrator\Contracts\TeamScopedInterface;
use AgenticOrchestrator\Conversations\Message;
use AgenticOrchestrator\Events\AgentEvents\AgentFailed;
use AgenticOrchestrator\Events\AgentEvents\AgentResponded;
use AgenticOrchestrator\Events\AgentEvents\AgentStarted;
use AgenticOrchestrator\Providers\ProviderManager;
use AgenticOrchestrator\Streaming\StreamResponse;

/**
 * Base class for all AI agents.
 *
 * Extend this class to create your own agents with custom
 * instructions, tools, and behavior.
 *
 * @example
 * ```php
 * class CustomerSupportAgent extends Agent
 * {
 *     protected string $name = 'Customer Support';
 *     protected string $model = 'gpt-4o';
 *
 *     public function instructions(): string
 *     {
 *         return "You are a helpful customer support agent.";
 *     }
 *
 *     #[Tool('Look up order')]
 *     public function lookupOrder(string $orderId): array
 *     {
 *         return $this->team->orders()->find($orderId)->toArray();
 *     }
 * }
 * ```
 */
abstract class Agent implements AgentInterface, TeamScopedInterface
{
    use HasDelegation;
    use HasMemory;
    use HasStreaming;
    use HasTeamScope;
    use HasTools;

    /**
     * The agent's display name.
     */
    protected string $name = '';

    /**
     * The agent's description.
     */
    protected string $description = '';

    /**
     * The LLM model to use.
     */
    protected string $model = 'gpt-4o';

    /**
     * The LLM provider.
     */
    protected string $provider = 'openai';

    /**
     * Temperature for responses (0-2).
     */
    protected float $temperature = 0.7;

    /**
     * Maximum tokens for response.
     */
    protected ?int $maxTokens = null;

    /**
     * Memory configuration.
     *
     * @var array{driver?: string, namespace?: string, ttl?: int, vector_store?: string}
     */
    protected array $memory = [
        'driver' => 'cache',
    ];

    /**
     * Agent capabilities configuration.
     *
     * @var array{can_delegate?: bool, can_be_delegate?: bool, can_use_rag?: bool, can_stream?: bool, max_iterations?: int}
     */
    protected array $capabilities = [
        'can_delegate' => false,
        'can_be_delegate' => true,
        'can_use_rag' => false,
        'can_stream' => true,
        'max_iterations' => 10,
    ];

    /**
     * Whether this is a system agent (platform-wide).
     */
    protected bool $isSystem = false;

    /**
     * External tool classes to register.
     *
     * @var array<int, class-string>
     */
    protected array $tools = [];

    /**
     * Create a new agent instance.
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Get the agent's unique identifier.
     */
    public function getId(): string
    {
        return static::class;
    }

    /**
     * Get the agent's display name.
     */
    public function getName(): string
    {
        return $this->name ?: class_basename(static::class);
    }

    /**
     * Get the agent's description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get the LLM model identifier.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the LLM provider name.
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Get the system instructions/prompt.
     *
     * Override this method in your agent to provide custom instructions.
     */
    abstract public function instructions(): string;

    /**
     * Process a message and return a response.
     *
     * @param  string  $message  The user's message
     * @param  array<string, mixed>  $context  Additional context
     */
    public function respond(string $message, array $context = []): AgentResponse
    {
        $this->fireEvent(new AgentStarted($this, $message));

        $startTime = microtime(true);

        try {
            $agentContext = $this->buildContext($message, $context);
            $response = $this->executeWithTools($agentContext);

            // Store conversation in memory
            $this->storeConversation($message, $response);

            $this->fireEvent(new AgentResponded($this, $response));

            return $response;
        } catch (\Throwable $e) {
            $this->fireEvent(new AgentFailed($this, $e));
            throw $e;
        }
    }

    /**
     * Process a message with streaming response.
     *
     * Uses the HasStreaming trait to create a StreamResponse that yields
     * chunks. By default, falls back to simulated streaming (word-by-word)
     * from a non-streaming response. Override createStreamGenerator() in
     * your agent subclass to integrate with Prism PHP's native streaming.
     *
     * @param  string  $message  The user's message
     * @param  array<string, mixed>  $context  Additional context
     */
    public function stream(string $message, array $context = []): StreamResponse
    {
        $this->fireEvent(new AgentStarted($this, $message));

        $generator = $this->createStreamGenerator($message, $context);

        return new StreamResponse($generator);
    }

    /**
     * Build the execution context.
     *
     * @param  array<string, mixed>  $context
     */
    protected function buildContext(string $message, array $context): AgentContext
    {
        // Get conversation history from memory
        $history = $this->getMemory()->getConversationHistory(
            config('agent-orchestrator.conversation.max_history', 50)
        );

        return new AgentContext(
            agent: $this,
            message: $message,
            team: $this->team,
            user: $this->user,
            history: $history,
            additionalContext: $context,
        );
    }

    /**
     * Execute the agent with tool support.
     */
    protected function executeWithTools(AgentContext $context): AgentResponse
    {
        /** @var ProviderManager $providerManager */
        $providerManager = app(ProviderManager::class);

        $toolSchemas = $this->getToolSchemas();
        $maxIterations = $this->capabilities['max_iterations'] ?? 10;

        $allToolCalls = [];
        $response = null;

        do {
            $messages = $context->buildMessages($this->instructions());

            $providerResponse = $providerManager->chat(
                provider: $this->provider,
                model: $this->model,
                messages: $messages,
                tools: $toolSchemas,
                temperature: $this->temperature,
                maxTokens: $this->maxTokens,
            );

            // Check for tool calls
            if (! empty($providerResponse['tool_calls'])) {
                $toolResults = $this->executeToolCalls($providerResponse['tool_calls']);

                // Add results to context for next iteration
                $formattedResults = array_map(fn ($result) => [
                    'tool_call_id' => $result->toolCallId,
                    'name' => $result->name,
                    'arguments' => $result->arguments,
                    'result' => $result->result,
                ], $toolResults);

                $context->addToolResults($formattedResults);
                $allToolCalls = array_merge($allToolCalls, $formattedResults);
            }

            $response = $providerResponse;
            $iteration = $context->incrementIteration();

        } while (! empty($response['tool_calls']) && $iteration < $maxIterations);

        return new AgentResponse(
            content: $response['content'] ?? '',
            toolCalls: $allToolCalls,
            usage: $response['usage'] ?? [
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
            ],
            metadata: $response['metadata'] ?? [],
            latency: $response['latency'] ?? null,
            finishReason: $response['finish_reason'] ?? null,
        );
    }

    /**
     * Store conversation in memory.
     */
    protected function storeConversation(string $userMessage, AgentResponse $response): void
    {
        $memory = $this->getMemory();

        // Add user message
        $memory->addMessage(Message::user($userMessage));

        // Add assistant response
        $memory->addMessage(Message::assistant(
            $response->content,
            $response->hasToolCalls() ? $response->toolCalls : null,
        ));
    }

    /**
     * Get the agent's full configuration.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'model' => $this->model,
            'provider' => $this->provider,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'memory' => $this->memory,
            'capabilities' => $this->capabilities,
            'is_system' => $this->isSystem,
            'tools' => array_map(
                fn ($t) => $t['name'],
                $this->getTools()->toArray()
            ),
        ];
    }

    /**
     * Fire an event if the event dispatcher is available.
     */
    protected function fireEvent(object $event): void
    {
        if (function_exists('event')) {
            event($event);
        }
    }

    /**
     * Set the model for this agent instance.
     */
    public function withModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Set the provider for this agent instance.
     */
    public function withProvider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Set the temperature for this agent instance.
     */
    public function withTemperature(float $temperature): static
    {
        $this->temperature = $temperature;

        return $this;
    }

    /**
     * Set max tokens for this agent instance.
     */
    public function withMaxTokens(?int $maxTokens): static
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }
}
