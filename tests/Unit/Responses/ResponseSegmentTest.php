<?php

declare(strict_types=1);

namespace Tests\Unit\Responses;

use AgenticOrchestrator\Responses\ResponseSegment;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResponseSegmentTest extends TestCase
{
    #[Test]
    public function it_creates_a_basic_segment(): void
    {
        $segment = new ResponseSegment(
            content: 'Test content',
            source: 'rag',
            confidence: 0.85,
            metadata: ['key' => 'value'],
            order: 1,
        );

        $this->assertEquals('Test content', $segment->content);
        $this->assertEquals('rag', $segment->source);
        $this->assertEquals(0.85, $segment->confidence);
        $this->assertEquals(['key' => 'value'], $segment->metadata);
        $this->assertEquals(1, $segment->order);
    }

    #[Test]
    public function it_creates_rag_segment_from_factory(): void
    {
        $segment = ResponseSegment::fromRag(
            content: 'Retrieved content',
            score: 0.92,
            metadata: ['document' => 'test.md'],
        );

        $this->assertEquals('Retrieved content', $segment->content);
        $this->assertEquals(ResponseSegment::SOURCE_RAG, $segment->source);
        $this->assertEquals(0.92, $segment->confidence);
        $this->assertTrue($segment->isFromRag());
        $this->assertFalse($segment->isFromLlm());
        $this->assertEquals('vector', $segment->getMeta('retrieval_type'));
    }

    #[Test]
    public function it_creates_llm_segment_from_factory(): void
    {
        $segment = ResponseSegment::fromLlm(
            content: 'Generated content',
            metadata: ['model' => 'gpt-4o'],
        );

        $this->assertEquals('Generated content', $segment->content);
        $this->assertEquals(ResponseSegment::SOURCE_LLM, $segment->source);
        $this->assertNull($segment->confidence);
        $this->assertTrue($segment->isFromLlm());
        $this->assertFalse($segment->isFromRag());
        $this->assertEquals('gpt-4o', $segment->getMeta('model'));
    }

    #[Test]
    public function it_creates_cached_segment_from_factory(): void
    {
        $segment = ResponseSegment::fromCache(
            content: 'Cached content',
            metadata: ['cached_at' => '2024-01-01'],
        );

        $this->assertEquals('Cached content', $segment->content);
        $this->assertEquals(ResponseSegment::SOURCE_CACHED, $segment->source);
        $this->assertEquals(1.0, $segment->confidence);
        $this->assertTrue($segment->isFromCache());
    }

    #[Test]
    public function it_creates_tool_segment_from_factory(): void
    {
        $segment = ResponseSegment::fromTool(
            content: 'Tool output',
            toolName: 'calculator',
            metadata: ['execution_time' => 50],
        );

        $this->assertEquals('Tool output', $segment->content);
        $this->assertEquals(ResponseSegment::SOURCE_TOOL, $segment->source);
        $this->assertTrue($segment->isFromTool());
        $this->assertEquals('calculator', $segment->getMeta('tool_name'));
    }

    #[Test]
    public function it_checks_confidence_threshold(): void
    {
        $highConfidence = new ResponseSegment('content', 'rag', confidence: 0.9);
        $lowConfidence = new ResponseSegment('content', 'rag', confidence: 0.3);
        $noConfidence = new ResponseSegment('content', 'llm');

        $this->assertTrue($highConfidence->meetsThreshold(0.7));
        $this->assertFalse($lowConfidence->meetsThreshold(0.7));
        $this->assertFalse($noConfidence->meetsThreshold(0.7));
    }

    #[Test]
    public function it_detects_confidence_presence(): void
    {
        $withConfidence = new ResponseSegment('content', 'rag', confidence: 0.5);
        $withoutConfidence = new ResponseSegment('content', 'llm');

        $this->assertTrue($withConfidence->hasConfidence());
        $this->assertFalse($withoutConfidence->hasConfidence());
    }

    #[Test]
    public function it_gets_metadata_with_default(): void
    {
        $segment = new ResponseSegment(
            'content',
            'rag',
            metadata: ['existing' => 'value'],
        );

        $this->assertEquals('value', $segment->getMeta('existing'));
        $this->assertNull($segment->getMeta('missing'));
        $this->assertEquals('default', $segment->getMeta('missing', 'default'));
    }

    #[Test]
    public function it_calculates_content_length(): void
    {
        $segment = new ResponseSegment('Hello World', 'llm');

        $this->assertEquals(11, $segment->length());
    }

    #[Test]
    public function it_truncates_content(): void
    {
        $segment = new ResponseSegment('This is a long piece of content', 'rag', confidence: 0.8);

        $truncated = $segment->truncate(15);

        $this->assertEquals('This is a lo...', $truncated->content);
        $this->assertTrue($truncated->getMeta('truncated'));
        $this->assertEquals(31, $truncated->getMeta('original_length'));
        // Original unchanged
        $this->assertEquals('This is a long piece of content', $segment->content);
    }

    #[Test]
    public function it_does_not_truncate_short_content(): void
    {
        $segment = new ResponseSegment('Short', 'rag');

        $truncated = $segment->truncate(100);

        $this->assertSame($segment, $truncated);
    }

    #[Test]
    public function it_creates_copy_with_updated_order(): void
    {
        $segment = new ResponseSegment('content', 'rag', confidence: 0.8, order: 1);

        $reordered = $segment->withOrder(5);

        $this->assertEquals(5, $reordered->order);
        $this->assertEquals(1, $segment->order);
        $this->assertEquals($segment->content, $reordered->content);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $segment = new ResponseSegment(
            content: 'Test',
            source: 'rag',
            confidence: 0.9,
            metadata: ['key' => 'value'],
            order: 2,
        );

        $array = $segment->toArray();

        $this->assertEquals([
            'content' => 'Test',
            'source' => 'rag',
            'confidence' => 0.9,
            'metadata' => ['key' => 'value'],
            'order' => 2,
        ], $array);
    }

    #[Test]
    public function it_is_json_serializable(): void
    {
        $segment = new ResponseSegment('Test', 'rag', confidence: 0.9);

        $json = json_encode($segment);
        $decoded = json_decode($json, true);

        $this->assertEquals('Test', $decoded['content']);
        $this->assertEquals('rag', $decoded['source']);
        $this->assertEquals(0.9, $decoded['confidence']);
    }

    #[Test]
    public function it_converts_to_string(): void
    {
        $segment = new ResponseSegment('Test content', 'llm');

        $this->assertEquals('Test content', (string) $segment);
    }
}
