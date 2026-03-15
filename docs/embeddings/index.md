# Embeddings

The Agent Orchestrator package provides a comprehensive embeddings system for semantic search, similarity matching, and Retrieval-Augmented Generation (RAG) patterns. This system converts text into high-dimensional vector representations that capture semantic meaning, enabling intelligent document retrieval and knowledge management.

## Overview

Embeddings are numerical representations of text that capture semantic meaning in a vector space. Similar concepts are positioned close together, allowing mathematical operations to find related content. This capability powers several key features:

- **Semantic Search**: Find documents by meaning rather than keyword matching
- **Memory Retrieval**: Retrieve contextually relevant agent memories
- **RAG Patterns**: Augment LLM responses with retrieved knowledge
- **Similarity Matching**: Compare documents, detect duplicates, and cluster content

## Architecture

The embeddings system consists of three primary components:

```
+------------------------------------------------------------------+
|                     Your Application                              |
+------------------------------------------------------------------+
|                                                                   |
|  +-------------+    +-----------------+    +------------------+   |
|  | Text Input  |--->| Embedding       |--->| Vector           |   |
|  |             |    | Provider        |    | Store            |   |
|  +-------------+    | (OpenAI, etc.)  |    | (Qdrant, etc.)   |   |
|                     +-----------------+    +------------------+   |
|                                                                   |
|  +-------------+    +-----------------+    +------------------+   |
|  | Query       |--->| Embedding       |--->| Similarity       |   |
|  |             |    | Provider        |    | Search           |   |
|  +-------------+    +-----------------+    +------------------+   |
|                                                                   |
+------------------------------------------------------------------+
```

### Embedding Providers

Embedding providers convert text into vector representations. The package currently supports OpenAI embeddings with a standardized interface for adding additional providers.

| Provider | Models | Dimensions | Max Tokens |
|----------|--------|------------|------------|
| OpenAI | text-embedding-ada-002 | 1536 | 8191 |
| OpenAI | text-embedding-3-small | 1536 (configurable) | 8191 |
| OpenAI | text-embedding-3-large | 3072 (configurable) | 8191 |

### Vector Stores

Vector stores persist embeddings and provide similarity search capabilities. Each store is optimized for different use cases:

| Store | Type | Best For | Scaling |
|-------|------|----------|---------|
| ArrayVectorStore | In-memory | Testing, development | Small datasets |
| PgVectorStore | PostgreSQL | Production, existing Postgres | Moderate scale |
| QdrantVectorStore | Dedicated | High performance, filtering | Large scale |
| WeaviateVectorStore | Dedicated | Multi-modal, GraphQL | Large scale |
| ChromaVectorStore | Dedicated | Development, prototyping | Moderate scale |

## Quick Start

### Basic Embedding Generation

```php
use AgenticOrchestrator\Embeddings\Providers\OpenAIEmbeddings;

// Create an embedding provider
$embeddings = new OpenAIEmbeddings(
    apiKey: config('services.openai.api_key'),
    model: 'text-embedding-3-small',
);

// Generate embedding for a single text
$vector = $embeddings->embed('What is machine learning?');

// Generate embeddings for multiple texts (more efficient)
$vectors = $embeddings->embedBatch([
    'What is machine learning?',
    'How do neural networks work?',
    'Explain deep learning concepts.',
]);
```

### Storing and Searching Documents

```php
use AgenticOrchestrator\Embeddings\Providers\OpenAIEmbeddings;
use AgenticOrchestrator\Embeddings\Stores\QdrantVectorStore;
use AgenticOrchestrator\Embeddings\VectorDocument;

// Initialize components
$embeddings = new OpenAIEmbeddings(
    apiKey: config('services.openai.api_key'),
);

$store = new QdrantVectorStore([
    'host' => 'http://localhost:6333',
    'collection' => 'knowledge_base',
]);

// Store a document
$content = 'Machine learning is a subset of artificial intelligence...';
$vector = $embeddings->embed($content);

$store->upsert(
    id: 'doc-001',
    embedding: $vector,
    content: $content,
    metadata: [
        'source' => 'textbook',
        'chapter' => 'introduction',
    ],
);

// Search for similar documents
$query = 'What is ML?';
$queryVector = $embeddings->embed($query);

$results = $store->search(
    embedding: $queryVector,
    limit: 5,
    filter: ['source' => 'textbook'],
);

foreach ($results as $result) {
    echo "Score: {$result->score}\n";
    echo "Content: {$result->getContent()}\n\n";
}
```

## RAG Implementation Pattern

Retrieval-Augmented Generation combines vector search with LLM generation for grounded, knowledge-based responses.

```php
use AgenticOrchestrator\Embeddings\Providers\OpenAIEmbeddings;
use AgenticOrchestrator\Embeddings\Stores\PgVectorStore;

class RAGService
{
    public function __construct(
        private OpenAIEmbeddings $embeddings,
        private PgVectorStore $store,
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

    /**
     * Index a document for retrieval.
     */
    public function indexDocument(
        string $id,
        string $content,
        array $metadata = [],
    ): void {
        $vector = $this->embeddings->embed($content);

        $this->store->upsert(
            id: $id,
            embedding: $vector,
            content: $content,
            metadata: $metadata,
        );
    }
}
```

### Using RAG with an Agent

```php
use AgenticOrchestrator\Agent;

// In your agent configuration, use retrieved context in the system prompt
$ragService = app(RAGService::class);

$userQuery = 'What are the key features of our product?';
$context = $ragService->retrieveContext($userQuery);
$contextString = $ragService->buildContextString($context);

$agent = Agent::create()
    ->withProvider('openai')
    ->withModel('gpt-4o')
    ->withSystemPrompt(
        "You are a helpful assistant. Use the following context to answer questions.\n\n" .
        $contextString
    );

$response = $agent->chat($userQuery);
```

## Document Chunking Strategies

For large documents, split content into smaller chunks before embedding. Common strategies include:

### Fixed-Size Chunking

```php
function chunkBySize(string $text, int $chunkSize = 500, int $overlap = 50): array
{
    $chunks = [];
    $position = 0;
    $length = strlen($text);

    while ($position < $length) {
        $chunk = substr($text, $position, $chunkSize);
        $chunks[] = $chunk;
        $position += $chunkSize - $overlap;
    }

    return $chunks;
}
```

### Sentence-Based Chunking

```php
function chunkBySentences(string $text, int $maxSentences = 5): array
{
    // Split by sentence boundaries
    $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

    $chunks = [];
    $current = [];

    foreach ($sentences as $sentence) {
        $current[] = $sentence;

        if (count($current) >= $maxSentences) {
            $chunks[] = implode(' ', $current);
            $current = [];
        }
    }

    if (!empty($current)) {
        $chunks[] = implode(' ', $current);
    }

    return $chunks;
}
```

### Indexing Chunked Documents

```php
$document = file_get_contents('large_document.txt');
$chunks = chunkBySentences($document, maxSentences: 5);

$documents = [];
foreach ($chunks as $index => $chunk) {
    $documents[] = new VectorDocument(
        id: "doc-001-chunk-{$index}",
        content: $chunk,
        embedding: $embeddings->embed($chunk),
        metadata: [
            'document_id' => 'doc-001',
            'chunk_index' => $index,
            'total_chunks' => count($chunks),
        ],
    );
}

$store->upsertBatch($documents);
```

## Performance Considerations

### Batch Operations

Always use batch methods when processing multiple documents:

```php
// Efficient: Single API call for multiple texts
$vectors = $embeddings->embedBatch($texts);

// Inefficient: Multiple API calls
foreach ($texts as $text) {
    $vectors[] = $embeddings->embed($text);
}
```

### Caching Embeddings

The OpenAI provider includes built-in caching:

```php
$embeddings = new OpenAIEmbeddings(
    apiKey: $apiKey,
    model: 'text-embedding-3-small',
    cacheTtl: 86400 * 7, // Cache for 7 days
);
```

### Dimension Reduction

For OpenAI's text-embedding-3 models, you can reduce dimensions to save storage while maintaining quality:

```php
$embeddings = new OpenAIEmbeddings(
    apiKey: $apiKey,
    model: 'text-embedding-3-large',
    dimensions: 1024, // Reduced from 3072
);
```

## Related Documentation

- [Embedding Providers](providers.md) - Detailed provider configuration
- [Vector Stores](vector-stores.md) - Store setup and optimization
- [VectorDocument](documents.md) - Document structure and metadata
- [Semantic Search](searching.md) - Advanced search techniques
