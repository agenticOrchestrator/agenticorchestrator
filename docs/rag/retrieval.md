# Retrieval and Reranking

Retrievers search the vector store for relevant documents, while rerankers post-process results to improve quality.

## Retrievers

### VectorRetriever (Default)

Uses embedding similarity to find semantically related documents.

```php
use AgenticOrchestrator\Rag\Retrievers\VectorRetriever;

$retriever = new VectorRetriever($embeddings, $store);
$retriever->setThreshold(0.7);

$results = $retriever->retrieve(
    query: 'How do I reset my password?',
    limit: 5,
    filter: ['namespace' => 'faq'],
);
```

#### How It Works

1. Query is converted to an embedding vector
2. Vector store performs similarity search
3. Results are filtered by threshold
4. Top results are returned

#### Configuration

```php
$retriever = (new VectorRetriever($embeddings, $store))
    ->setThreshold(0.75);  // Minimum similarity score (0-1)
```

### HybridRetriever

Combines vector similarity with keyword matching for improved results, especially when specific terms are important.

```php
use AgenticOrchestrator\Rag\Retrievers\HybridRetriever;

$retriever = new HybridRetriever($embeddings, $store);
```

#### How It Works

1. Vector similarity search (default 70% weight)
2. Keyword extraction and matching (default 30% weight)
3. Combined scoring
4. Results sorted by combined score

#### Configuration

```php
$retriever = (new HybridRetriever($embeddings, $store))
    ->setThreshold(0.7)
    ->setVectorWeight(0.7)    // 70% vector similarity
    ->setKeywordWeight(0.3);  // 30% keyword matching
```

#### When to Use Hybrid

- Technical documentation with specific terms
- Code-related queries
- Queries containing product names, error codes
- Mixed natural language and technical content

### Using with Pipeline

```php
// Default: VectorRetriever
$pipeline = RagPipeline::make()
    ->embeddings($embeddings)
    ->store($store);

// Custom retriever
$pipeline->retriever(new HybridRetriever($embeddings, $store));
```

## Rerankers

Rerankers post-process search results to improve quality.

### ScoreThresholdReranker

Filters and limits results based on similarity scores.

```php
use AgenticOrchestrator\Rag\Rerankers\ScoreThresholdReranker;

$reranker = new ScoreThresholdReranker(
    threshold: 0.7,
    maxResults: 5,
);
```

#### Configuration

```php
$reranker = (new ScoreThresholdReranker())
    ->setThreshold(0.75)      // Minimum score to include
    ->setMaxResults(10)       // Maximum results to return
    ->setMinScoreGap(0.15);   // Stop when score drops significantly
```

#### Score Gap Detection

The `minScoreGap` option detects quality drop-offs in results:

```php
// Results: [0.95, 0.92, 0.90, 0.65, 0.60]
// With minScoreGap: 0.15
// Output: [0.95, 0.92, 0.90] (stops at 0.90 -> 0.65 gap)

$reranker->setMinScoreGap(0.15);
```

### Using with Pipeline

```php
$pipeline->reranker(new ScoreThresholdReranker(0.8, 5));
```

## Search Results

### VectorSearchResult

Search results contain the document and scoring information:

```php
$results = $pipeline->query('user question')->getResults();

foreach ($results as $result) {
    // Document access
    $result->getId();          // Document ID
    $result->getContent();     // Document content
    $result->getMetadata();    // All metadata
    $result->getMeta('key');   // Specific metadata

    // Scoring
    $result->score;            // Similarity score (0-1)
    $result->distance;         // Distance metric (if available)

    // Threshold check
    if ($result->isAboveThreshold(0.8)) {
        // High quality result
    }
}
```

### Processing Results

```php
$queryResult = $pipeline->query('question');

// Check for results
if (!$queryResult->hasContext()) {
    return "No relevant information found.";
}

// Get formatted context
$context = $queryResult->getContext();

// Get results above threshold
$highQuality = $queryResult->getResultsAboveThreshold(0.85);

// Get statistics
$avgScore = $queryResult->getAverageScore();
$sources = $queryResult->getSources();
```

## Filtering

### Metadata Filtering

Filter results based on document metadata:

```php
// During retrieval
$results = $retriever->retrieve(
    query: 'password reset',
    limit: 10,
    filter: [
        'namespace' => 'support_docs',
        'category' => 'authentication',
        'language' => 'en',
    ],
);
```

### With Pipeline

```php
// Namespace filtering is automatic
$pipeline->namespace('support_docs')
    ->forTenant($tenantId)
    ->query('password reset');
// Automatically filters by namespace and tenant
```

### Custom Filters

```php
// Delete with filter
$pipeline->delete([
    'category' => 'outdated',
    'created_before' => '2024-01-01',
]);
```

## Implementing Custom Retrievers

Create specialized retrievers for specific use cases:

```php
use AgenticOrchestrator\Rag\Contracts\RetrieverInterface;
use AgenticOrchestrator\Embeddings\VectorSearchResult;

class MultiStageRetriever implements RetrieverInterface
{
    protected float $threshold = 0.7;

    public function __construct(
        protected $embeddings,
        protected $store,
    ) {}

    public function retrieve(string $query, int $limit = 5, array $filter = []): array
    {
        // Stage 1: Broad search
        $embedding = $this->embeddings->embed($query);
        $candidates = $this->store->search($embedding, $limit * 3, $filter);

        // Stage 2: Keyword boost
        $keywords = $this->extractKeywords($query);
        foreach ($candidates as &$result) {
            $boost = $this->calculateKeywordBoost($result->getContent(), $keywords);
            // Adjust score (simplified - actual implementation would create new result)
        }

        // Stage 3: Filter and limit
        $filtered = array_filter(
            $candidates,
            fn($r) => $r->score >= $this->threshold
        );

        usort($filtered, fn($a, $b) => $b->score <=> $a->score);

        return array_slice($filtered, 0, $limit);
    }

    public function setThreshold(float $threshold): static
    {
        $this->threshold = $threshold;
        return $this;
    }

    public function getThreshold(): float
    {
        return $this->threshold;
    }

    // ... helper methods
}
```

## Implementing Custom Rerankers

Create rerankers for specific ranking strategies:

```php
use AgenticOrchestrator\Rag\Contracts\RerankerInterface;

class DiversityReranker implements RerankerInterface
{
    public function rerank(array $results, string $query): array
    {
        if (count($results) <= 1) {
            return $results;
        }

        // Select diverse results
        $selected = [$results[0]];
        $remaining = array_slice($results, 1);

        while (count($selected) < count($results) && !empty($remaining)) {
            $mostDiverse = $this->findMostDiverse($remaining, $selected);
            if ($mostDiverse !== null) {
                $selected[] = $mostDiverse;
                $remaining = array_filter(
                    $remaining,
                    fn($r) => $r->getId() !== $mostDiverse->getId()
                );
            } else {
                break;
            }
        }

        return $selected;
    }

    private function findMostDiverse(array $candidates, array $selected): ?VectorSearchResult
    {
        // Find candidate most different from already selected
        // Implementation depends on your similarity metric
    }
}
```

## Performance Optimization

### Caching Embeddings

Query embeddings can be cached to reduce API calls:

```php
$cacheKey = 'query_embedding:' . md5($query);
$embedding = Cache::remember($cacheKey, 3600, function() use ($query, $embeddings) {
    return $embeddings->embed($query);
});
```

### Batch Queries

For multiple queries, batch embedding calls:

```php
$queries = ['question 1', 'question 2', 'question 3'];
$embeddings = $embeddingProvider->embedBatch($queries);

// Search with each embedding
foreach ($embeddings as $i => $embedding) {
    $results[$i] = $store->search($embedding, $limit, $filter);
}
```

### Pre-filtering

Use metadata filters to reduce search space:

```php
// More efficient: filter first
$results = $retriever->retrieve($query, 10, [
    'namespace' => 'specific_namespace',
    'category' => 'relevant_category',
]);

// Less efficient: search everything, filter after
$results = $retriever->retrieve($query, 100);
$filtered = array_filter($results, fn($r) => $r->getMeta('category') === 'relevant');
```

## Best Practices

1. **Start with VectorRetriever**: It works well for most semantic search use cases.

2. **Use HybridRetriever for Technical Content**: When queries contain specific terms, codes, or names.

3. **Set Appropriate Thresholds**: Start with 0.7 and adjust based on result quality.

4. **Use Score Gap Detection**: Helps identify natural quality boundaries in results.

5. **Filter Early**: Use metadata filters to reduce search space before similarity computation.

6. **Monitor Result Quality**: Log scores and review low-scoring matches to tune settings.

7. **Consider Multi-Stage Retrieval**: For complex queries, use multiple retrieval stages with different strategies.
