<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Agents\Concerns;

use AgenticOrchestrator\Streaming\StreamChunk;
use AgenticOrchestrator\Streaming\StreamResponse;
use Closure;
use Generator;

/**
 * HasStreaming - Adds streaming response capability to agents.
 *
 * Enables real-time token-by-token streaming of LLM responses
 * with callbacks for content, tool calls, and completion.
 */
trait HasStreaming
{
    /**
     * Whether streaming is enabled for this agent.
     */
    protected bool $streamingEnabled = true;

    /**
     * Stream a response from the agent.
     *
     * @param  string  $message  The user message
     * @param  array<string, mixed>  $context  Additional context
     */
    public function stream(string $message, array $context = []): StreamResponse
    {
        // Create generator that yields chunks
        $generator = $this->createStreamGenerator($message, $context);

        return new StreamResponse($generator);
    }

    /**
     * Stream with callbacks.
     *
     * @param  string  $message  The user message
     * @param  array<string, mixed>  $context  Additional context
     * @param  Closure|null  $onContent  Content callback
     * @param  Closure|null  $onToolCall  Tool call callback
     * @param  Closure|null  $onDone  Completion callback
     */
    public function streamWith(
        string $message,
        array $context = [],
        ?Closure $onContent = null,
        ?Closure $onToolCall = null,
        ?Closure $onDone = null,
    ): StreamResponse {
        $stream = $this->stream($message, $context);

        if ($onContent !== null) {
            $stream->onContent($onContent);
        }

        if ($onToolCall !== null) {
            $stream->onToolCall($onToolCall);
        }

        if ($onDone !== null) {
            $stream->onDone($onDone);
        }

        return $stream;
    }

    /**
     * Enable streaming.
     */
    public function enableStreaming(): static
    {
        $this->streamingEnabled = true;

        return $this;
    }

    /**
     * Disable streaming.
     */
    public function disableStreaming(): static
    {
        $this->streamingEnabled = false;

        return $this;
    }

    /**
     * Check if streaming is enabled.
     */
    public function isStreamingEnabled(): bool
    {
        return $this->streamingEnabled;
    }

    /**
     * Create the stream generator.
     *
     * This should be overridden by the agent implementation to integrate
     * with the actual LLM provider's streaming API.
     *
     * @param  string  $message  The user message
     * @param  array<string, mixed>  $context  Additional context
     * @return Generator<int, StreamChunk>
     */
    protected function createStreamGenerator(string $message, array $context = []): Generator
    {
        // Default implementation: fall back to non-streaming and emit single chunk
        // Real implementations should override this to use the LLM's streaming API
        $response = $this->respond($message, $context);

        $content = $response->content ?? '';

        // Emit content in chunks (simulated streaming)
        $words = explode(' ', $content);
        $index = 0;

        foreach ($words as $i => $word) {
            $separator = $i === 0 ? '' : ' ';
            yield StreamChunk::content($separator.$word, $index++);
        }

        // Emit done chunk
        yield StreamChunk::done([
            'usage' => $response->usage ?? [],
            'model' => $response->model ?? null,
        ], $index);
    }

    /**
     * Create a streaming response from a Prism stream.
     *
     * Helper method for integrating with Prism PHP streaming.
     *
     * @param  iterable  $prismStream  The Prism stream
     * @return Generator<int, StreamChunk>
     */
    protected function wrapPrismStream(iterable $prismStream): Generator
    {
        $index = 0;

        foreach ($prismStream as $chunk) {
            // Handle different chunk types from Prism
            if (isset($chunk['content']) || isset($chunk['delta']['content'])) {
                $content = $chunk['content'] ?? $chunk['delta']['content'] ?? '';
                yield StreamChunk::content($content, $index++);
            } elseif (isset($chunk['tool_calls']) || isset($chunk['delta']['tool_calls'])) {
                $toolCalls = $chunk['tool_calls'] ?? $chunk['delta']['tool_calls'] ?? [];
                foreach ($toolCalls as $toolCall) {
                    yield StreamChunk::toolCall($toolCall, $index++);
                }
            } elseif (isset($chunk['finish_reason'])) {
                yield StreamChunk::done([
                    'finish_reason' => $chunk['finish_reason'],
                    'usage' => $chunk['usage'] ?? [],
                ], $index++);
            }
        }
    }
}
