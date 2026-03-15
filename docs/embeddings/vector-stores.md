# Vector Stores

Vector stores enable semantic search by storing and querying document embeddings. All stores implement the `VectorStoreInterface` and provide a consistent API for document storage and similarity search.

## Supported Stores

| Store | Type | Best For | Features |
|-------|------|----------|----------|
| ArrayVectorStore | In-memory | Testing, development | No setup, pure PHP |
| PgVectorStore | PostgreSQL | Production, existing Postgres | SQL filtering, transactions |
| QdrantVectorStore | Dedicated | High performance | Fast queries, payload filtering |
| WeaviateVectorStore | Dedicated | Scalable production | GraphQL, schema management |
| ChromaVectorStore | Dedicated | Development, prototyping | Simple setup, auto-collection |

## VectorStoreInterface

All vector stores implement this interface:

```php
namespace AgenticOrchestrator\Embeddings\Contracts;

interface VectorStoreInterface
{
    // Store a document
    public function upsert(
        string $id,
        array $embedding,
        string $content,
        array $metadata = [],
    ): void;

    // Store multiple documents
    public function upsertBatch(array $documents): void;

    // Search for similar documents
    public function search(
        array $embedding,
        int $limit = 10,
        array $filter = [],
    ): array;

    // Delete operations
    public function delete(string $id): bool;
    public function deleteBatch(array $ids): int;
    public function deleteByFilter(array $filter): int;

    // Retrieval operations
    public function get(string $id): ?VectorDocument;
    public function exists(string $id): bool;
    public function count(): int;
    public function clear(): void;
}
```

## Basic Usage

### Storing Documents

```php
use AgenticOrchestrator\Embeddings\Providers\OpenAIEmbeddings;
use AgenticOrchestrator\Embeddings\Stores\QdrantVectorStore;

// Initialize
$embeddings = new OpenAIEmbeddings(
    apiKey: config('services.openai.api_key'),
);

$store = new QdrantVectorStore([
    'host' => 'http://localhost:6333',
    'collection' => 'my-collection',
]);

// Generate embedding and store
$content = 'Laravel is a PHP framework for web applications.';
$vector = $embeddings->embed($content);

$store->upsert(
    id: 'doc-001',
    embedding: $vector,
    content: $content,
    metadata: [
        'source' => 'documentation',
        'category' => 'frameworks',
    ],
);
```

### Batch Storage

```php
use AgenticOrchestrator\Embeddings\VectorDocument;

$documents = [];
$contents = ['Content 1...', 'Content 2...', 'Content 3...'];
$vectors = $embeddings->embedBatch($contents);

foreach ($contents as $i => $content) {
    $documents[] = new VectorDocument(
        id: "doc-{$i}",
        content: $content,
        embedding: $vectors[$i],
        metadata: ['index' => $i],
    );
}

$store->upsertBatch($documents);
```

### Searching

The `search()` method takes an embedding vector (not a text query) and returns `VectorSearchResult` objects:

```php
// Generate query embedding
$queryVector = $embeddings->embed('What is Laravel?');

// Search for similar documents
$results = $store->search(
    embedding: $queryVector,
    limit: 5,
    filter: ['category' => 'frameworks'],
);

foreach ($results as $result) {
    echo "ID: {$result->getId()}\n";
    echo "Score: {$result->score}\n";
    echo "Content: {$result->getContent()}\n";
    echo "Source: {$result->getMeta('source')}\n\n";
}
```

## ArrayVectorStore

In-memory store for testing and development. No external dependencies.

### Constructor

```php
use AgenticOrchestrator\Embeddings\Stores\ArrayVectorStore;

$store = new ArrayVectorStore(
    distanceMetric: 'cosine', // 'cosine', 'euclidean', or 'dot'
);
```

### Features

- Pure PHP implementation
- Cosine, Euclidean, and dot product similarity
- Simple array-based filtering
- Access all documents with `all()` method

```php
// For testing: get all stored documents
$allDocs = $store->all();
```

## PgVectorStore

PostgreSQL with the pgvector extension. Good for production when you already use PostgreSQL.

### Prerequisites

```sql
-- Enable pgvector extension
CREATE EXTENSION IF NOT EXISTS vector;
```

### Constructor

```php
use AgenticOrchestrator\Embeddings\Stores\PgVectorStore;

$store = new PgVectorStore(
    table: 'vector_documents',
    dimension: 1536,
    distanceMetric: 'cosine', // 'cosine', 'euclidean', or 'inner_product'
    connection: null, // Uses default database connection
);

// Or from config array
$store = PgVectorStore::fromConfig([
    'table' => 'vector_documents',
    'dimension' => 1536,
    'distance_metric' => 'cosine',
    'connection' => 'pgsql',
]);
```

### Table Management

```php
// Create table and index
$store->createTable();

// Drop table
$store->dropTable();
```

### Filtering

Filters use JSONB operators on the metadata column:

```php
// Simple equality filter
$results = $store->search($vector, 10, [
    'category' => 'tutorials',
]);

// Array filter (IN clause)
$results = $store->search($vector, 10, [
    'status' => ['published', 'featured'],
]);
```

## QdrantVectorStore

High-performance dedicated vector database.

### Constructor

```php
use AgenticOrchestrator\Embeddings\Stores\QdrantVectorStore;

$store = new QdrantVectorStore([
    'host' => 'http://localhost:6333',
    'api_key' => env('QDRANT_API_KEY'), // Optional
    'collection' => 'documents',
    'timeout' => 30,
]);
```

### Collection Management

```php
// Create collection with vector configuration
$store->createCollection(vectorSize: 1536);
```

### Filtering

```php
$results = $store->search($vector, 10, [
    'category' => 'ai',
    'published' => true,
]);
```

The store builds Qdrant-compatible filters:

```php
// Internally converted to:
[
    'must' => [
        ['key' => 'category', 'match' => ['value' => 'ai']],
        ['key' => 'published', 'match' => ['value' => true]],
    ],
]
```

## WeaviateVectorStore

GraphQL-based vector database with schema management.

### Constructor

```php
use AgenticOrchestrator\Embeddings\Stores\WeaviateVectorStore;

$store = new WeaviateVectorStore([
    'host' => 'http://localhost:8080',
    'api_key' => env('WEAVIATE_API_KEY'), // Optional
    'class_name' => 'Document',
    'timeout' => 30,
]);
```

### Filtering

```php
$results = $store->search($vector, 10, [
    'source' => 'official-docs',
]);
```

For advanced Weaviate filters, pass the complete filter structure:

```php
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

## ChromaVectorStore

Simple vector database with automatic collection management.

### Constructor

```php
use AgenticOrchestrator\Embeddings\Stores\ChromaVectorStore;

$store = new ChromaVectorStore([
    'host' => 'http://localhost:8000',
    'collection' => 'documents',
    'timeout' => 30,
    'tenant' => 'default_tenant',
    'database' => 'default_database',
]);
```

### Features

- Automatic collection creation
- Distance-to-similarity conversion for consistent scoring
- Simple metadata filtering

### Filtering

```php
$results = $store->search($vector, 10, [
    'type' => 'tutorial',
]);
```

## Implementing a Custom Store

Create your own vector store by implementing `VectorStoreInterface`:

```php
use AgenticOrchestrator\Embeddings\Contracts\VectorStoreInterface;
use AgenticOrchestrator\Embeddings\VectorDocument;
use AgenticOrchestrator\Embeddings\VectorSearchResult;

class CustomVectorStore implements VectorStoreInterface
{
    public function upsert(
        string $id,
        array $embedding,
        string $content,
        array $metadata = [],
    ): void {
        // Store document in your backend
    }

    public function upsertBatch(array $documents): void
    {
        foreach ($documents as $doc) {
            $this->upsert(
                $doc->id,
                $doc->embedding,
                $doc->content,
                $doc->metadata,
            );
        }
    }

    public function search(
        array $embedding,
        int $limit = 10,
        array $filter = [],
    ): array {
        // Query your backend and return VectorSearchResult objects
        return [
            new VectorSearchResult(
                document: new VectorDocument(
                    id: 'result-1',
                    content: 'Matching content...',
                    embedding: [...],
                    metadata: [...],
                ),
                score: 0.95,
            ),
        ];
    }

    public function delete(string $id): bool
    {
        // Delete and return success status
    }

    public function deleteBatch(array $ids): int
    {
        // Delete multiple and return count
    }

    public function deleteByFilter(array $filter): int
    {
        // Delete by filter and return count
    }

    public function get(string $id): ?VectorDocument
    {
        // Retrieve single document or null
    }

    public function exists(string $id): bool
    {
        // Check existence
    }

    public function count(): int
    {
        // Return total document count
    }

    public function clear(): void
    {
        // Remove all documents
    }
}
```

## Configuration Reference

### Environment Variables

```env
# Qdrant
QDRANT_HOST=http://localhost:6333
QDRANT_API_KEY=

# Weaviate
WEAVIATE_HOST=http://localhost:8080
WEAVIATE_API_KEY=

# Chroma
CHROMA_HOST=http://localhost:8000

# PostgreSQL (uses Laravel database config)
DB_CONNECTION=pgsql
```

### Config File Example

```php
// config/agent-orchestrator.php
'vector_stores' => [
    'default' => env('VECTOR_STORE', 'qdrant'),

    'stores' => [
        'array' => [
            'driver' => 'array',
            'distance_metric' => 'cosine',
        ],

        'pgvector' => [
            'driver' => 'pgvector',
            'table' => 'vector_documents',
            'dimension' => 1536,
            'distance_metric' => 'cosine',
            'connection' => env('DB_CONNECTION', 'pgsql'),
        ],

        'qdrant' => [
            'driver' => 'qdrant',
            'host' => env('QDRANT_HOST', 'http://localhost:6333'),
            'api_key' => env('QDRANT_API_KEY'),
            'collection' => 'documents',
        ],

        'weaviate' => [
            'driver' => 'weaviate',
            'host' => env('WEAVIATE_HOST', 'http://localhost:8080'),
            'api_key' => env('WEAVIATE_API_KEY'),
            'class_name' => 'Document',
        ],

        'chroma' => [
            'driver' => 'chroma',
            'host' => env('CHROMA_HOST', 'http://localhost:8000'),
            'collection' => 'documents',
        ],
    ],
],
```

## Best Practices

1. **Choose the right store** - Array for testing, PgVector for existing Postgres, Qdrant/Weaviate for dedicated vector search
2. **Use meaningful IDs** - Enable updates and deletions with predictable identifiers
3. **Add rich metadata** - Enable filtering to narrow search scope
4. **Batch operations** - Use `upsertBatch()` for large datasets
5. **Monitor performance** - Track query latencies and optimize indexes
6. **Handle failures** - Implement retry logic for network operations
7. **Consistent dimensions** - Ensure all documents use the same embedding model/dimensions
