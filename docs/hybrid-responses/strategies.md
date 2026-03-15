# Hybrid Response Strategies

This document details each hybrid response strategy, when to use it, and how it works internally.

> **Related**: [Overview & API Reference](README.md) | [Middleware Integration](middleware-integration.md) | [RAG Pipeline](../rag/pipeline.md)

## Strategy Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          HYBRID STRATEGY SELECTION                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│   Query Type          Recommended Strategy       Characteristics            │
│   ──────────          ────────────────────       ───────────────            │
│                                                                             │
│   Factual/FAQ    ──►  RAG_WITH_FALLBACK         Cost-effective, fast       │
│   Documentation  ──►  RAG_ONLY                  Precise, no hallucination  │
│   Reasoning      ──►  RAG_AUGMENTED             Best synthesis             │
│   Creative       ──►  LLM_ONLY                  Full generation            │
│   Verification   ──►  LLM_WITH_VERIFICATION     Fact-checked               │
│   Comparison     ──►  PARALLEL                  Independent sources        │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

## RAG_ONLY

**Purpose**: Pure retrieval from the knowledge base without LLM processing.

**Flow**:
```
Query → RAG Pipeline → Retrieved Documents → Response
```

**When to use**:
- Exact documentation lookups
- FAQ responses where accuracy is critical
- When you want to avoid LLM hallucination
- Cost-sensitive applications
- Low-latency requirements

**Example**:
```php
$agent->withHybridStrategy(HybridStrategy::RAG_ONLY);
$response = $agent->respondHybrid('What are the system requirements?');

// Response contains only retrieved content
foreach ($response->getRagSegments() as $segment) {
    echo "Source: " . $segment->getMeta('source') . "\n";
    echo "Confidence: " . $segment->confidence . "\n";
    echo "Content: " . $segment->content . "\n\n";
}
```

**Characteristics**:
| Aspect | Value |
|--------|-------|
| LLM calls | 0 |
| RAG calls | 1 |
| Latency | Low |
| Cost | Low |
| Hallucination risk | None |
| Synthesis quality | None (raw retrieval) |

---

## LLM_ONLY

**Purpose**: Pure LLM generation without RAG context.

**Flow**:
```
Query → LLM → Generated Response
```

**When to use**:
- Creative writing tasks
- General knowledge questions
- Tasks where your knowledge base is not relevant
- Brainstorming and ideation

**Example**:
```php
$agent->withHybridStrategy(HybridStrategy::LLM_ONLY);
$response = $agent->respondHybrid('Write a poem about artificial intelligence');

// Response contains only LLM-generated content
$content = $response->getContent();
```

**Characteristics**:
| Aspect | Value |
|--------|-------|
| LLM calls | 1 |
| RAG calls | 0 |
| Latency | Medium |
| Cost | Medium |
| Hallucination risk | Standard |
| Synthesis quality | High |

---

## RAG_AUGMENTED

**Purpose**: Traditional RAG pattern - retrieve context, inject into LLM prompt.

**Flow**:
```
Query → RAG Pipeline → Context
                          ↓
Query + Context → LLM → Synthesized Response
```

**When to use**:
- Most question-answering scenarios
- When you need LLM reasoning over retrieved content
- Customer support with knowledge base
- Research and analysis tasks

**Example**:
```php
$agent->withHybridStrategy(HybridStrategy::RAG_AUGMENTED);
$response = $agent->respondHybrid('Compare our pricing plans');

// Response includes both RAG context and LLM synthesis
$ragContext = $response->getRagSegments();  // Retrieved pricing info
$llmAnswer = $response->getLlmSegments();   // Synthesized comparison
```

**Internal Implementation**:
```php
// 1. Retrieve RAG context
$ragResult = $this->retrieveRagContext($message);

// 2. Inject into prompt context
$context['rag_context'] = $this->getFormattedRagContext($ragResult);

// 3. Generate LLM response with augmented context
$response = $this->respond($message, $context);

// 4. Combine into HybridResponse
return HybridResponse::fromCombined($response, $ragResult, $message);
```

**Characteristics**:
| Aspect | Value |
|--------|-------|
| LLM calls | 1 |
| RAG calls | 1 |
| Latency | Medium-High |
| Cost | Medium |
| Hallucination risk | Low (grounded in context) |
| Synthesis quality | High |

---

## PARALLEL

**Purpose**: Execute RAG and LLM independently, combine results.

**Flow**:
```
         ┌──► RAG Pipeline ──► RAG Results ──┐
Query ───┤                                   ├──► Combined Response
         └──► LLM ──────────► LLM Response ──┘
```

**When to use**:
- Comparing retrieved vs generated content
- A/B testing response quality
- When you want both perspectives
- Verification without blocking

**Example**:
```php
$agent->withHybridStrategy(HybridStrategy::PARALLEL);
$response = $agent->respondHybrid('What is our refund policy?');

// Both sources queried independently
$fromKnowledgeBase = $response->getRagSegments();
$fromLLM = $response->getLlmSegments();

// Display both to user
echo "From our documentation:\n";
foreach ($fromKnowledgeBase as $segment) {
    echo "- " . $segment->content . " (confidence: {$segment->confidence})\n";
}

echo "\nAI Summary:\n";
echo $response->getLlmSegments()->first()->content;
```

**Characteristics**:
| Aspect | Value |
|--------|-------|
| LLM calls | 1 |
| RAG calls | 1 |
| Latency | Max(RAG, LLM) - can be parallelized |
| Cost | Medium |
| Hallucination risk | Mixed |
| Synthesis quality | None (independent results) |

---

## RAG_WITH_FALLBACK

**Purpose**: Use RAG if confidence is high enough, otherwise fall back to LLM.

**Flow**:
```
Query → RAG Pipeline → Check Confidence
                           ↓
              ┌────────────┴────────────┐
              ↓                         ↓
        High Confidence           Low Confidence
              ↓                         ↓
        Return RAG Results      Query LLM → Return LLM Response
```

**When to use**:
- Cost optimization (avoid LLM when RAG is sufficient)
- FAQ systems with fallback to conversational AI
- Tiered response quality based on confidence

**Example**:
```php
$agent
    ->withHybridStrategy(HybridStrategy::RAG_WITH_FALLBACK)
    ->withRagConfidenceThreshold(0.8);  // High threshold

$response = $agent->respondHybrid('How do I reset my password?');

// Check which source was used
$fallbackUsed = $response->getMeta('fallback_used');
if ($fallbackUsed) {
    echo "Answered by AI (no confident documentation match)\n";
} else {
    echo "Answered from documentation\n";
}
```

**Configuration**:
```php
// Adjust the confidence threshold
$agent->withRagConfidenceThreshold(0.7);  // Default

// Disable fallback entirely (RAG or nothing)
$agent->withLlmFallback(false);
```

**Characteristics**:
| Aspect | Value |
|--------|-------|
| LLM calls | 0 or 1 (conditional) |
| RAG calls | 1 |
| Latency | Variable |
| Cost | Low to Medium |
| Hallucination risk | Low (RAG preferred) |
| Synthesis quality | Variable |

---

## LLM_WITH_VERIFICATION

**Purpose**: Generate LLM response first, then verify/supplement with RAG.

**Flow**:
```
Query → LLM → Generated Response
                    ↓
              RAG Pipeline (verification)
                    ↓
              Combined Response with Verification Score
```

**When to use**:
- Fact-checking LLM responses
- Adding citations to generated content
- Compliance and accuracy requirements
- Building user trust

**Example**:
```php
$agent->withHybridStrategy(HybridStrategy::LLM_WITH_VERIFICATION);
$response = $agent->respondHybrid('Explain our data privacy practices');

// Check verification status
$verified = $response->getMeta('verified');
$verificationScore = $response->getMeta('verification_score');

if ($verified) {
    echo "✓ Response verified against documentation\n";
    echo "Verification score: " . ($verificationScore * 100) . "%\n";
} else {
    echo "⚠ Could not fully verify against documentation\n";
}

// Show supporting evidence
echo "\nSupporting documents:\n";
foreach ($response->getRagSegments() as $segment) {
    echo "- " . $segment->getMeta('source') . " (relevance: {$segment->confidence})\n";
}
```

**Characteristics**:
| Aspect | Value |
|--------|-------|
| LLM calls | 1 |
| RAG calls | 1 |
| Latency | High (sequential) |
| Cost | Medium |
| Hallucination risk | Detectable |
| Synthesis quality | High (with verification) |

---

## Strategy Selection Guide

### Decision Tree

```
Is the query about your specific documentation/knowledge base?
├─ YES → Is synthesis/reasoning required?
│        ├─ YES → RAG_AUGMENTED
│        └─ NO  → RAG_ONLY or RAG_WITH_FALLBACK
│
└─ NO  → Is accuracy critical?
         ├─ YES → LLM_WITH_VERIFICATION
         └─ NO  → LLM_ONLY
```

### By Use Case

| Use Case | Strategy | Rationale |
|----------|----------|-----------|
| Customer Support FAQ | `RAG_WITH_FALLBACK` | Fast, accurate, cost-effective |
| Technical Documentation | `RAG_ONLY` | Precision, no hallucination |
| Product Recommendations | `RAG_AUGMENTED` | Needs reasoning over catalog |
| Creative Writing | `LLM_ONLY` | Pure generation |
| Legal/Compliance Answers | `LLM_WITH_VERIFICATION` | Must be verified |
| Research Assistant | `PARALLEL` | Compare sources |

### By Priority

| Priority | Recommended Strategies |
|----------|----------------------|
| Accuracy | `RAG_ONLY`, `LLM_WITH_VERIFICATION` |
| Speed | `RAG_ONLY`, `RAG_WITH_FALLBACK` |
| Cost | `RAG_WITH_FALLBACK`, `RAG_ONLY` |
| Quality | `RAG_AUGMENTED`, `LLM_WITH_VERIFICATION` |
| Coverage | `PARALLEL`, `RAG_AUGMENTED` |

## Custom Strategy Implementation

You can extend the `HasHybridResponse` trait to implement custom strategies:

```php
trait HasCustomHybridResponse
{
    use HasHybridResponse;

    public function respondHybrid(
        string $message,
        array $context = [],
        ?HybridStrategy $strategy = null,
    ): HybridResponse {
        // Custom logic for strategy selection
        $strategy = $this->selectOptimalStrategy($message);

        return parent::respondHybrid($message, $context, $strategy);
    }

    protected function selectOptimalStrategy(string $message): HybridStrategy
    {
        // Analyze query to select best strategy
        if ($this->isFactualQuery($message)) {
            return HybridStrategy::RAG_WITH_FALLBACK;
        }

        if ($this->requiresReasoning($message)) {
            return HybridStrategy::RAG_AUGMENTED;
        }

        return HybridStrategy::LLM_ONLY;
    }
}
```
