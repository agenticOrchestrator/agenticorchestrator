# RAG Pipeline

The `RagPipeline` class is the main entry point for RAG operations. It provides a fluent interface for configuring, ingesting, and querying documents.

## Creating a Pipeline

### Basic Creation

```php
use AgenticOrchestrator\Rag\RagPipeline;

$pipeline = RagPipeline::make();
```

### With Configuration

```php
use AgenticOrchestrator\Rag\RagConfig;

$config = new RagConfig(
    namespace: 'my_knowledge_base',
    chunkSize: 1000,
    chunkOverlap: 200,
    scoreThreshold: 0.7,
);

$pipeline = RagPipeline::make($config);
```

## Configuration Methods

### Required Configuration

```php
$pipeline
    ->embeddings($embeddingProvider)  // EmbeddingProviderInterface
    ->store($vectorStore);            // VectorStoreInterface
```

### Optional Configuration

```php
$pipeline
    ->namespace('knowledge_base')     // Document namespace
    ->forTenant($tenantId)            // Multi-tenancy support
    ->chunkSize(1000)                 // Characters per chunk
    ->chunkOverlap(200)               // Overlap between chunks
    ->limit(5)                        // Max results to retrieve
    ->threshold(0.7);                 // Minimum similarity score
```

### Custom Components

```php
use AgenticOrchestrator\Rag\Loaders\MarkdownLoader;
use AgenticOrchestrator\Rag\Chunking\RecursiveCharacterChunker;
use AgenticOrchestrator\Rag\Retrievers\HybridRetriever;
use AgenticOrchestrator\Rag\Rerankers\ScoreThresholdReranker;

$pipeline
    ->loader(new MarkdownLoader())
    ->chunker(new RecursiveCharacterChunker())
    ->retriever(new HybridRetriever($embeddings, $store))
    ->reranker(new ScoreThresholdReranker(0.8));
```

## Ingesting Documents

### From a Directory

```php
$result = $pipeline->from('/path/to/documents')->ingest();

echo "Processed: {$result->documentsProcessed} documents";
echo "Created: {$result->chunksCreated} chunks";
echo "Duration: {$result->getDurationSeconds()}s";
```

### From a File

```php
$result = $pipeline->from('/path/to/document.md')->ingest();
```

### From Text

```php
$result = $pipeline
    ->fromText('Your document content here', ['category' => 'faq'])
    ->ingest();
```

### From Multiple Sources

```php
use AgenticOrchestrator\Rag\Document;

$result = $pipeline
    ->from('/path/to/docs')
    ->fromText('Additional content')
    ->addDocument(Document::fromText('More content'))
    ->addDocuments($documentArray)
    ->ingest();
```

## Querying

### Basic Query

```php
$result = $pipeline->query('What is the return policy?');

if ($result->hasContext()) {
    $context = $result->getContext();
    echo $context;
}
```

### Query with Options

```php
$result = $pipeline
    ->limit(10)
    ->threshold(0.8)
    ->query('How do I configure authentication?');
```

### Search Alias

```php
// search() is an alias for query()
$result = $pipeline->search('search terms');
```

## Working with Results

### RagPipelineResult

The result object provides various methods to access and format the retrieved content.

```php
$result = $pipeline->query('user question');

// Check if context was found
if ($result->hasContext()) {
    // Get formatted context string
    $context = $result->getContext();

    // Get context with custom separator
    $context = $result->getContext("\n\n===\n\n");

    // Get context with relevance scores
    $context = $result->getContextWithScores();
    // Output: [Relevance: 95%]\nContent here...
}

// Get raw results
$results = $result->getResults(); // array<VectorSearchResult>

// Get best match
$best = $result->getBestResult();
$bestContent = $result->getBestMatch();

// Filter by threshold
$highQuality = $result->getResultsAboveThreshold(0.9);

// Statistics
$count = $result->count();
$avgScore = $result->getAverageScore();
$sources = $result->getSources(); // unique source files
```

### Ingest Result

```php
$result = $pipeline->from('/docs')->ingest();

// Check operation type
$result->isIngest(); // true
$result->isQuery();  // false

// Access metrics
$result->documentsProcessed;
$result->chunksCreated;
$result->durationMs;
$result->getDurationSeconds();

// Get metadata
$result->getMeta('namespace');
$result->getMeta('chunk_size');
```

## Deleting Documents

### Delete by Filter

```php
// Delete documents matching filter
$deleted = $pipeline->delete(['category' => 'outdated']);
```

### Clear Namespace

```php
// Delete all documents in current namespace
$deleted = $pipeline->clear();
```

## Configuration Object

### RagConfig

```php
use AgenticOrchestrator\Rag\RagConfig;

$config = new RagConfig(
    namespace: 'default',
    chunkSize: 1000,
    chunkOverlap: 200,
    chunker: 'recursive',      // 'recursive' or 'fixed'
    retriever: 'vector',       // 'vector' or 'hybrid'
    retrieveLimit: 5,
    scoreThreshold: 0.7,
    tenantId: null,
    extra: [],
);

// Immutable updates
$newConfig = $config
    ->withNamespace('new_namespace')
    ->withChunkSize(500)
    ->withTenantId('tenant-123');

// Get effective namespace (includes tenant prefix)
$namespace = $config->getEffectiveNamespace();
// Returns: "tenant_123_default" if tenant is set
```

### Creating from Laravel Config

```php
$config = RagConfig::fromConfig(config('agent-orchestrator.rag'));
```

## Multi-Tenancy

### Tenant Scoping

```php
// Scope to a tenant
$pipeline->forTenant($team->id);

// The effective namespace becomes: tenant_{id}_{namespace}
$pipeline->namespace('knowledge')->forTenant(42);
// Effective namespace: "tenant_42_knowledge"
```

### Per-Tenant Ingestion

```php
foreach ($teams as $team) {
    $pipeline
        ->forTenant($team->id)
        ->from($team->documents_path)
        ->ingest();
}
```

### Per-Tenant Queries

```php
$result = $pipeline
    ->forTenant($currentTeam->id)
    ->query($userQuestion);
```

## Error Handling

```php
use InvalidArgumentException;

try {
    $pipeline->ingest();
} catch (InvalidArgumentException $e) {
    // Missing embeddings or store configuration
    echo $e->getMessage();
}

try {
    $pipeline->query('question');
} catch (InvalidArgumentException $e) {
    // Missing embeddings or store configuration
}
```

## Complete Example

```php
use AgenticOrchestrator\Rag\RagPipeline;
use AgenticOrchestrator\Embeddings\Providers\OpenAIEmbeddings;
use AgenticOrchestrator\Embeddings\Stores\PgVectorStore;

// Initialize components
$embeddings = new OpenAIEmbeddings(env('OPENAI_API_KEY'));
$store = new PgVectorStore(config('database.connections.pgsql'));

// Create pipeline
$pipeline = RagPipeline::make()
    ->embeddings($embeddings)
    ->store($store)
    ->namespace('support_docs')
    ->forTenant($team->id)
    ->chunkSize(1000)
    ->chunkOverlap(200);

// Ingest documentation
$ingestResult = $pipeline
    ->from(resource_path('docs/support'))
    ->ingest();

logger()->info('Ingested documents', [
    'documents' => $ingestResult->documentsProcessed,
    'chunks' => $ingestResult->chunksCreated,
    'duration' => $ingestResult->getDurationSeconds(),
]);

// Later: Query for context
$queryResult = $pipeline
    ->limit(5)
    ->threshold(0.75)
    ->query($userMessage);

if ($queryResult->hasContext()) {
    $context = $queryResult->getContext();

    // Use context in LLM prompt
    $prompt = "Use this context to answer the question:\n\n{$context}\n\nQuestion: {$userMessage}";
}
```
