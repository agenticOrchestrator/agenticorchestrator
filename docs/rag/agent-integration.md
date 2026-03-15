# Agent Integration

The RAG pipeline integrates seamlessly with agents through the `HasRag` trait and `#[RagSource]` attribute.

## HasRag Trait

Add RAG capabilities to any agent by including the `HasRag` trait.

### Basic Setup

```php
use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Agents\Concerns\HasRag;

class CustomerSupportAgent extends Agent
{
    use HasRag;

    protected string $name = 'Customer Support';
    protected string $instructions = 'Help customers using the knowledge base.';
}
```

### Configuring the Pipeline

```php
use AgenticOrchestrator\Rag\RagPipeline;

// Create pipeline
$pipeline = RagPipeline::make()
    ->embeddings($embeddings)
    ->store($store)
    ->namespace('support_docs');

// Attach to agent
$agent = CustomerSupportAgent::make()->withRag($pipeline);
```

### Using RAG Context

```php
// Retrieve context for a query
$result = $agent->retrieveRagContext('How do I reset my password?');

if ($result->hasContext()) {
    $context = $result->getContext();
}

// Get formatted context for prompt injection
$formattedContext = $agent->getFormattedRagContext('user question');
```

## RagSource Attribute

The `#[RagSource]` attribute provides declarative RAG configuration.

### Class-Level Attribute

```php
use AgenticOrchestrator\Rag\Attributes\RagSource;

#[RagSource(namespace: 'product_faq', limit: 5, threshold: 0.7)]
class ProductSupportAgent extends Agent
{
    use HasRag;
}
```

### Property-Level Attribute

```php
class MultiSourceAgent extends Agent
{
    use HasRag;

    #[RagSource(namespace: 'general_faq', limit: 3)]
    protected string $faqSource;

    #[RagSource(namespace: 'technical_docs', limit: 5, threshold: 0.8)]
    protected string $technicalSource;
}
```

### Multiple Sources

```php
#[RagSource(namespace: 'faq', limit: 3)]
#[RagSource(namespace: 'documentation', limit: 5)]
#[RagSource(namespace: 'tutorials', limit: 2)]
class ComprehensiveAgent extends Agent
{
    use HasRag;
}
```

### Attribute Options

```php
#[RagSource(
    namespace: 'knowledge_base',  // Required: namespace to query
    limit: 5,                     // Max results (default: 5)
    threshold: 0.7,               // Min similarity (default: 0.7)
    contextTemplate: null,        // Custom template (optional)
    enabled: true,                // Enable/disable (default: true)
    filter: [],                   // Additional filters (default: [])
)]
```

### Custom Context Template

```php
#[RagSource(
    namespace: 'faq',
    contextTemplate: <<<'TEMPLATE'
## Reference Information

Here is relevant context from the FAQ:

{context}

Use this information to help answer the user's question.

---

TEMPLATE
)]
class FaqAgent extends Agent
{
    use HasRag;
}
```

## Trait Methods

### Retrieving Context

```php
// Single query
$result = $agent->retrieveRagContext('question');

// From all configured sources
$results = $agent->retrieveRagContextFromSources('question');
// Returns: ['namespace1' => RagPipelineResult, 'namespace2' => RagPipelineResult]

// Get formatted context for prompt
$context = $agent->getFormattedRagContext('question');
```

### Managing RAG State

```php
// Check if RAG is enabled
if ($agent->isRagEnabled()) {
    // RAG is configured and enabled
}

// Enable/disable RAG
$agent->enableRag();
$agent->disableRag();

// Check for configured sources
if ($agent->hasRagSources()) {
    $sources = $agent->getRagSources();
}

// Access pipeline
$pipeline = $agent->getRagPipeline();
```

### Ingesting Content

Agents can ingest content directly:

```php
// Ingest documents
$result = $agent->ingestDocuments($documents);

// Ingest from path
$result = $agent->ingestFromPath('/path/to/docs');

// Ingest text
$result = $agent->ingestText('Content to ingest', ['category' => 'faq']);
```

## Integration Patterns

### Pattern 1: Pre-Response Context Injection

Retrieve context before generating a response:

```php
class ContextAwareAgent extends Agent
{
    use HasRag;

    public function respond(string $message): AgentResponse
    {
        // Get RAG context
        $context = $this->getFormattedRagContext($message);

        // Inject into instructions
        $enhancedInstructions = $this->instructions . "\n\n" . $context;

        // Continue with response generation
        return parent::respond($message);
    }
}
```

### Pattern 2: Tool-Based RAG

Expose RAG as a tool the agent can use:

```php
use AgenticOrchestrator\Tools\Attributes\Tool;

class ToolBasedAgent extends Agent
{
    use HasRag;

    #[Tool(
        name: 'search_knowledge_base',
        description: 'Search the knowledge base for relevant information'
    )]
    public function searchKnowledgeBase(string $query): string
    {
        $result = $this->retrieveRagContext($query);

        if (!$result->hasContext()) {
            return "No relevant information found for: {$query}";
        }

        return $result->getContext();
    }
}
```

### Pattern 3: Multi-Tenant RAG

Configure RAG per tenant:

```php
class TenantAwareAgent extends Agent
{
    use HasRag;

    public function forTeam($team): static
    {
        $pipeline = $this->getRagPipeline();

        if ($pipeline) {
            $pipeline->forTenant($team->id);
        }

        return parent::forTeam($team);
    }
}
```

### Pattern 4: Conditional RAG

Enable RAG based on conditions:

```php
class ConditionalRagAgent extends Agent
{
    use HasRag;

    public function respond(string $message): AgentResponse
    {
        // Only use RAG for questions
        if ($this->isQuestion($message)) {
            $this->enableRag();
        } else {
            $this->disableRag();
        }

        return parent::respond($message);
    }

    private function isQuestion(string $message): bool
    {
        return str_contains($message, '?') ||
               preg_match('/^(how|what|why|when|where|who|can|could|would|should)/i', $message);
    }
}
```

### Pattern 5: Fallback RAG

Use RAG when the agent is uncertain:

```php
class FallbackRagAgent extends Agent
{
    use HasRag;

    public function respond(string $message): AgentResponse
    {
        // First attempt without RAG
        $response = parent::respond($message);

        // Check confidence (implementation depends on your response structure)
        if ($this->isLowConfidence($response)) {
            // Retry with RAG context
            $context = $this->getFormattedRagContext($message);
            if ($context) {
                // Re-generate with context
                $response = $this->respondWithContext($message, $context);
            }
        }

        return $response;
    }
}
```

## Complete Example

```php
use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Agents\Concerns\HasRag;
use AgenticOrchestrator\Rag\Attributes\RagSource;
use AgenticOrchestrator\Rag\RagPipeline;
use AgenticOrchestrator\Embeddings\Providers\OpenAIEmbeddings;
use AgenticOrchestrator\Embeddings\Stores\PgVectorStore;

#[RagSource(namespace: 'product_docs', limit: 5, threshold: 0.75)]
class ProductExpertAgent extends Agent
{
    use HasRag;

    protected string $name = 'Product Expert';

    protected string $instructions = <<<'INSTRUCTIONS'
You are a product expert assistant. Use the provided context to answer
questions about our products accurately. If you don't have enough
information, say so rather than making things up.
INSTRUCTIONS;

    protected array $tools = [
        // Your tools here
    ];
}

// Setup
$embeddings = new OpenAIEmbeddings(env('OPENAI_API_KEY'));
$store = new PgVectorStore(config('database.connections.pgsql'));

$pipeline = RagPipeline::make()
    ->embeddings($embeddings)
    ->store($store)
    ->namespace('product_docs')
    ->chunkSize(1000)
    ->chunkOverlap(200);

// Initial ingestion (run once or on content update)
$pipeline->from(resource_path('docs/products'))->ingest();

// Create agent with RAG
$agent = ProductExpertAgent::make()
    ->withRag($pipeline)
    ->forTeam($currentTeam);

// Handle user query
$response = $agent->respond($userMessage);
```

## Hybrid Response Integration

For unified responses that combine LLM and RAG with clear source attribution, use the `HasHybridResponse` trait.

### Using HasHybridResponse

```php
use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Agents\Concerns\HasHybridResponse;
use AgenticOrchestrator\Responses\HybridStrategy;

class SmartSupportAgent extends Agent
{
    use HasHybridResponse; // Includes HasRag

    protected HybridStrategy $defaultHybridStrategy = HybridStrategy::RAG_AUGMENTED;
    protected float $ragConfidenceThreshold = 0.7;
}

// Get a unified response with source attribution
$response = $agent->respondHybrid('What is your return policy?');

// Access different parts of the response
$response->getContent();          // Primary answer
$response->getRagSegments();      // Retrieved context
$response->getLlmSegments();      // LLM-generated content
$response->getSources();          // Document sources
$response->isHybrid();            // true if both RAG and LLM used
```

### Available Strategies

| Strategy | Description |
|----------|-------------|
| `RAG_ONLY` | Pure retrieval, no LLM |
| `LLM_ONLY` | Pure LLM, no RAG |
| `RAG_AUGMENTED` | RAG context injected into LLM (default) |
| `PARALLEL` | RAG and LLM queried independently |
| `RAG_WITH_FALLBACK` | RAG first, LLM if confidence low |
| `LLM_WITH_VERIFICATION` | LLM first, RAG verifies |

### Example: Strategy Selection

```php
$agent = SmartSupportAgent::make()
    ->withRag($pipeline)
    ->withHybridStrategy(HybridStrategy::RAG_WITH_FALLBACK)
    ->withRagConfidenceThreshold(0.8)
    ->withLlmFallback(true);

$response = $agent->respondHybrid('How do I get a refund?');

// Check which source was used
if ($response->getMeta('fallback_used')) {
    echo "Answered by AI (no confident documentation match)";
} else {
    echo "Answered from documentation";
}
```

For complete documentation on hybrid responses, see [Hybrid Responses](../hybrid-responses/README.md).

## Best Practices

1. **Choose Appropriate Namespaces**: Use meaningful namespaces to organize knowledge by domain.

2. **Set Realistic Thresholds**: Start with 0.7 and adjust based on result quality.

3. **Limit Results**: Don't overwhelm the context window; 3-5 results is usually sufficient.

4. **Use Custom Templates**: Tailor context formatting to your agent's style.

5. **Handle Empty Results**: Always check `hasContext()` and provide fallback behavior.

6. **Consider Token Limits**: RAG context consumes tokens; balance context size with response capacity.

7. **Update Content Regularly**: Re-ingest documents when source content changes.

8. **Monitor Performance**: Log RAG queries and results to identify improvement opportunities.

9. **Use Hybrid Responses for APIs**: When building endpoints, use `HasHybridResponse` for consistent response formatting with source attribution.

10. **Choose the Right Strategy**: Use `RAG_WITH_FALLBACK` for cost efficiency, `RAG_AUGMENTED` for best synthesis quality.

## Related Documentation

- [Hybrid Responses Overview](../hybrid-responses/README.md) - Combining LLM and RAG responses
- [Hybrid Strategies](../hybrid-responses/strategies.md) - Detailed strategy guide
- [Middleware Integration](../hybrid-responses/middleware-integration.md) - HTTP/API patterns
