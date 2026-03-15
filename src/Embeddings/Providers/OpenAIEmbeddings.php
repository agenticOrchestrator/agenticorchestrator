<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Embeddings\Providers;

use AgenticOrchestrator\Embeddings\Contracts\EmbeddingProviderInterface;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * OpenAI Embeddings Provider - Uses OpenAI's embedding models.
 *
 * Supports text-embedding-ada-002 and text-embedding-3-* models.
 */
class OpenAIEmbeddings implements EmbeddingProviderInterface
{
    /**
     * Model dimensions.
     */
    protected const MODEL_DIMENSIONS = [
        'text-embedding-ada-002' => 1536,
        'text-embedding-3-small' => 1536,
        'text-embedding-3-large' => 3072,
    ];

    /**
     * Maximum input tokens per model.
     */
    protected const MAX_TOKENS = [
        'text-embedding-ada-002' => 8191,
        'text-embedding-3-small' => 8191,
        'text-embedding-3-large' => 8191,
    ];

    /**
     * HTTP client.
     */
    protected HttpClient $http;

    /**
     * Cache TTL in seconds.
     */
    protected int $cacheTtl;

    /**
     * Create a new OpenAI embeddings provider.
     *
     * @param  string  $apiKey  OpenAI API key
     * @param  string  $model  Embedding model to use
     * @param  int|null  $dimensions  Custom dimension (for text-embedding-3-* models)
     * @param  int  $cacheTtl  Cache TTL in seconds (0 to disable)
     */
    public function __construct(
        protected string $apiKey,
        protected string $model = 'text-embedding-3-small',
        protected ?int $dimensions = null,
        int $cacheTtl = 86400,
    ) {
        $this->http = new HttpClient;
        $this->cacheTtl = $cacheTtl;
    }

    /**
     * Create from config.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): static
    {
        return new static(
            apiKey: $config['api_key'] ?? config('services.openai.api_key', ''),
            model: $config['model'] ?? 'text-embedding-3-small',
            dimensions: $config['dimensions'] ?? null,
            cacheTtl: $config['cache_ttl'] ?? 86400,
        );
    }

    /**
     * Generate embeddings for a single text.
     *
     * @return array<float>
     */
    public function embed(string $text): array
    {
        // Check cache
        $cacheKey = $this->getCacheKey($text);

        if ($this->cacheTtl > 0 && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $result = $this->embedBatch([$text]);
        $embedding = $result[0] ?? [];

        // Cache result
        if ($this->cacheTtl > 0 && ! empty($embedding)) {
            Cache::put($cacheKey, $embedding, $this->cacheTtl);
        }

        return $embedding;
    }

    /**
     * Generate embeddings for multiple texts.
     *
     * @param  array<string>  $texts
     * @return array<int, array<float>>
     */
    public function embedBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $payload = [
            'model' => $this->model,
            'input' => $texts,
        ];

        // Add dimensions for v3 models
        if ($this->dimensions !== null && str_contains($this->model, 'text-embedding-3')) {
            $payload['dimensions'] = $this->dimensions;
        }

        $response = $this->http
            ->withToken($this->apiKey)
            ->post('https://api.openai.com/v1/embeddings', $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'OpenAI embedding request failed: '.$response->body()
            );
        }

        $data = $response->json();

        // Extract embeddings, sorted by index
        $embeddings = [];
        foreach ($data['data'] ?? [] as $item) {
            $embeddings[$item['index']] = $item['embedding'];
        }

        // Sort by index and return values
        ksort($embeddings);

        return array_values($embeddings);
    }

    /**
     * Get the embedding dimension.
     */
    public function getDimension(): int
    {
        if ($this->dimensions !== null) {
            return $this->dimensions;
        }

        return self::MODEL_DIMENSIONS[$this->model] ?? 1536;
    }

    /**
     * Get the model name.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get maximum input length.
     */
    public function getMaxInputLength(): int
    {
        return self::MAX_TOKENS[$this->model] ?? 8191;
    }

    /**
     * Get cache key for text.
     */
    protected function getCacheKey(string $text): string
    {
        $dimension = $this->dimensions ?? 'default';

        return sprintf(
            'embeddings:%s:%s:%s',
            $this->model,
            $dimension,
            md5($text)
        );
    }

    /**
     * Clear cache for a text.
     */
    public function clearCache(string $text): void
    {
        Cache::forget($this->getCacheKey($text));
    }
}
