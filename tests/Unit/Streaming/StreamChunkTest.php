<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Streaming;

use AgenticOrchestrator\Streaming\StreamChunk;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StreamChunk::class)]
class StreamChunkTest extends TestCase
{
    #[Test]
    public function it_creates_content_chunk(): void
    {
        $chunk = StreamChunk::content('Hello world', 5);

        $this->assertTrue($chunk->isContent());
        $this->assertFalse($chunk->isToolCall());
        $this->assertFalse($chunk->isError());
        $this->assertFalse($chunk->isDone());
        $this->assertSame('Hello world', $chunk->content);
        $this->assertSame(5, $chunk->index);
    }

    #[Test]
    public function it_creates_tool_call_chunk(): void
    {
        $toolCall = ['name' => 'get_weather', 'arguments' => ['location' => 'Berlin']];
        $chunk = StreamChunk::toolCall($toolCall, 3);

        $this->assertTrue($chunk->isToolCall());
        $this->assertFalse($chunk->isContent());
        $this->assertSame($toolCall, $chunk->getMeta('tool_call'));
    }

    #[Test]
    public function it_creates_tool_result_chunk(): void
    {
        $chunk = StreamChunk::toolResult('get_weather', ['temp' => 22], 4);

        $this->assertSame(StreamChunk::TYPE_TOOL_RESULT, $chunk->type);
        $this->assertSame('get_weather', $chunk->getMeta('tool_name'));
        $this->assertSame(['temp' => 22], $chunk->getMeta('result'));
    }

    #[Test]
    public function it_creates_error_chunk(): void
    {
        $chunk = StreamChunk::error('Something went wrong');

        $this->assertTrue($chunk->isError());
        $this->assertSame('Something went wrong', $chunk->content);
    }

    #[Test]
    public function it_creates_done_chunk(): void
    {
        $chunk = StreamChunk::done(['tokens' => 100], 10);

        $this->assertTrue($chunk->isDone());
        $this->assertTrue($chunk->isLast);
        $this->assertSame(100, $chunk->getMeta('tokens'));
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $chunk = StreamChunk::content('Hello', 1);
        $array = $chunk->toArray();

        $this->assertSame('content', $array['type']);
        $this->assertSame('Hello', $array['content']);
        $this->assertSame(1, $array['index']);
        $this->assertFalse($array['is_last']);
    }

    #[Test]
    public function it_converts_to_sse(): void
    {
        $chunk = StreamChunk::content('Hello');
        $sse = $chunk->toSSE();

        $this->assertStringStartsWith('data: ', $sse);
        $this->assertStringEndsWith("\n\n", $sse);
        $this->assertJson(substr($sse, 6, -2));
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $chunk = StreamChunk::content('Hello');
        $json = json_encode($chunk);

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('content', $decoded['type']);
        $this->assertSame('Hello', $decoded['content']);
    }

    #[Test]
    public function it_returns_default_for_missing_metadata(): void
    {
        $chunk = StreamChunk::content('Hello');

        $this->assertNull($chunk->getMeta('missing'));
        $this->assertSame('default', $chunk->getMeta('missing', 'default'));
    }
}
