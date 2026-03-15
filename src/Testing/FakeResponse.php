<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Testing;

use AgenticOrchestrator\Agents\AgentResponse;

/**
 * Fake Response - Builder for creating test agent responses.
 *
 * @example
 * ```php
 * $response = FakeResponse::make()
 *     ->content('Hello, world!')
 *     ->tokens(50, 20)
 *     ->build();
 *
 * // Or use the shorthand
 * $response = FakeResponse::text('Hello!');
 * ```
 */
class FakeResponse
{
    protected string $content = '';

    protected int $promptTokens = 10;

    protected int $completionTokens = 20;

    protected ?string $finishReason = 'stop';

    /** @var array<int, array{id: string, name: string, arguments: array<string, mixed>, result: mixed}> */
    protected array $toolCalls = [];

    /** @var array<string, mixed> */
    protected array $metadata = [];

    protected ?float $latency = null;

    /**
     * Create a new fake response builder.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Set the response content.
     */
    public function content(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Set the token usage.
     */
    public function tokens(int $prompt, int $completion): static
    {
        $this->promptTokens = $prompt;
        $this->completionTokens = $completion;

        return $this;
    }

    /**
     * Set the finish reason.
     */
    public function finishReason(?string $reason): static
    {
        $this->finishReason = $reason;

        return $this;
    }

    /**
     * Set the latency.
     */
    public function latency(float $latency): static
    {
        $this->latency = $latency;

        return $this;
    }

    /**
     * Add a tool call to the response.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function withToolCall(string $id, string $name, array $arguments = [], mixed $result = null): static
    {
        $this->toolCalls[] = [
            'id' => $id,
            'name' => $name,
            'arguments' => $arguments,
            'result' => $result,
        ];

        return $this;
    }

    /**
     * Set metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Build the fake agent response.
     */
    public function build(): AgentResponse
    {
        return new AgentResponse(
            content: $this->content,
            toolCalls: $this->toolCalls,
            usage: [
                'prompt_tokens' => $this->promptTokens,
                'completion_tokens' => $this->completionTokens,
                'total_tokens' => $this->promptTokens + $this->completionTokens,
            ],
            metadata: $this->metadata,
            latency: $this->latency,
            finishReason: $this->finishReason,
        );
    }

    /**
     * Create a simple text response.
     */
    public static function text(string $content): AgentResponse
    {
        return static::make()->content($content)->build();
    }

    /**
     * Create a response with tool calls.
     *
     * @param  array<array{id: string, name: string, arguments?: array<string, mixed>, result?: mixed}>  $toolCalls
     */
    public static function withTools(string $content, array $toolCalls): AgentResponse
    {
        $builder = static::make()
            ->content($content)
            ->finishReason('tool_calls');

        foreach ($toolCalls as $call) {
            $builder->withToolCall(
                $call['id'],
                $call['name'],
                $call['arguments'] ?? [],
                $call['result'] ?? null,
            );
        }

        return $builder->build();
    }

    /**
     * Create an error response.
     */
    public static function error(string $message = 'An error occurred'): AgentResponse
    {
        return static::make()
            ->content($message)
            ->finishReason('error')
            ->build();
    }

    /**
     * Create a truncated response.
     */
    public static function truncated(string $content): AgentResponse
    {
        return static::make()
            ->content($content)
            ->finishReason('length')
            ->build();
    }
}
