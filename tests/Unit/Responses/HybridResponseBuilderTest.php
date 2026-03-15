<?php

declare(strict_types=1);

namespace Tests\Unit\Responses;

use AgenticOrchestrator\Agents\AgentResponse;
use AgenticOrchestrator\Rag\RagPipelineResult;
use AgenticOrchestrator\Responses\HybridResponse;
use AgenticOrchestrator\Responses\HybridResponseBuilder;
use AgenticOrchestrator\Responses\HybridStrategy;
use AgenticOrchestrator\Responses\ResponseSegment;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HybridResponseBuilderTest extends TestCase
{
    #[Test]
    public function it_creates_builder_from_static_method(): void
    {
        $builder = HybridResponse::builder('Test query');

        $this->assertInstanceOf(HybridResponseBuilder::class, $builder);
    }

    #[Test]
    public function it_builds_empty_response(): void
    {
        $response = (new HybridResponseBuilder('Test query'))->build();

        $this->assertEquals('Test query', $response->query);
        $this->assertEquals(0, $response->segmentCount());
    }

    #[Test]
    public function it_adds_agent_response(): void
    {
        $agentResponse = new AgentResponse(
            content: 'LLM content',
            toolCalls: [],
            usage: ['prompt_tokens' => 100, 'completion_tokens' => 50],
            metadata: ['model' => 'gpt-4o'],
            latency: 0.5,
            finishReason: 'stop',
        );

        $response = (new HybridResponseBuilder('Query'))
            ->withAgentResponse($agentResponse)
            ->build();

        $this->assertEquals(1, $response->segmentCount());
        $this->assertTrue($response->hasLlmContent());
        $this->assertEquals(150, $response->getTotalTokens());
        $this->assertEquals(500, $response->getLatencyBreakdown()['llm_ms']);
    }

    #[Test]
    public function it_adds_rag_result(): void
    {
        $ragResult = RagPipelineResult::forQuery(
            query: 'Query',
            results: [
                ['content' => 'Result 1', 'score' => 0.9],
                ['content' => 'Result 2', 'score' => 0.8],
            ],
            durationMs: 100,
        );

        $response = (new HybridResponseBuilder('Query'))
            ->withRagResult($ragResult)
            ->build();

        $this->assertEquals(2, $response->segmentCount());
        $this->assertTrue($response->hasRagContext());
        $this->assertEquals(100, $response->getLatencyBreakdown()['rag_ms']);
    }

    #[Test]
    public function it_adds_custom_segment(): void
    {
        $segment = ResponseSegment::fromRag('Custom content', 0.95);

        $response = (new HybridResponseBuilder('Query'))
            ->withSegment($segment)
            ->build();

        $this->assertEquals(1, $response->segmentCount());
        $this->assertEquals('Custom content', $response->getContent());
    }

    #[Test]
    public function it_adds_manual_rag_segment(): void
    {
        $response = (new HybridResponseBuilder('Query'))
            ->withRagSegment('RAG content', 0.9, ['source' => 'test.md'])
            ->build();

        $this->assertEquals(1, $response->segmentCount());
        $segment = $response->getRagSegments()->first();
        $this->assertEquals('RAG content', $segment->content);
        $this->assertEquals(0.9, $segment->confidence);
    }

    #[Test]
    public function it_adds_manual_llm_segment(): void
    {
        $response = (new HybridResponseBuilder('Query'))
            ->withLlmSegment('LLM content', ['model' => 'gpt-4'])
            ->build();

        $this->assertEquals(1, $response->segmentCount());
        $segment = $response->getLlmSegments()->first();
        $this->assertEquals('LLM content', $segment->content);
        $this->assertEquals('gpt-4', $segment->getMeta('model'));
    }

    #[Test]
    public function it_adds_cached_segment(): void
    {
        $response = (new HybridResponseBuilder('Query'))
            ->withCachedSegment('Cached content', ['ttl' => 3600])
            ->build();

        $this->assertEquals(1, $response->segmentCount());
        $segment = $response->getSegments()->first();
        $this->assertTrue($segment->isFromCache());
    }

    #[Test]
    public function it_adds_tool_segment(): void
    {
        $response = (new HybridResponseBuilder('Query'))
            ->withToolSegment('Tool output', 'calculator', ['result' => 42])
            ->build();

        $this->assertEquals(1, $response->segmentCount());
        $segment = $response->getSegments()->first();
        $this->assertTrue($segment->isFromTool());
        $this->assertEquals('calculator', $segment->getMeta('tool_name'));
    }

    #[Test]
    public function it_sets_strategy(): void
    {
        $response = (new HybridResponseBuilder('Query'))
            ->withStrategy(HybridStrategy::PARALLEL)
            ->build();

        $this->assertEquals(HybridStrategy::PARALLEL, $response->strategy);
    }

    #[Test]
    public function it_sets_usage(): void
    {
        $response = (new HybridResponseBuilder('Query'))
            ->withUsage(['prompt_tokens' => 100])
            ->withUsage(['completion_tokens' => 50])
            ->build();

        $this->assertEquals(150, $response->getTotalTokens());
    }

    #[Test]
    public function it_sets_latency(): void
    {
        $response = (new HybridResponseBuilder('Query'))
            ->withLatency(['rag_ms' => 100])
            ->withLatency(['llm_ms' => 500])
            ->build();

        $latency = $response->getLatencyBreakdown();
        $this->assertEquals(100, $latency['rag_ms']);
        $this->assertEquals(500, $latency['llm_ms']);
        $this->assertEquals(600, $latency['total_ms']); // Auto-calculated
    }

    #[Test]
    public function it_sets_metadata(): void
    {
        $response = (new HybridResponseBuilder('Query'))
            ->withMetadata(['key1' => 'value1'])
            ->withMeta('key2', 'value2')
            ->build();

        $this->assertEquals('value1', $response->getMeta('key1'));
        $this->assertEquals('value2', $response->getMeta('key2'));
    }

    #[Test]
    public function it_auto_detects_strategy(): void
    {
        $ragOnly = (new HybridResponseBuilder('Query'))
            ->withRagSegment('RAG', 0.9)
            ->autoDetectStrategy()
            ->build();

        $llmOnly = (new HybridResponseBuilder('Query'))
            ->withLlmSegment('LLM')
            ->autoDetectStrategy()
            ->build();

        $hybrid = (new HybridResponseBuilder('Query'))
            ->withRagSegment('RAG', 0.9)
            ->withLlmSegment('LLM')
            ->autoDetectStrategy()
            ->build();

        $this->assertEquals(HybridStrategy::RAG_ONLY, $ragOnly->strategy);
        $this->assertEquals(HybridStrategy::LLM_ONLY, $llmOnly->strategy);
        $this->assertEquals(HybridStrategy::RAG_AUGMENTED, $hybrid->strategy);
    }

    #[Test]
    public function it_preserves_segment_order(): void
    {
        $response = (new HybridResponseBuilder('Query'))
            ->withRagSegment('First', 0.9)
            ->withLlmSegment('Second')
            ->withRagSegment('Third', 0.8)
            ->build();

        $segments = $response->getSegments();
        $this->assertEquals(0, $segments->get(0)->order);
        $this->assertEquals(1, $segments->get(1)->order);
        $this->assertEquals(2, $segments->get(2)->order);
    }

    #[Test]
    public function it_combines_multiple_sources_fluently(): void
    {
        $agentResponse = new AgentResponse(
            content: 'LLM answer',
            toolCalls: [],
            usage: ['prompt_tokens' => 50, 'completion_tokens' => 30],
            metadata: [],
            latency: 0.3,
            finishReason: 'stop',
        );

        $ragResult = RagPipelineResult::forQuery(
            query: 'Query',
            results: [['content' => 'Context', 'score' => 0.85]],
            durationMs: 80,
        );

        $response = HybridResponse::builder('Complex query')
            ->withRagResult($ragResult)
            ->withAgentResponse($agentResponse)
            ->withStrategy(HybridStrategy::RAG_AUGMENTED)
            ->withMeta('custom_key', 'custom_value')
            ->build();

        $this->assertEquals('Complex query', $response->query);
        $this->assertEquals(HybridStrategy::RAG_AUGMENTED, $response->strategy);
        $this->assertEquals(2, $response->segmentCount());
        $this->assertTrue($response->isHybrid());
        $this->assertEquals('custom_value', $response->getMeta('custom_key'));
        $this->assertEquals(80, $response->getLatencyBreakdown()['rag_ms']);
        $this->assertEquals(300, $response->getLatencyBreakdown()['llm_ms']);
    }
}
