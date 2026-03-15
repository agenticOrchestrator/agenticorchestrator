<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Streaming;

use AgenticOrchestrator\Streaming\StreamChunk;
use AgenticOrchestrator\Streaming\StreamResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StreamResponse::class)]
class StreamResponseTest extends TestCase
{
    #[Test]
    public function it_iterates_over_chunks(): void
    {
        $generator = $this->createGenerator([
            StreamChunk::content('Hello ', 0),
            StreamChunk::content('world', 1),
            StreamChunk::done([], 2),
        ]);

        $stream = new StreamResponse($generator);
        $chunks = [];

        foreach ($stream as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertCount(3, $chunks);
        $this->assertTrue($chunks[0]->isContent());
        $this->assertTrue($chunks[2]->isDone());
    }

    #[Test]
    public function it_accumulates_content(): void
    {
        $generator = $this->createGenerator([
            StreamChunk::content('Hello ', 0),
            StreamChunk::content('world', 1),
            StreamChunk::done([], 2),
        ]);

        $stream = new StreamResponse($generator);
        $text = $stream->text();

        $this->assertSame('Hello world', $text);
    }

    #[Test]
    public function it_fires_content_callback(): void
    {
        $received = [];
        $generator = $this->createGenerator([
            StreamChunk::content('Hello', 0),
            StreamChunk::content(' world', 1),
            StreamChunk::done([], 2),
        ]);

        $stream = new StreamResponse($generator);
        $stream->onContent(function (string $content) use (&$received) {
            $received[] = $content;
        });

        $stream->text(); // Consume

        $this->assertSame(['Hello', ' world'], $received);
    }

    #[Test]
    public function it_fires_done_callback(): void
    {
        $doneContent = null;
        $doneMeta = null;

        $generator = $this->createGenerator([
            StreamChunk::content('Result', 0),
            StreamChunk::done(['model' => 'gpt-4'], 1),
        ]);

        $stream = new StreamResponse($generator);
        $stream->onDone(function (string $content, array $meta) use (&$doneContent, &$doneMeta) {
            $doneContent = $content;
            $doneMeta = $meta;
        });

        $stream->text();

        $this->assertSame('Result', $doneContent);
        $this->assertSame('gpt-4', $doneMeta['model']);
    }

    #[Test]
    public function it_fires_tool_call_callback(): void
    {
        $receivedToolCalls = [];
        $toolCallData = ['name' => 'search', 'arguments' => ['query' => 'test']];

        $generator = $this->createGenerator([
            StreamChunk::toolCall($toolCallData, 0),
            StreamChunk::done([], 1),
        ]);

        $stream = new StreamResponse($generator);
        $stream->onToolCall(function (array $toolCall) use (&$receivedToolCalls) {
            $receivedToolCalls[] = $toolCall;
        });

        $stream->text();

        $this->assertCount(1, $receivedToolCalls);
        $this->assertSame('search', $receivedToolCalls[0]['name']);
    }

    #[Test]
    public function it_fires_error_callback(): void
    {
        $receivedError = null;

        $generator = $this->createGenerator([
            StreamChunk::error('Something went wrong', 0),
            StreamChunk::done([], 1),
        ]);

        $stream = new StreamResponse($generator);
        $stream->onError(function (string $error) use (&$receivedError) {
            $receivedError = $error;
        });

        $stream->text();

        $this->assertSame('Something went wrong', $receivedError);
    }

    #[Test]
    public function it_collects_chunks_as_array(): void
    {
        $generator = $this->createGenerator([
            StreamChunk::content('a', 0),
            StreamChunk::content('b', 1),
            StreamChunk::done([], 2),
        ]);

        $stream = new StreamResponse($generator);
        $chunks = $stream->collect();

        $this->assertCount(3, $chunks);
        $this->assertContainsOnlyInstancesOf(StreamChunk::class, $chunks);
    }

    #[Test]
    public function it_throws_when_consumed_twice(): void
    {
        $generator = $this->createGenerator([
            StreamChunk::content('test', 0),
            StreamChunk::done([], 1),
        ]);

        $stream = new StreamResponse($generator);
        $stream->text(); // First consume

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stream has already been consumed');

        foreach ($stream as $chunk) {
            // Should throw
        }
    }

    #[Test]
    public function it_creates_from_iterable(): void
    {
        $chunks = [
            StreamChunk::content('test', 0),
            StreamChunk::done([], 1),
        ];

        $stream = StreamResponse::from($chunks);
        $text = $stream->text();

        $this->assertSame('test', $text);
    }

    #[Test]
    public function it_generates_sse_output(): void
    {
        $generator = $this->createGenerator([
            StreamChunk::content('hi', 0),
            StreamChunk::done([], 1),
        ]);

        $stream = new StreamResponse($generator);
        $sseChunks = [];

        foreach ($stream->toSSE() as $sse) {
            $sseChunks[] = $sse;
        }

        $this->assertCount(2, $sseChunks);
        $this->assertStringStartsWith('data: ', $sseChunks[0]);
        $this->assertStringEndsWith("\n\n", $sseChunks[0]);
    }

    #[Test]
    public function it_tracks_consumed_state(): void
    {
        $generator = $this->createGenerator([
            StreamChunk::done([], 0),
        ]);

        $stream = new StreamResponse($generator);
        $this->assertFalse($stream->isConsumed());

        $stream->text();
        $this->assertTrue($stream->isConsumed());
    }

    #[Test]
    public function it_gets_collected_tool_calls(): void
    {
        $generator = $this->createGenerator([
            StreamChunk::toolCall(['name' => 'tool1'], 0),
            StreamChunk::toolCall(['name' => 'tool2'], 1),
            StreamChunk::done([], 2),
        ]);

        $stream = new StreamResponse($generator);
        $stream->text();

        $toolCalls = $stream->getToolCalls();
        $this->assertCount(2, $toolCalls);
    }

    /**
     * @param  array<StreamChunk>  $chunks
     * @return \Generator<int, StreamChunk>
     */
    private function createGenerator(array $chunks): \Generator
    {
        foreach ($chunks as $chunk) {
            yield $chunk;
        }
    }
}
