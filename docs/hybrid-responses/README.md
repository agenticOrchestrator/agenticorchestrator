# Hybrid Responses

The Hybrid Response system provides a unified way to combine responses from multiple sources (LLM, RAG, tools, cache) into a single, homogeneous response structure with clear source attribution.

## Overview

When building AI-powered applications, you often need to combine:
- **LLM responses**: Generated content from language models
- **RAG results**: Retrieved context from your knowledge base
- **Tool outputs**: Results from function/tool execution
- **Cached responses**: Previously computed results

The Hybrid Response system solves this by providing:
- A unified response envelope (`HybridResponse`)
- Clear source attribution per content segment
- Confidence scoring for retrieved content
- Multiple combination strategies
- Middleware-friendly output formats

## Quick Start

### Basic Usage

```php
use AgenticOrchestrator\Responses\HybridResponse;
use AgenticOrchestrator\Responses\HybridStrategy;

// Create from an agent response (LLM only)
$hybridResponse = HybridResponse::fromAgentResponse($agentResponse, $query);

// Create from RAG results only
$hybridResponse = HybridResponse::fromRagResult($ragResult, $query);

// Combine LLM and RAG
$hybridResponse = HybridResponse::fromCombined(
    llmResponse: $agentResponse,
    ragResult: $ragResult,
    query: $query,
    strategy: HybridStrategy::RAG_AUGMENTED,
);
```

### Using the Builder

```php
$response = HybridResponse::builder('What is the refund policy?')
    ->withRagResult($ragResult)
    ->withAgentResponse($llmResponse)
    ->withStrategy(HybridStrategy::RAG_AUGMENTED)
    ->withMeta('request_id', $requestId)
    ->build();
```

### Agent Integration

```php
use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Agents\Concerns\HasHybridResponse;

class CustomerSupportAgent extends Agent
{
    use HasHybridResponse;

    protected HybridStrategy $defaultHybridStrategy = HybridStrategy::RAG_AUGMENTED;
    protected float $ragConfidenceThreshold = 0.7;

    public function instructions(): string
    {
        return 'You are a helpful customer support agent...';
    }
}

// Usage
$agent = new CustomerSupportAgent();
$response = $agent->respondHybrid('What is your return policy?');
```

## Core Concepts

### Response Segments

Each piece of content in a hybrid response is wrapped in a `ResponseSegment`:

```php
use AgenticOrchestrator\Responses\ResponseSegment;

// From RAG retrieval
$segment = ResponseSegment::fromRag(
    content: 'Our return policy allows returns within 30 days...',
    score: 0.92,
    metadata: ['source' => 'policies/returns.md']
);

// From LLM generation
$segment = ResponseSegment::fromLlm(
    content: 'Based on our policy, you can return items within 30 days.',
    metadata: ['model' => 'gpt-4o', 'finish_reason' => 'stop']
);

// From cache
$segment = ResponseSegment::fromCache(
    content: 'Cached response...',
    metadata: ['cached_at' => '2024-01-15']
);

// From tool execution
$segment = ResponseSegment::fromTool(
    content: '{"order_status": "shipped"}',
    toolName: 'order_lookup',
    metadata: ['execution_time_ms' => 45]
);
```

### Hybrid Strategies

The system supports multiple strategies for combining sources:

| Strategy | Description | Use Case |
|----------|-------------|----------|
| `RAG_ONLY` | Pure retrieval, no LLM | FAQ lookups, exact documentation |
| `LLM_ONLY` | Pure generation, no RAG | Creative tasks, general knowledge |
| `RAG_AUGMENTED` | RAG context injected into LLM prompt | Most common pattern |
| `PARALLEL` | RAG and LLM queried independently | Comparison, verification |
| `RAG_WITH_FALLBACK` | RAG first, LLM if confidence low | Cost optimization |
| `LLM_WITH_VERIFICATION` | LLM first, RAG verifies | Fact-checking |

```php
use AgenticOrchestrator\Responses\HybridStrategy;

// Check strategy capabilities
$strategy = HybridStrategy::RAG_AUGMENTED;

$strategy->usesRag();    // true
$strategy->usesLlm();    // true
$strategy->isHybrid();   // true
$strategy->description(); // "LLM generation augmented with retrieved context"
```

## API Reference

### HybridResponse

#### Factory Methods

```php
// From agent response only
HybridResponse::fromAgentResponse(AgentResponse $response, string $query): HybridResponse

// From RAG result only
HybridResponse::fromRagResult(RagPipelineResult $result, string $query): HybridResponse

// Combined LLM + RAG
HybridResponse::fromCombined(
    AgentResponse $llmResponse,
    RagPipelineResult $ragResult,
    string $query,
    HybridStrategy $strategy = HybridStrategy::RAG_AUGMENTED
): HybridResponse

// Builder pattern
HybridResponse::builder(string $query): HybridResponseBuilder
```

#### Accessing Content

```php
// Get primary content (LLM preferred for hybrid, highest confidence for RAG-only)
$content = $response->getContent();

// Get combined content from all segments
$combined = $response->getCombinedContent("\n\n");

// Get content with source attribution
$attributed = $response->getAttributedContent();
// Returns: [
//   ['content' => '...', 'source' => 'rag', 'confidence' => 0.92],
//   ['content' => '...', 'source' => 'llm', 'confidence' => null],
// ]
```

#### Working with Segments

```php
// Get all segments
$segments = $response->getSegments();

// Filter by source
$ragSegments = $response->getRagSegments();
$llmSegments = $response->getLlmSegments();

// Get high confidence segments
$highConfidence = $response->getHighConfidenceSegments(0.8);

// Get primary segment
$primary = $response->getPrimarySegment();

// Transform segments
$transformed = $response->mapSegments(fn($s) => $s->truncate(500));

// Filter segments
$filtered = $response->filterSegments(fn($s) => $s->meetsThreshold(0.7));
```

#### Metadata and Statistics

```php
// Check content sources
$response->hasRagContext();  // bool
$response->hasLlmContent();  // bool
$response->isHybrid();       // bool (has both)

// Get statistics
$response->segmentCount();
$response->getAverageRagConfidence();
$response->getSources();     // ['doc1.md', 'doc2.md']
$response->getTotalTokens();
$response->getTotalLatencyMs();
$response->getLatencyBreakdown(); // ['rag_ms' => 100, 'llm_ms' => 500, 'total_ms' => 600]
```

#### Output Formats

```php
// Full array representation
$array = $response->toArray();

// Simplified API response
$api = $response->toApiResponse();
// Returns: [
//   'content' => '...',
//   'segments' => [...],
//   'strategy' => 'rag_augmented',
//   'sources' => ['doc1.md'],
//   'latency_ms' => 600
// ]

// JSON serialization
$json = json_encode($response);

// String conversion (returns primary content)
echo $response; // "Primary content..."
```

### HybridResponseBuilder

```php
$response = HybridResponse::builder($query)
    // Add sources
    ->withAgentResponse($agentResponse)
    ->withRagResult($ragResult)
    ->withSegment($customSegment)
    ->withRagSegment('content', 0.9, ['source' => 'doc.md'])
    ->withLlmSegment('content', ['model' => 'gpt-4'])
    ->withCachedSegment('content', ['ttl' => 3600])
    ->withToolSegment('output', 'tool_name', [])

    // Configure
    ->withStrategy(HybridStrategy::PARALLEL)
    ->withUsage(['prompt_tokens' => 100, 'completion_tokens' => 50])
    ->withLatency(['rag_ms' => 100, 'llm_ms' => 500])
    ->withMetadata(['key' => 'value'])
    ->withMeta('single_key', 'value')

    // Auto-detect strategy based on segments
    ->autoDetectStrategy()

    // Build
    ->build();
```

### ResponseSegment

```php
$segment = new ResponseSegment(
    content: 'The content text',
    source: 'rag',           // rag, llm, cached, tool
    confidence: 0.92,        // 0.0-1.0 for RAG, null for LLM
    metadata: ['key' => 'value'],
    order: 0                 // display order
);

// Check source type
$segment->isFromRag();
$segment->isFromLlm();
$segment->isFromCache();
$segment->isFromTool();

// Confidence checks
$segment->hasConfidence();
$segment->meetsThreshold(0.8);

// Utility methods
$segment->length();
$segment->truncate(500, '...');
$segment->withOrder(5);
$segment->getMeta('key', 'default');
```

## Middleware Integration

### Laravel HTTP Response

```php
// In a controller
public function ask(Request $request)
{
    $agent = new CustomerSupportAgent();
    $response = $agent->respondHybrid($request->input('question'));

    return response()->json($response->toApiResponse());
}
```

### Custom API Format

```php
public function ask(Request $request)
{
    $response = $agent->respondHybrid($request->input('question'));

    return response()->json([
        'answer' => $response->getContent(),
        'confidence' => $response->getAverageRagConfidence(),
        'sources' => $response->getSources(),
        'segments' => $response->getAttributedContent(),
        'meta' => [
            'strategy' => $response->strategy->value,
            'latency_ms' => $response->getTotalLatencyMs(),
            'tokens' => $response->getTotalTokens(),
        ],
    ]);
}
```

### Streaming Support

```php
// For real-time updates, stream segments as they arrive
public function streamResponse(Request $request)
{
    return response()->stream(function () use ($request) {
        $builder = HybridResponse::builder($request->input('question'));

        // Stream RAG results first
        $ragResult = $this->ragPipeline->query($request->input('question'));
        $builder->withRagResult($ragResult);

        echo "data: " . json_encode(['type' => 'rag', 'segments' => $ragResult->getResults()]) . "\n\n";
        ob_flush();
        flush();

        // Then stream LLM response
        $llmResponse = $this->agent->respond($request->input('question'));
        $builder->withAgentResponse($llmResponse);

        echo "data: " . json_encode(['type' => 'llm', 'content' => $llmResponse->content]) . "\n\n";
        ob_flush();
        flush();

        // Final combined response
        $response = $builder->build();
        echo "data: " . json_encode(['type' => 'complete', 'response' => $response->toApiResponse()]) . "\n\n";
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
    ]);
}
```

## Best Practices

### 1. Choose the Right Strategy

```php
// For FAQ/documentation queries - RAG might be sufficient
if ($query->isFactual()) {
    $strategy = HybridStrategy::RAG_WITH_FALLBACK;
}

// For complex reasoning - need LLM synthesis
if ($query->requiresReasoning()) {
    $strategy = HybridStrategy::RAG_AUGMENTED;
}

// For creative tasks - LLM only
if ($query->isCreative()) {
    $strategy = HybridStrategy::LLM_ONLY;
}
```

### 2. Set Appropriate Confidence Thresholds

```php
$agent->withRagConfidenceThreshold(0.8);  // High threshold for critical info
$agent->withRagConfidenceThreshold(0.5);  // Lower for general queries
```

### 3. Handle Edge Cases

```php
$response = $agent->respondHybrid($query);

if ($response->isEmpty()) {
    // No content from any source
    return $this->fallbackResponse();
}

if (!$response->hasRagContext() && $response->strategy->usesRag()) {
    // RAG returned nothing - might want to warn user
    $response = $response->withMeta('warning', 'No relevant documents found');
}
```

### 4. Log for Debugging

```php
Log::info('Hybrid response generated', [
    'query' => $response->query,
    'strategy' => $response->strategy->value,
    'segment_count' => $response->segmentCount(),
    'has_rag' => $response->hasRagContext(),
    'has_llm' => $response->hasLlmContent(),
    'avg_confidence' => $response->getAverageRagConfidence(),
    'latency' => $response->getLatencyBreakdown(),
    'sources' => $response->getSources(),
]);
```

## Documentation Index

### Hybrid Response Documentation

| Document | Description |
|----------|-------------|
| [README](README.md) | Overview, quick start, and API reference (this document) |
| [Strategies](strategies.md) | Detailed guide to all 6 hybrid strategies |
| [Middleware Integration](middleware-integration.md) | HTTP/API patterns and examples |

### Related RAG Documentation

| Document | Description |
|----------|-------------|
| [RAG Overview](../rag/README.md) | RAG pipeline basics and setup |
| [Agent Integration](../rag/agent-integration.md) | Using RAG with agents |
| [Loaders](../rag/loaders.md) | Document loading from various sources |
| [Chunking](../rag/chunking.md) | Text chunking strategies |
| [Retrieval](../rag/retrieval.md) | Retrievers and rerankers |

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           HYBRID RESPONSE SYSTEM                            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│   User Query                                                                │
│       │                                                                     │
│       ▼                                                                     │
│   ┌───────────────┐                                                         │
│   │   Agent with  │                                                         │
│   │ HasHybridResp │                                                         │
│   └───────┬───────┘                                                         │
│           │                                                                 │
│           ▼                                                                 │
│   ┌───────────────┐     ┌─────────────┐     ┌─────────────┐                │
│   │   Strategy    │────▶│ RAG Pipeline │────▶│ ResponseSeg │                │
│   │   Selection   │     └─────────────┘     │   (RAG)     │                │
│   └───────┬───────┘                         └──────┬──────┘                │
│           │                                        │                        │
│           │             ┌─────────────┐     ┌──────┴──────┐                │
│           └────────────▶│ LLM Provider │────▶│ ResponseSeg │                │
│                         └─────────────┘     │   (LLM)     │                │
│                                             └──────┬──────┘                │
│                                                    │                        │
│                                             ┌──────▼──────┐                │
│                                             │   Hybrid    │                │
│                                             │  Response   │                │
│                                             └─────────────┘                │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```
