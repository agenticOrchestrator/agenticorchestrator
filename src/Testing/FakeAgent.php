<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Testing;

use AgenticOrchestrator\Agents\AgentResponse;
use AgenticOrchestrator\Contracts\AgentInterface;
use AgenticOrchestrator\Contracts\MemoryInterface;
use AgenticOrchestrator\Contracts\ToolInterface;
use AgenticOrchestrator\Streaming\StreamResponse;
use Closure;
use Illuminate\Support\Collection;
use PHPUnit\Framework\AssertionFailedError;

/**
 * Fake Agent - Test double for agent testing.
 *
 * @example
 * ```php
 * $fake = FakeAgent::make()
 *     ->respondWith('Hello!')
 *     ->expectMessage('Hi');
 *
 * $response = $fake->respond('Hi');
 * $fake->assertCalled();
 * ```
 */
class FakeAgent implements AgentInterface
{
    /** @var array<AgentResponse|Closure> */
    protected array $responses = [];

    protected int $responseIndex = 0;

    /** @var array<array{message: string, context: array<string, mixed>}> */
    protected array $calls = [];

    /** @var array<string> */
    protected array $expectedMessages = [];

    protected ?int $teamId = null;

    protected ?string $userId = null;

    protected string $name = 'fake-agent';

    protected string $id = 'fake-agent-id';

    protected string $model = 'gpt-4';

    protected string $provider = 'openai';

    protected ?MemoryInterface $memory = null;

    /** @var Collection<int, ToolInterface> */
    protected Collection $tools;

    /**
     * Create a new fake agent.
     */
    public function __construct()
    {
        $this->tools = collect();
    }

    /**
     * Create a new fake agent.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Set the agent name.
     */
    public function named(string $name): static
    {
        $this->name = $name;
        $this->id = $name.'-id';

        return $this;
    }

    /**
     * Set the agent model.
     */
    public function usingModel(string $model, string $provider = 'openai'): static
    {
        $this->model = $model;
        $this->provider = $provider;

        return $this;
    }

    /**
     * Set a response or sequence of responses.
     *
     * @param  AgentResponse|string|Closure|array<AgentResponse|string|Closure>  $responses
     */
    public function respondWith(AgentResponse|string|Closure|array $responses): static
    {
        if (! is_array($responses)) {
            $responses = [$responses];
        }

        foreach ($responses as $response) {
            if (is_string($response)) {
                $this->responses[] = FakeResponse::text($response);
            } elseif ($response instanceof Closure) {
                $this->responses[] = $response;
            } else {
                $this->responses[] = $response;
            }
        }

        return $this;
    }

    /**
     * Add an expected message.
     */
    public function expectMessage(string $message): static
    {
        $this->expectedMessages[] = $message;

        return $this;
    }

    /**
     * Set the memory instance.
     */
    public function withMemory(MemoryInterface $memory): static
    {
        $this->memory = $memory;

        return $this;
    }

    /**
     * Add tools to the agent.
     *
     * @param  array<ToolInterface>  $tools
     */
    public function withTools(array $tools): static
    {
        $this->tools = collect($tools);

        return $this;
    }

    /**
     * Get the agent ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the agent name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the agent description.
     */
    public function getDescription(): string
    {
        return 'Fake agent for testing';
    }

    /**
     * Get the model.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the provider.
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Get agent instructions.
     */
    public function instructions(): string
    {
        return 'Fake agent for testing';
    }

    /**
     * Respond to a message.
     *
     * @param  array<string, mixed>  $context
     */
    public function respond(string $message, array $context = []): AgentResponse
    {
        $this->calls[] = [
            'message' => $message,
            'context' => $context,
        ];

        if (empty($this->responses)) {
            return FakeResponse::text('Fake response');
        }

        $response = $this->responses[$this->responseIndex] ?? $this->responses[count($this->responses) - 1];
        $this->responseIndex++;

        if ($response instanceof Closure) {
            $result = $response($message, $context);

            return is_string($result) ? FakeResponse::text($result) : $result;
        }

        return $response;
    }

    /**
     * Stream a response.
     *
     * @param  array<string, mixed>  $context
     */
    public function stream(string $message, array $context = []): StreamResponse
    {
        // For testing, just return a fake stream
        $response = $this->respond($message, $context);

        return new StreamResponse(function () use ($response) {
            yield $response->content;
        });
    }

    /**
     * Get tools.
     *
     * @return Collection<int, ToolInterface>
     */
    public function getTools(): Collection
    {
        return $this->tools;
    }

    /**
     * Get memory.
     */
    public function getMemory(): MemoryInterface
    {
        if ($this->memory === null) {
            $this->memory = FakeMemory::make();
        }

        return $this->memory;
    }

    /**
     * Delegate to another agent.
     *
     * @param  array<string, mixed>  $context
     */
    public function delegate(AgentInterface $agent, string $task, array $context = []): AgentResponse
    {
        return $agent->respond($task, $context);
    }

    /**
     * Check if can be delegate.
     */
    public function canBeDelegate(): bool
    {
        return true;
    }

    /**
     * Get config.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return [
            'name' => $this->name,
            'model' => $this->model,
            'provider' => $this->provider,
        ];
    }

    /**
     * Scope to a team.
     */
    public function forTeam(int|string|object $team): static
    {
        $clone = clone $this;
        $clone->teamId = is_object($team) ? (int) $team->getKey() : (int) $team;

        return $clone;
    }

    /**
     * Scope to a user.
     */
    public function forUser(int|string $userId): static
    {
        $clone = clone $this;
        $clone->userId = (string) $userId;

        return $clone;
    }

    /**
     * Assert the agent was called.
     */
    public function assertCalled(): void
    {
        if (empty($this->calls)) {
            throw new AssertionFailedError(
                'Expected agent to be called, but it was not.'
            );
        }
    }

    /**
     * Assert the agent was not called.
     */
    public function assertNotCalled(): void
    {
        if (! empty($this->calls)) {
            throw new AssertionFailedError(
                sprintf('Expected agent not to be called, but it was called %d time(s).', count($this->calls))
            );
        }
    }

    /**
     * Assert call count.
     */
    public function assertCalledTimes(int $count): void
    {
        $actual = count($this->calls);

        if ($actual !== $count) {
            throw new AssertionFailedError(
                sprintf('Expected agent to be called %d time(s), but it was called %d time(s).', $count, $actual)
            );
        }
    }

    /**
     * Assert a specific message was received.
     */
    public function assertReceivedMessage(string $message): void
    {
        foreach ($this->calls as $call) {
            if ($call['message'] === $message) {
                return;
            }
        }

        throw new AssertionFailedError(
            sprintf('Expected agent to receive message "%s", but it did not.', $message)
        );
    }

    /**
     * Assert message contains substring.
     */
    public function assertReceivedMessageContaining(string $substring): void
    {
        foreach ($this->calls as $call) {
            if (str_contains($call['message'], $substring)) {
                return;
            }
        }

        throw new AssertionFailedError(
            sprintf('Expected agent to receive message containing "%s", but it did not.', $substring)
        );
    }

    /**
     * Get all calls.
     *
     * @return array<array{message: string, context: array<string, mixed>}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * Get the last call.
     *
     * @return array{message: string, context: array<string, mixed>}|null
     */
    public function getLastCall(): ?array
    {
        return $this->calls[count($this->calls) - 1] ?? null;
    }

    /**
     * Reset the fake agent state.
     */
    public function reset(): static
    {
        $this->calls = [];
        $this->responseIndex = 0;

        return $this;
    }
}
