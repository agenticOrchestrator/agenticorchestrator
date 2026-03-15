# VectorDocument

The `VectorDocument` class represents a document with its embedding vector for storage and retrieval in vector stores.

## Class Overview

```php
namespace AgenticOrchestrator\Embeddings;

class VectorDocument implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $content,
        public readonly array $embedding = [],
        public readonly array $metadata = [],
    ) {}
}
```

All properties are readonly, making `VectorDocument` immutable. To modify a document, use the `with*` methods that return new instances.

## Creating Documents

### Basic Document

```php
use AgenticOrchestrator\Embeddings\VectorDocument;

$document = new VectorDocument(
    id: 'doc-123',
    content: 'This is the document content to be embedded.',
);
```

### With Metadata

```php
$document = new VectorDocument(
    id: 'doc-456',
    content: 'Laravel is a PHP web application framework.',
    metadata: [
        'source' => 'documentation',
        'category' => 'frameworks',
        'language' => 'php',
        'created_at' => now()->toIso8601String(),
    ],
);
```

### With Embedding

```php
$document = new VectorDocument(
    id: 'doc-789',
    content: 'Document content',
    embedding: $vectorArray, // array<float> from embedding provider
    metadata: ['type' => 'article'],
);
```

## Document Properties

### Accessing Properties

Since all properties are readonly and public, you can access them directly:

```php
// Direct property access
$id = $document->id;
$content = $document->content;
$embedding = $document->embedding;
$metadata = $document->metadata;
```

### Metadata Access

```php
// Get specific metadata value
$source = $document->getMeta('source');

// Get with default fallback
$category = $document->getMeta('category', 'uncategorized');

// Check if metadata key exists
if ($document->hasMeta('source')) {
    // ...
}
```

### Embedding Information

```php
// Get embedding dimension
$dimension = $document->getDimension();

// Check if document has an embedding
if ($document->hasEmbedding()) {
    // ...
}
```

## Immutable Updates

To modify a document, use methods that return new instances:

### Adding/Updating Embedding

```php
// Create a new document with the embedding
$embeddedDocument = $document->withEmbedding($vectorArray);

// Original document is unchanged
echo $document->hasEmbedding(); // false
echo $embeddedDocument->hasEmbedding(); // true
```

### Adding/Merging Metadata

```php
// Create a new document with additional metadata
$enrichedDocument = $document->withMetadata([
    'processed' => true,
    'version' => 2,
]);

// Metadata is merged with existing
// Original: ['source' => 'docs']
// After: ['source' => 'docs', 'processed' => true, 'version' => 2]
```

## Factory Method

### From Array

```php
$document = VectorDocument::fromArray([
    'id' => 'doc-123',
    'content' => 'Document content',
    'embedding' => [0.1, 0.2, 0.3, ...],
    'metadata' => ['key' => 'value'],
]);
```

## Serialization

### To Array

```php
$array = $document->toArray();

// Returns:
[
    'id' => 'doc-123',
    'content' => 'Document content',
    'embedding' => [0.1, 0.2, ...],
    'metadata' => ['key' => 'value'],
]
```

### To JSON

```php
// VectorDocument implements JsonSerializable
$json = json_encode($document);
```

## VectorSearchResult

Search operations return `VectorSearchResult` objects that wrap a `VectorDocument` with similarity score:

```php
namespace AgenticOrchestrator\Embeddings;

class VectorSearchResult implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly VectorDocument $document,
        public readonly float $score,
        public readonly ?float $distance = null,
    ) {}
}
```

### Working with Search Results

```php
$results = $store->search($queryEmbedding, limit: 5);

foreach ($results as $result) {
    // Access the document
    $document = $result->document;

    // Convenience accessors
    $id = $result->getId();
    $content = $result->getContent();
    $metadata = $result->getMetadata();
    $source = $result->getMeta('source');

    // Similarity score (higher = more similar)
    $score = $result->score;

    // Distance (lower = closer, may be null)
    $distance = $result->distance;

    // Check against threshold
    if ($result->isAboveThreshold(0.7)) {
        // High confidence match
    }
}
```

### Serialization

```php
$array = $result->toArray();

// Returns:
[
    'document' => [
        'id' => 'doc-123',
        'content' => '...',
        'embedding' => [...],
        'metadata' => [...],
    ],
    'score' => 0.85,
    'distance' => 0.15,
]
```

## Complete Example

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

// Create and embed a document
$content = 'Machine learning is a subset of artificial intelligence...';
$vector = $embeddings->embed($content);

$document = new VectorDocument(
    id: 'ml-intro-001',
    content: $content,
    embedding: $vector,
    metadata: [
        'source' => 'textbook',
        'chapter' => 'introduction',
        'topic' => 'machine-learning',
    ],
);

// Store using the upsert API
$store->upsert(
    id: $document->id,
    embedding: $document->embedding,
    content: $document->content,
    metadata: $document->metadata,
);

// Or batch store multiple documents
$documents = [
    new VectorDocument('doc-1', 'Content 1', $vector1, ['type' => 'a']),
    new VectorDocument('doc-2', 'Content 2', $vector2, ['type' => 'b']),
];
$store->upsertBatch($documents);
```

## Best Practices

1. **Use meaningful IDs** - Enable updates and deletions with consistent identifiers
2. **Add rich metadata** - Include source, category, timestamps for filtering and context
3. **Immutable by design** - Use `with*` methods to create modified copies
4. **Batch operations** - Use `upsertBatch()` for efficient bulk storage
5. **Track provenance** - Store enough metadata to trace back to original sources
6. **Consistent embedding dimensions** - Ensure all documents in a store use the same embedding model
