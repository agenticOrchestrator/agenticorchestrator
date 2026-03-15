<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Streaming;

use Closure;
use Generator;
use IteratorAggregate;
use Traversable;

/**
 * Stream Response - Wraps a streaming LLM response.
 *
 * Provides iteration over stream chunks with event callbacks
 * for content, tool calls, and completion.
 *
 * @implements IteratorAggregate<int, StreamChunk>
 */
class StreamResponse implements IteratorAggregate
{
    /**
     * The underlying chunk generator.
     *
     * @var Generator<int, StreamChunk>|iterable<StreamChunk>
     */
    protected Generator|iterable $chunks;

    /**
     * Content callback.
     */
    protected ?Closure $onContent = null;

    /**
     * Tool call callback.
     */
    protected ?Closure $onToolCall = null;

    /**
     * Tool result callback.
     */
    protected ?Closure $onToolResult = null;

    /**
     * Error callback.
     */
    protected ?Closure $onError = null;

    /**
     * Completion callback.
     */
    protected ?Closure $onDone = null;

    /**
     * Accumulated content.
     */
    protected string $content = '';

    /**
     * Collected tool calls.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $toolCalls = [];

    /**
     * Whether the stream has been consumed.
     */
    protected bool $consumed = false;

    /**
     * Final metadata from the stream.
     *
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    /**
     * Create a new stream response.
     *
     * @param  Generator<int, StreamChunk>|iterable<StreamChunk>  $chunks
     */
    public function __construct(Generator|iterable $chunks)
    {
        $this->chunks = $chunks;
    }

    /**
     * Create from an iterable of chunks.
     *
     * @param  iterable<StreamChunk>  $chunks
     */
    public static function from(iterable $chunks): static
    {
        return new static($chunks);
    }

    /**
     * Register content callback.
     *
     * @param  Closure(string, StreamChunk): void  $callback
     */
    public function onContent(Closure $callback): static
    {
        $this->onContent = $callback;

        return $this;
    }

    /**
     * Register tool call callback.
     *
     * @param  Closure(array, StreamChunk): void  $callback
     */
    public function onToolCall(Closure $callback): static
    {
        $this->onToolCall = $callback;

        return $this;
    }

    /**
     * Register tool result callback.
     *
     * @param  Closure(string, mixed, StreamChunk): void  $callback
     */
    public function onToolResult(Closure $callback): static
    {
        $this->onToolResult = $callback;

        return $this;
    }

    /**
     * Register error callback.
     *
     * @param  Closure(string, StreamChunk): void  $callback
     */
    public function onError(Closure $callback): static
    {
        $this->onError = $callback;

        return $this;
    }

    /**
     * Register completion callback.
     *
     * @param  Closure(string, array): void  $callback
     */
    public function onDone(Closure $callback): static
    {
        $this->onDone = $callback;

        return $this;
    }

    /**
     * Get the iterator for the stream.
     *
     * @return Traversable<int, StreamChunk>
     */
    public function getIterator(): Traversable
    {
        if ($this->consumed) {
            throw new \RuntimeException('Stream has already been consumed.');
        }

        $this->consumed = true;

        foreach ($this->chunks as $chunk) {
            $this->processChunk($chunk);

            yield $chunk;
        }
    }

    /**
     * Process a chunk and fire callbacks.
     */
    protected function processChunk(StreamChunk $chunk): void
    {
        match ($chunk->type) {
            StreamChunk::TYPE_CONTENT => $this->handleContent($chunk),
            StreamChunk::TYPE_TOOL_CALL => $this->handleToolCall($chunk),
            StreamChunk::TYPE_TOOL_RESULT => $this->handleToolResult($chunk),
            StreamChunk::TYPE_ERROR => $this->handleError($chunk),
            StreamChunk::TYPE_DONE => $this->handleDone($chunk),
            default => null,
        };
    }

    /**
     * Handle content chunk.
     */
    protected function handleContent(StreamChunk $chunk): void
    {
        $this->content .= $chunk->content;

        if ($this->onContent !== null) {
            ($this->onContent)($chunk->content, $chunk);
        }
    }

    /**
     * Handle tool call chunk.
     */
    protected function handleToolCall(StreamChunk $chunk): void
    {
        $toolCall = $chunk->getMeta('tool_call');

        if ($toolCall !== null) {
            $this->toolCalls[] = $toolCall;

            if ($this->onToolCall !== null) {
                ($this->onToolCall)($toolCall, $chunk);
            }
        }
    }

    /**
     * Handle tool result chunk.
     */
    protected function handleToolResult(StreamChunk $chunk): void
    {
        if ($this->onToolResult !== null) {
            $toolName = $chunk->getMeta('tool_name');
            $result = $chunk->getMeta('result');
            ($this->onToolResult)($toolName, $result, $chunk);
        }
    }

    /**
     * Handle error chunk.
     */
    protected function handleError(StreamChunk $chunk): void
    {
        if ($this->onError !== null) {
            ($this->onError)($chunk->content, $chunk);
        }
    }

    /**
     * Handle done chunk.
     */
    protected function handleDone(StreamChunk $chunk): void
    {
        $this->metadata = array_merge($this->metadata, $chunk->metadata);

        if ($this->onDone !== null) {
            ($this->onDone)($this->content, $this->metadata);
        }
    }

    /**
     * Consume the stream and return accumulated content.
     */
    public function text(): string
    {
        if (! $this->consumed) {
            foreach ($this as $chunk) {
                // Just iterate to consume
            }
        }

        return $this->content;
    }

    /**
     * Consume the stream and return all chunks as array.
     *
     * @return array<int, StreamChunk>
     */
    public function collect(): array
    {
        $chunks = [];

        foreach ($this as $chunk) {
            $chunks[] = $chunk;
        }

        return $chunks;
    }

    /**
     * Get the accumulated content so far.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get collected tool calls.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    /**
     * Get final metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Check if stream has been consumed.
     */
    public function isConsumed(): bool
    {
        return $this->consumed;
    }

    /**
     * Convert to SSE stream (for HTTP streaming).
     *
     * @return Generator<string>
     */
    public function toSSE(): Generator
    {
        foreach ($this as $chunk) {
            yield $chunk->toSSE();
        }
    }
}
