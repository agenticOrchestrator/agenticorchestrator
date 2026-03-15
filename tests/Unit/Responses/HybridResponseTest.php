<?php

declare(strict_types=1);

namespace Tests\Unit\Responses;

use AgenticOrchestrator\Agents\AgentResponse;
use AgenticOrchestrator\Rag\RagPipelineResult;
use AgenticOrchestrator\Responses\HybridResponse;
use AgenticOrchestrator\Responses\HybridStrategy;
use AgenticOrchestrator\Responses\ResponseSegment;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HybridResponseTest extends TestCase
{
    #[Test]
    public function it_creates_a_basic_hybrid_response(): void
    {
        $segments = [
            ResponseSegment::fromRag('RAG content', 0.9),
            ResponseSegment::fromLlm('LLM content'),
        ];

        $response = new HybridResponse(
            segments: $segments,
            query: 'Test query',
            strategy: HybridStrategy::RAG_AUGMENTED,
            usage: ['prompt_tokens' => 100, 'completion_tokens' => 50],
            latency: ['rag_ms' => 100, 'llm_ms' => 500, 'total_ms' => 600],
            metadata: ['key' => 'value'],
        );

        $this->assertEquals('Test query', $response->query);
        $this->assertEquals(HybridStrategy::RAG_AUGMENTED, $response->strategy);
        $this->assertEquals(2, $response->segmentCount());
    }

    #[Test]
    public function it_creates_from_agent_response(): void
    {
        $agentResponse = new AgentResponse(
            content: 'LLM generated content',
            toolCalls: [],
            usage: ['prompt_tokens' => 100, 'completion_tokens' => 50],
            metadata: ['model' => 'gpt-4o'],
            latency: 0.5,
            finishReason: 'stop',
        );

        $response = HybridResponse::fromAgentResponse($agentResponse, 'Test query');

        $this->assertEquals(HybridStrategy::LLM_ONLY, $response->strategy);
        $this->assertEquals(1, $response->segmentCount());
        $this->assertEquals('LLM generated content', $response->getContent());
        $this->assertTrue($response->hasLlmContent());
        $this->assertFalse($response->hasRagContext());
    }

    #[Test]
    public function it_creates_from_rag_result(): void
    {
        $ragResult = RagPipelineResult::forQuery(
            query: 'Test query',
            results: [
                ['content' => 'Result 1', 'score' => 0.95, 'source' => 'doc1.md'],
                ['content' => 'Result 2', 'score' => 0.85, 'source' => 'doc2.md'],
            ],
            durationMs: 150,
        );

        $response = HybridResponse::fromRagResult($ragResult, 'Test query');

        $this->assertEquals(HybridStrategy::RAG_ONLY, $response->strategy);
        $this->assertEquals(2, $response->segmentCount());
        $this->assertTrue($response->hasRagContext());
        $this->assertFalse($response->hasLlmContent());
    }

    #[Test]
    public function it_creates_combined_response(): void
    {
        $agentResponse = new AgentResponse(
            content: 'LLM answer',
            toolCalls: [],
            usage: ['prompt_tokens' => 100, 'completion_tokens' => 50],
            metadata: ['model' => 'gpt-4o'],
            latency: 0.5,
            finishReason: 'stop',
        );

        $ragResult = RagPipelineResult::forQuery(
            query: 'Test query',
            results: [
                ['content' => 'RAG context', 'score' => 0.9],
            ],
            durationMs: 100,
        );

        $response = HybridResponse::fromCombined($agentResponse, $ragResult, 'Test query');

        $this->assertEquals(HybridStrategy::RAG_AUGMENTED, $response->strategy);
        $this->assertEquals(2, $response->segmentCount());
        $this->assertTrue($response->isHybrid());
        $this->assertTrue($response->hasRagContext());
        $this->assertTrue($response->hasLlmContent());
    }

    #[Test]
    public function it_gets_segments_by_source(): void
    {
        $response = new HybridResponse(
            segments: [
                ResponseSegment::fromRag('RAG 1', 0.9),
                ResponseSegment::fromRag('RAG 2', 0.8),
                ResponseSegment::fromLlm('LLM'),
            ],
            query: 'Query',
            strategy: HybridStrategy::PARALLEL,
        );

        $ragSegments = $response->getRagSegments();
        $llmSegments = $response->getLlmSegments();

        $this->assertCount(2, $ragSegments);
        $this->assertCount(1, $llmSegments);
    }

    #[Test]
    public function it_gets_primary_segment(): void
    {
        $response = new HybridResponse(
            segments: [
                ResponseSegment::fromRag('RAG content', 0.95),
                ResponseSegment::fromLlm('LLM content'),
            ],
            query: 'Query',
            strategy: HybridStrategy::RAG_AUGMENTED,
        );

        $primary = $response->getPrimarySegment();

        // For RAG_AUGMENTED, LLM is preferred
        $this->assertEquals('LLM content', $primary->content);
    }

    #[Test]
    public function it_gets_primary_segment_for_rag_only(): void
    {
        $response = new HybridResponse(
            segments: [
                ResponseSegment::fromRag('Low confidence', 0.5),
                ResponseSegment::fromRag('High confidence', 0.95),
            ],
            query: 'Query',
            strategy: HybridStrategy::RAG_ONLY,
        );

        $primary = $response->getPrimarySegment();

        // For RAG_ONLY, highest confidence is preferred
        $this->assertEquals('High confidence', $primary->content);
    }

    #[Test]
    public function it_gets_combined_content(): void
    {
        $response = new HybridResponse(
            segments: [
                ResponseSegment::fromRag('First', 0.9)->withOrder(0),
                ResponseSegment::fromRag('Second', 0.8)->withOrder(1),
            ],
            query: 'Query',
            strategy: HybridStrategy::RAG_ONLY,
        );

        $combined = $response->getCombinedContent("\n");

        $this->assertEquals("First\nSecond", $combined);
    }

    #[Test]
    public function it_gets_attributed_content(): void
    {
        $response = new HybridResponse(
            segments: [
                ResponseSegment::fromRag('RAG', 0.9),
                ResponseSegment::fromLlm('LLM'),
            ],
            query: 'Query',
            strategy: HybridStrategy::PARALLEL,
        );

        $attributed = $response->getAttributedContent();

        $this->assertCount(2, $attributed);
        $this->assertEquals('RAG', $attributed[0]['content']);
        $this->assertEquals('rag', $attributed[0]['source']);
        $this->assertEquals(0.9, $attributed[0]['confidence']);
        $this->assertEquals('LLM', $attributed[1]['content']);
        $this->assertEquals('llm', $attributed[1]['source']);
        $this->assertNull($attributed[1]['confidence']);
    }

    #[Test]
    public function it_gets_high_confidence_segments(): void
    {
        $response = new HybridResponse(
            segments: [
                ResponseSegment::fromRag('High', 0.95),
                ResponseSegment::fromRag('Low', 0.3),
                ResponseSegment::fromRag('Medium', 0.75),
            ],
            query: 'Query',
            strategy: HybridStrategy::RAG_ONLY,
        );

        $highConfidence = $response->getHighConfidenceSegments(0.7);

        $this->assertCount(2, $highConfidence);
    }

    #[Test]
    public function it_calculates_average_rag_confidence(): void
    {
        $response = new HybridResponse(
            segments: [
                ResponseSegment::fromRag('A', 0.9),
                ResponseSegment::fromRag('B', 0.8),
                ResponseSegment::fromLlm('C'), // Should be ignored
            ],
            query: 'Query',
            strategy: HybridStrategy::PARALLEL,
        );

        $this->assertEqualsWithDelta(0.85, $response->getAverageRagConfidence(), 0.0001);
    }

    #[Test]
    public function it_gets_unique_sources(): void
    {
        $response = new HybridResponse(
            segments: [
                ResponseSegment::fromRag('A', 0.9, ['source' => 'doc1.md']),
                ResponseSegment::fromRag('B', 0.8, ['source' => 'doc2.md']),
                ResponseSegment::fromRag('C', 0.7, ['source' => 'doc1.md']), // Duplicate
            ],
            query: 'Query',
            strategy: HybridStrategy::RAG_ONLY,
        );

        $sources = $response->getSources();

        $this->assertCount(2, $sources);
        $this->assertContains('doc1.md', $sources);
        $this->assertContains('doc2.md', $sources);
    }

    #[Test]
    public function it_calculates_total_tokens(): void
    {
        $response = new HybridResponse(
            segments: [],
            query: 'Query',
            strategy: HybridStrategy::LLM_ONLY,
            usage: ['prompt_tokens' => 100, 'completion_tokens' => 50],
        );

        $this->assertEquals(150, $response->getTotalTokens());
    }

    #[Test]
    public function it_gets_latency_information(): void
    {
        $response = new HybridResponse(
            segments: [],
            query: 'Query',
            strategy: HybridStrategy::RAG_AUGMENTED,
            latency: ['rag_ms' => 100, 'llm_ms' => 500, 'total_ms' => 600],
        );

        $this->assertEquals(600, $response->getTotalLatencyMs());
        $this->assertEquals([
            'rag_ms' => 100,
            'llm_ms' => 500,
            'total_ms' => 600,
        ], $response->getLatencyBreakdown());
    }

    #[Test]
    public function it_maps_segments(): void
    {
        $response = new HybridResponse(
            segments: [
                ResponseSegment::fromRag('lowercase', 0.9),
            ],
            query: 'Query',
            strategy: HybridStrategy::RAG_ONLY,
        );

        $mapped = $response->mapSegments(
            fn (ResponseSegment $s) => new ResponseSegment(
                strtoupper($s->content),
                $s->source,
                $s->confidence,
            )
        );

        $this->assertEquals('LOWERCASE', $mapped->getContent());
        $this->assertEquals('lowercase', $response->getContent()); // Original unchanged
    }

    #[Test]
    public function it_filters_segments(): void
    {
        $response = new HybridResponse(
            segments: [
                ResponseSegment::fromRag('High', 0.9),
                ResponseSegment::fromRag('Low', 0.3),
            ],
            query: 'Query',
            strategy: HybridStrategy::RAG_ONLY,
        );

        $filtered = $response->filterSegments(
            fn (ResponseSegment $s) => $s->meetsThreshold(0.5)
        );

        $this->assertEquals(1, $filtered->segmentCount());
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $response = new HybridResponse(
            segments: [
                ResponseSegment::fromRag('RAG', 0.9, ['source' => 'test.md']),
                ResponseSegment::fromLlm('LLM'),
            ],
            query: 'Test query',
            strategy: HybridStrategy::RAG_AUGMENTED,
            usage: ['prompt_tokens' => 100],
            latency: ['total_ms' => 500],
            metadata: ['key' => 'value'],
        );

        $array = $response->toArray();

        $this->assertArrayHasKey('segments', $array);
        $this->assertArrayHasKey('query', $array);
        $this->assertArrayHasKey('strategy', $array);
        $this->assertArrayHasKey('summary', $array);
        $this->assertEquals('rag_augmented', $array['strategy']);
        $this->assertTrue($array['summary']['is_hybrid']);
    }

    #[Test]
    public function it_converts_to_api_response(): void
    {
        $response = new HybridResponse(
            segments: [
                ResponseSegment::fromRag('Context', 0.9, ['source' => 'doc.md']),
                ResponseSegment::fromLlm('Answer'),
            ],
            query: 'Query',
            strategy: HybridStrategy::RAG_AUGMENTED,
            latency: ['total_ms' => 500],
        );

        $api = $response->toApiResponse();

        $this->assertArrayHasKey('content', $api);
        $this->assertArrayHasKey('segments', $api);
        $this->assertArrayHasKey('strategy', $api);
        $this->assertArrayHasKey('sources', $api);
        $this->assertArrayHasKey('latency_ms', $api);
    }

    #[Test]
    public function it_is_json_serializable(): void
    {
        $response = new HybridResponse(
            segments: [ResponseSegment::fromLlm('Test')],
            query: 'Query',
            strategy: HybridStrategy::LLM_ONLY,
        );

        $json = json_encode($response);
        $decoded = json_decode($json, true);

        $this->assertEquals('Query', $decoded['query']);
        $this->assertEquals('llm_only', $decoded['strategy']);
    }

    #[Test]
    public function it_converts_to_string(): void
    {
        $response = new HybridResponse(
            segments: [ResponseSegment::fromLlm('Primary content')],
            query: 'Query',
            strategy: HybridStrategy::LLM_ONLY,
        );

        $this->assertEquals('Primary content', (string) $response);
    }
}
