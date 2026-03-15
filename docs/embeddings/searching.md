# Semantic Search

Perform semantic similarity search across embedded documents using vector stores.

## Overview

Semantic search finds documents by meaning rather than keyword matching. The workflow is:

1. Generate an embedding vector for your query text
2. Pass the vector to the store's `search()` method
3. Receive ranked `VectorSearchResult` objects

## Basic Search

```php
use AgenticOrchestrator\Embeddings\Providers\OpenAIEmbeddings;
use AgenticOrchestrator\Embeddings\Stores\QdrantVectorStore;

// Initialize components
$embeddings = new OpenAIEmbeddings(
    apiKey: config('services.openai.api_key'),
);

$store = new QdrantVectorStore([
    'host' => 'http://localhost:6333',
    'collection' => 'documentation',
]);

// Generate query embedding
$queryVector = $embeddings->embed('How do I authenticate users?');

// Search for similar documents
$results = $store->search(
    embedding: $queryVector,
    limit: 5,
);

foreach ($results as $result) {
    echo "Score: {$result->score}\n";
    echo "Content: {$result->getContent()}\n";
    echo "Source: {$result->getMeta('source')}\n\n";
}
```

## Search Parameters

### Limit Results

```php
$results = $store->search(
    embedding: $queryVector,
    limit: 10, // Return top 10 results
);
```

### With Metadata Filters

```php
$results = $store->search(
    embedding: $queryVector,
    limit: 10,
    filter: [
        'category' => 'security',
        'version' => '2.0',
    ],
);
```

## Working with Search Results

Search returns an array of `VectorSearchResult` objects:

```php
foreach ($results as $result) {
    // Access the wrapped document
    $document = $result->document;

    // Convenience accessors
    $id = $result->getId();
    $content = $result->getContent();
    $metadata = $result->getMetadata();

    // Get specific metadata
    $source = $result->getMeta('source');
    $category = $result->getMeta('category', 'unknown');

    // Similarity score (higher = more similar)
    $score = $result->score;

    // Distance (lower = closer, may be null)
    $distance = $result->distance;
}
```

### Filtering by Score Threshold

```php
// Filter results by minimum score
$filtered = array_filter(
    $results,
    fn ($result) => $result->isAboveThreshold(0.7)
);
```

## Store-Specific Filtering

Each vector store supports different filter syntax.

### Simple Key-Value Filters

All stores support basic equality filters:

```php
$results = $store->search($vector, 10, [
    'category' => 'tutorials',
    'status' => 'published',
]);
```

### Qdrant Advanced Filters

Qdrant supports complex filter conditions:

```php
// Simple filters are converted automatically
$results = $store->search($vector, 10, [
    'published' => true,
    'category' => 'ai',
]);

// For advanced queries, build the filter structure directly
// when interfacing with the Qdrant API
```

### Weaviate Advanced Filters

Weaviate supports GraphQL-style filters:

```php
// Simple filters work for equality
$results = $store->search($vector, 10, [
    'source' => 'official-docs',
]);

// For complex conditions, pass the full filter structure
$results = $store->search($vector, 10, [
    'operator' => 'And',
    'operands' => [
        [
            'path' => ['category'],
            'operator' => 'Equal',
            'valueString' => 'tutorials',
        ],
        [
            'path' => ['views'],
            'operator' => 'GreaterThan',
            'valueInt' => 1000,
        ],
    ],
]);
```

### Chroma Filters

Chroma uses simple equality filters:

```php
$results = $store->search($vector, 10, [
    'type' => 'tutorial',
]);
```

### PgVector Filters

PgVector supports equality and array membership:

```php
// Equality filter
$results = $store->search($vector, 10, [
    'category' => 'tutorials',
]);

// Array membership (IN clause)
$results = $store->search($vector, 10, [
    'status' => ['published', 'featured'],
]);
```

## RAG Pattern Implementation

Combine vector search with LLM generation for grounded responses.

### Basic RAG Service

```php
use AgenticOrchestrator\Embeddings\Providers\OpenAIEmbeddings;
use AgenticOrchestrator\Embeddings\Stores\QdrantVectorStore;

class RAGService
{
    public function __construct(
        private OpenAIEmbeddings $embeddings,
        private QdrantVectorStore $store,
    ) {}

    /**
     * Retrieve relevant context for a query.
     */
    public function retrieveContext(
        string $query,
        int $limit = 5,
        float $threshold = 0.7,
    ): array {
        // Generate query embedding
        $queryVector = $this->embeddings->embed($query);

        // Search for relevant documents
        $results = $this->store->search(
            embedding: $queryVector,
            limit: $limit,
        );

        // Filter by similarity threshold
        return array_filter(
            $results,
            fn ($result) => $result->isAboveThreshold($threshold)
        );
    }

    /**
     * Build a context string from search results.
     */
    public function buildContextString(array $results): string
    {
        $context = "Relevant information:\n\n";

        foreach ($results as $result) {
            $context .= "---\n";
            $context .= "Source: " . ($result->getMeta('source') ?? 'Unknown') . "\n";
            $context .= $result->getContent() . "\n\n";
        }

        return $context;
    }
}
```

### Using RAG with an Agent

```php
use AgenticOrchestrator\Agent;

$ragService = app(RAGService::class);

$userQuery = 'What are the key features of our product?';
$contextResults = $ragService->retrieveContext($userQuery);
$contextString = $ragService->buildContextString($contextResults);

$agent = Agent::create()
    ->withProvider('openai')
    ->withModel('gpt-4o')
    ->withSystemPrompt(
        "You are a helpful assistant. Use the following context to answer questions.\n\n" .
        "If the context doesn't contain relevant information, say so.\n\n" .
        $contextString
    );

$response = $agent->chat($userQuery);
```

## Search Result Processing

### Reranking Results

Apply additional criteria to refine ranking:

```php
$results = $store->search($queryVector, 20);

$reranked = collect($results)
    ->map(function ($result) {
        // Boost recent documents
        $recencyBoost = $this->calculateRecencyBoost($result);

        // Boost trusted sources
        $sourceBoost = $this->calculateSourceBoost($result);

        return [
            'result' => $result,
            'final_score' => $result->score * $recencyBoost * $sourceBoost,
        ];
    })
    ->sortByDesc('final_score')
    ->take(5)
    ->pluck('result');
```

### Deduplication

Remove near-duplicate content:

```php
$results = $store->search($queryVector, 20);

$unique = collect($results)
    ->unique(fn ($r) => md5($r->getContent()))
    ->values();
```

### Grouping by Source

Limit results per source for diversity:

```php
$results = $store->search($queryVector, 20);

$grouped = collect($results)
    ->groupBy(fn ($r) => $r->getMeta('source'))
    ->map(fn ($group) => $group->take(2)) // Max 2 per source
    ->flatten(1);
```

## Performance Optimization

### Caching Search Results

```php
use Illuminate\Support\Facades\Cache;

$cacheKey = 'search:' . md5(json_encode($queryVector) . json_encode($filter));

$results = Cache::remember($cacheKey, 3600, function () use ($store, $queryVector, $filter) {
    return $store->search($queryVector, 10, $filter);
});
```

### Pre-Computing Query Embeddings

For common queries, cache the embeddings:

```php
$queryCacheKey = 'embedding:' . md5($queryText);

$queryVector = Cache::remember($queryCacheKey, 86400, function () use ($embeddings, $queryText) {
    return $embeddings->embed($queryText);
});

$results = $store->search($queryVector, 10);
```

### Batch Processing Multiple Queries

```php
$queries = ['query 1', 'query 2', 'query 3'];

// Batch embed all queries
$vectors = $embeddings->embedBatch($queries);

// Search for each
$allResults = [];
foreach ($vectors as $i => $vector) {
    $allResults[$queries[$i]] = $store->search($vector, 5);
}
```

## Error Handling

```php
use RuntimeException;

try {
    $results = $store->search($queryVector, 10);
} catch (RuntimeException $e) {
    // Log the error
    logger()->error('Search failed', [
        'error' => $e->getMessage(),
    ]);

    // Return empty results or use fallback
    $results = [];
}
```

## Best Practices

1. **Tune result count** - Start with 3-5, increase if needed for better coverage
2. **Use filters** - Narrow search scope for faster, more relevant results
3. **Set score thresholds** - Filter out low-confidence matches with `isAboveThreshold()`
4. **Cache appropriately** - Cache embeddings and popular query results
5. **Handle empty results** - Provide graceful fallbacks when no matches found
6. **Monitor latency** - Track search performance in production
7. **Test with real queries** - Validate search quality with representative examples
8. **Consider reranking** - Apply business logic to refine result ordering
