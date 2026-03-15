# Embedding Providers

Embedding providers convert text into vector representations that capture semantic meaning. The Agent Orchestrator package provides a standardized interface for embedding generation with built-in support for OpenAI's embedding models.

## Provider Interface

All embedding providers implement the `EmbeddingProviderInterface`:

```php
namespace AgenticOrchestrator\Embeddings\Contracts;

interface EmbeddingProviderInterface
{
    /**
     * Generate embeddings for a single text.
     *
     * @param string $text The text to embed
     * @return array<float> The embedding vector
     */
    public function embed(string $text): array;

    /**
     * Generate embeddings for multiple texts.
     *
     * @param array<string> $texts The texts to embed
     * @return array<int, array<float>> Array of embedding vectors
     */
    public function embedBatch(array $texts): array;

    /**
     * Get the dimension of the embeddings.
     */
    public function getDimension(): int;

    /**
     * Get the model name/identifier.
     */
    public function getModel(): string;

    /**
     * Get the maximum input length (tokens or characters).
     */
    public function getMaxInputLength(): int;
}
```

## OpenAI Embeddings

The `OpenAIEmbeddings` provider supports all OpenAI embedding models including the latest text-embedding-3 family.

### Supported Models

| Model | Dimensions | Max Tokens | Notes |
|-------|------------|------------|-------|
| `text-embedding-ada-002` | 1536 | 8191 | Legacy model, widely deployed |
| `text-embedding-3-small` | 1536 | 8191 | Improved quality, supports dimension reduction |
| `text-embedding-3-large` | 3072 | 8191 | Highest quality, supports dimension reduction |

### Basic Usage

```php
use AgenticOrchestrator\Embeddings\Providers\OpenAIEmbeddings;

$embeddings = new OpenAIEmbeddings(
    apiKey: config('services.openai.api_key'),
    model: 'text-embedding-3-small',
);

// Generate a single embedding
$vector = $embeddings->embed('Your text here');

// Get model information
echo $embeddings->getModel();        // text-embedding-3-small
echo $embeddings->getDimension();    // 1536
echo $embeddings->getMaxInputLength(); // 8191
```

### Constructor Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `apiKey` | string | required | OpenAI API key |
| `model` | string | `'text-embedding-3-small'` | Embedding model to use |
| `dimensions` | int\|null | `null` | Custom dimension for v3 models |
| `cacheTtl` | int | `86400` | Cache TTL in seconds (0 to disable) |

### Creating from Configuration

```php
$embeddings = OpenAIEmbeddings::fromConfig([
    'api_key' => env('OPENAI_API_KEY'),
    'model' => 'text-embedding-3-small',
    'dimensions' => null,
    'cache_ttl' => 86400,
]);
```

### Dimension Reduction

The text-embedding-3 models support dimension reduction, allowing you to reduce storage requirements while maintaining semantic quality:

```php
// Full dimensions (3072)
$embeddings = new OpenAIEmbeddings(
    apiKey: $apiKey,
    model: 'text-embedding-3-large',
);

// Reduced dimensions (1024)
$embeddings = new OpenAIEmbeddings(
    apiKey: $apiKey,
    model: 'text-embedding-3-large',
    dimensions: 1024,
);

// Even smaller (512)
$embeddings = new OpenAIEmbeddings(
    apiKey: $apiKey,
    model: 'text-embedding-3-large',
    dimensions: 512,
);
```

**Dimension Reduction Trade-offs:**

| Dimensions | Storage | Quality | Use Case |
|------------|---------|---------|----------|
| 3072 | Largest | Highest | Critical accuracy requirements |
| 1536 | Medium | Very Good | General production use |
| 1024 | Smaller | Good | Large-scale deployments |
| 512 | Smallest | Acceptable | Cost-sensitive applications |

### Batch Processing

For efficiency, use batch embedding when processing multiple texts:

```php
$texts = [
    'First document content...',
    'Second document content...',
    'Third document content...',
];

// Single API call for all texts
$vectors = $embeddings->embedBatch($texts);

// $vectors is indexed by position
echo count($vectors[0]); // 1536 (dimension count)
```

**Batch Size Recommendations:**

- OpenAI supports up to 2048 texts per batch
- Optimal batch size: 100-500 texts
- Monitor rate limits for high-volume processing

### Caching

The provider includes built-in caching to reduce API calls and costs:

```php
// Enable caching with 7-day TTL
$embeddings = new OpenAIEmbeddings(
    apiKey: $apiKey,
    model: 'text-embedding-3-small',
    cacheTtl: 86400 * 7, // 7 days
);

// Embeddings are automatically cached
$vector1 = $embeddings->embed('Hello world'); // API call
$vector2 = $embeddings->embed('Hello world'); // Cached

// Clear cache for specific text
$embeddings->clearCache('Hello world');
```

Cache keys include the model and dimensions, ensuring different configurations don't share cache entries.

### Error Handling

```php
use AgenticOrchestrator\Embeddings\Providers\OpenAIEmbeddings;
use RuntimeException;

try {
    $vector = $embeddings->embed($text);
} catch (RuntimeException $e) {
    // Handle API errors
    logger()->error('Embedding failed', [
        'error' => $e->getMessage(),
        'text_length' => strlen($text),
    ]);
}
```

## Implementing Custom Providers

To add support for additional embedding providers, implement the `EmbeddingProviderInterface`:

### Voyage AI Example

```php
namespace App\Embeddings\Providers;

use AgenticOrchestrator\Embeddings\Contracts\EmbeddingProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class VoyageEmbeddings implements EmbeddingProviderInterface
{
    protected const DIMENSIONS = [
        'voyage-3' => 1024,
        'voyage-3-lite' => 512,
        'voyage-code-3' => 1024,
    ];

    protected const MAX_TOKENS = [
        'voyage-3' => 32000,
        'voyage-3-lite' => 32000,
        'voyage-code-3' => 32000,
    ];

    public function __construct(
        protected string $apiKey,
        protected string $model = 'voyage-3',
    ) {}

    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0] ?? [];
    }

    public function embedBatch(array $texts): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->post('https://api.voyageai.com/v1/embeddings', [
            'model' => $this->model,
            'input' => $texts,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                'Voyage embedding request failed: ' . $response->body()
            );
        }

        $data = $response->json();
        $embeddings = [];

        foreach ($data['data'] ?? [] as $item) {
            $embeddings[$item['index']] = $item['embedding'];
        }

        ksort($embeddings);
        return array_values($embeddings);
    }

    public function getDimension(): int
    {
        return self::DIMENSIONS[$this->model] ?? 1024;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getMaxInputLength(): int
    {
        return self::MAX_TOKENS[$this->model] ?? 32000;
    }
}
```

### Cohere Example

```php
namespace App\Embeddings\Providers;

use AgenticOrchestrator\Embeddings\Contracts\EmbeddingProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CohereEmbeddings implements EmbeddingProviderInterface
{
    protected const DIMENSIONS = [
        'embed-english-v3.0' => 1024,
        'embed-multilingual-v3.0' => 1024,
        'embed-english-light-v3.0' => 384,
        'embed-multilingual-light-v3.0' => 384,
    ];

    public function __construct(
        protected string $apiKey,
        protected string $model = 'embed-english-v3.0',
        protected string $inputType = 'search_document',
    ) {}

    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0] ?? [];
    }

    public function embedBatch(array $texts): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->post('https://api.cohere.ai/v1/embed', [
            'model' => $this->model,
            'texts' => $texts,
            'input_type' => $this->inputType,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                'Cohere embedding request failed: ' . $response->body()
            );
        }

        $data = $response->json();
        return $data['embeddings'] ?? [];
    }

    public function getDimension(): int
    {
        return self::DIMENSIONS[$this->model] ?? 1024;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getMaxInputLength(): int
    {
        return 512; // Cohere's token limit
    }

    /**
     * Set input type for embedding purpose.
     *
     * @param string $type 'search_document', 'search_query', 'classification', 'clustering'
     */
    public function setInputType(string $type): static
    {
        $this->inputType = $type;
        return $this;
    }
}
```

### Local Model Example (Ollama)

```php
namespace App\Embeddings\Providers;

use AgenticOrchestrator\Embeddings\Contracts\EmbeddingProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaEmbeddings implements EmbeddingProviderInterface
{
    public function __construct(
        protected string $baseUrl = 'http://localhost:11434',
        protected string $model = 'nomic-embed-text',
    ) {}

    public function embed(string $text): array
    {
        $response = Http::post("{$this->baseUrl}/api/embeddings", [
            'model' => $this->model,
            'prompt' => $text,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                'Ollama embedding request failed: ' . $response->body()
            );
        }

        return $response->json()['embedding'] ?? [];
    }

    public function embedBatch(array $texts): array
    {
        // Ollama doesn't support batch, process individually
        return array_map(
            fn ($text) => $this->embed($text),
            $texts
        );
    }

    public function getDimension(): int
    {
        return 768; // Varies by model
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getMaxInputLength(): int
    {
        return 8192; // Varies by model
    }
}
```

## Provider Selection Guidelines

Choose an embedding provider based on your requirements:

| Provider | Best For | Considerations |
|----------|----------|----------------|
| **OpenAI** | General purpose, high quality | Cost per API call |
| **Voyage** | Retrieval optimization | Specialized for RAG |
| **Cohere** | Multilingual support | Input type selection |
| **Ollama** | Local deployment, privacy | Hardware requirements |

### Quality vs Cost Trade-offs

```
High Quality, Higher Cost:
├── OpenAI text-embedding-3-large (3072d)
├── Voyage voyage-3 (1024d)
└── Cohere embed-english-v3.0 (1024d)

Balanced:
├── OpenAI text-embedding-3-small (1536d)
├── OpenAI text-embedding-3-large (1024d reduced)
└── Voyage voyage-3-lite (512d)

Cost Efficient:
├── OpenAI text-embedding-3-small (512d reduced)
├── Cohere embed-english-light-v3.0 (384d)
└── Ollama local models (free, hardware cost)
```

## Related Documentation

- [Vector Stores](vector-stores.md) - Where to store embeddings
- [Configuration](configuration.md) - Environment setup
- [Semantic Search](searching.md) - Using embeddings for search
