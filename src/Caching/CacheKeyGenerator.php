<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Caching;

/**
 * Cache Key Generator - Generates consistent cache keys for various data types.
 */
class CacheKeyGenerator
{
    /**
     * Global key prefix.
     */
    protected string $prefix = 'agent_orchestrator';

    /**
     * Hash algorithm to use.
     */
    protected string $hashAlgorithm = 'xxh128';

    /**
     * Create a new cache key generator.
     */
    public function __construct(?string $prefix = null)
    {
        if ($prefix !== null) {
            $this->prefix = $prefix;
        }
    }

    /**
     * Generate a key for an agent response.
     *
     * @param  array<string, mixed>  $context
     */
    public function forResponse(
        string $agentName,
        string $input,
        array $context = [],
        ?string $model = null,
    ): string {
        $data = [
            'agent' => $agentName,
            'input' => $input,
            'context' => $this->normalizeContext($context),
            'model' => $model,
        ];

        return $this->generate('response', $data);
    }

    /**
     * Generate a key for an embedding.
     */
    public function forEmbedding(
        string $text,
        ?string $model = null,
        ?int $dimensions = null,
    ): string {
        $data = [
            'text' => $text,
            'model' => $model,
            'dimensions' => $dimensions,
        ];

        return $this->generate('embedding', $data);
    }

    /**
     * Generate a key for a tool result.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function forToolResult(
        string $toolName,
        array $arguments,
        ?int $teamId = null,
    ): string {
        $data = [
            'tool' => $toolName,
            'arguments' => $this->normalizeArguments($arguments),
            'team_id' => $teamId,
        ];

        return $this->generate('tool', $data);
    }

    /**
     * Generate a key for a vector search result.
     *
     * @param  array<float>  $vector
     */
    public function forVectorSearch(
        array $vector,
        string $namespace,
        int $limit = 10,
        ?float $minScore = null,
    ): string {
        // For vectors, we use a hash of the first few and last few elements
        // along with the count to create a stable key
        $vectorFingerprint = $this->vectorFingerprint($vector);

        $data = [
            'vector_fingerprint' => $vectorFingerprint,
            'namespace' => $namespace,
            'limit' => $limit,
            'min_score' => $minScore,
        ];

        return $this->generate('vector_search', $data);
    }

    /**
     * Generate a key for a conversation.
     *
     * @param  array<array<string, mixed>>  $messages
     */
    public function forConversation(
        string $agentName,
        array $messages,
        ?int $teamId = null,
    ): string {
        $data = [
            'agent' => $agentName,
            'messages_hash' => $this->hashMessages($messages),
            'team_id' => $teamId,
        ];

        return $this->generate('conversation', $data);
    }

    /**
     * Generate a custom key.
     *
     * @param  array<string, mixed>  $data
     */
    public function custom(string $namespace, array $data): string
    {
        return $this->generate($namespace, $data);
    }

    /**
     * Generate a key with raw parts.
     */
    public function raw(string ...$parts): string
    {
        return $this->prefix.':'.implode(':', $parts);
    }

    /**
     * Generate a key from data.
     *
     * @param  array<string, mixed>  $data
     */
    protected function generate(string $namespace, array $data): string
    {
        $serialized = json_encode($data, JSON_THROW_ON_ERROR);
        $hash = hash($this->hashAlgorithm, $serialized);

        return "{$this->prefix}:{$namespace}:{$hash}";
    }

    /**
     * Normalize context for consistent hashing.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function normalizeContext(array $context): array
    {
        // Remove volatile keys that shouldn't affect caching
        $volatileKeys = ['timestamp', 'request_id', 'session_id'];

        foreach ($volatileKeys as $key) {
            unset($context[$key]);
        }

        // Sort by keys for consistent ordering
        ksort($context);

        return $context;
    }

    /**
     * Normalize arguments for consistent hashing.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function normalizeArguments(array $arguments): array
    {
        // Sort by keys for consistent ordering
        ksort($arguments);

        // Recursively sort nested arrays
        foreach ($arguments as $key => $value) {
            if (is_array($value)) {
                if ($this->isAssociative($value)) {
                    ksort($arguments[$key]);
                }
            }
        }

        return $arguments;
    }

    /**
     * Check if array is associative.
     *
     * @param  array<mixed>  $array
     */
    protected function isAssociative(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Create a fingerprint for a vector.
     *
     * @param  array<float>  $vector
     */
    protected function vectorFingerprint(array $vector): string
    {
        $count = count($vector);

        if ($count === 0) {
            return 'empty';
        }

        // Take first 3, middle 3, and last 3 elements
        $sample = [];

        // First 3
        for ($i = 0; $i < min(3, $count); $i++) {
            $sample[] = round($vector[$i], 6);
        }

        // Middle 3
        $mid = (int) ($count / 2);
        for ($i = max(0, $mid - 1); $i < min($count, $mid + 2); $i++) {
            $sample[] = round($vector[$i], 6);
        }

        // Last 3
        for ($i = max(0, $count - 3); $i < $count; $i++) {
            $sample[] = round($vector[$i], 6);
        }

        return hash('xxh64', $count.':'.implode(',', array_unique($sample)));
    }

    /**
     * Hash an array of messages.
     *
     * @param  array<array<string, mixed>>  $messages
     */
    protected function hashMessages(array $messages): string
    {
        // Only hash the content and role, not metadata
        $simplified = array_map(function ($message) {
            return [
                'role' => $message['role'] ?? '',
                'content' => $message['content'] ?? '',
            ];
        }, $messages);

        return hash('xxh64', json_encode($simplified));
    }

    /**
     * Set the prefix.
     */
    public function setPrefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Get the prefix.
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
}
