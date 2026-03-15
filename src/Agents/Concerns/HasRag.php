<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Agents\Concerns;

use AgenticOrchestrator\Rag\Attributes\RagSource;
use AgenticOrchestrator\Rag\Document;
use AgenticOrchestrator\Rag\RagPipeline;
use AgenticOrchestrator\Rag\RagPipelineResult;
use ReflectionClass;

/**
 * Provides RAG (Retrieval-Augmented Generation) capabilities for agents.
 *
 * Enables agents to automatically retrieve and inject relevant context
 * from vector stores into their prompts based on user queries.
 */
trait HasRag
{
    /**
     * The RAG pipeline instance.
     */
    protected ?RagPipeline $ragPipeline = null;

    /**
     * Cached RAG source attributes.
     *
     * @var array<RagSource>|null
     */
    protected ?array $ragSources = null;

    /**
     * Whether RAG is enabled.
     */
    protected bool $ragEnabled = true;

    /**
     * Set the RAG pipeline.
     */
    public function withRag(RagPipeline $pipeline): static
    {
        $this->ragPipeline = $pipeline;

        return $this;
    }

    /**
     * Get the RAG pipeline.
     */
    public function getRagPipeline(): ?RagPipeline
    {
        return $this->ragPipeline;
    }

    /**
     * Enable RAG.
     */
    public function enableRag(): static
    {
        $this->ragEnabled = true;

        return $this;
    }

    /**
     * Disable RAG.
     */
    public function disableRag(): static
    {
        $this->ragEnabled = false;

        return $this;
    }

    /**
     * Check if RAG is enabled.
     */
    public function isRagEnabled(): bool
    {
        return $this->ragEnabled && ($this->ragPipeline !== null || ! empty($this->getRagSources()));
    }

    /**
     * Retrieve RAG context for a query.
     */
    public function retrieveRagContext(string $query): RagPipelineResult
    {
        if ($this->ragPipeline === null) {
            return new RagPipelineResult;
        }

        return $this->ragPipeline->query($query);
    }

    /**
     * Retrieve RAG context from all configured sources.
     *
     * @return array<string, RagPipelineResult>
     */
    public function retrieveRagContextFromSources(string $query): array
    {
        $results = [];

        foreach ($this->getRagSources() as $source) {
            if (! $source->enabled) {
                continue;
            }

            if ($this->ragPipeline === null) {
                continue;
            }

            // Clone pipeline with source-specific settings
            $pipeline = RagPipeline::make($this->ragPipeline->getConfig())
                ->namespace($source->namespace)
                ->limit($source->limit)
                ->threshold($source->threshold);

            // Apply the same embeddings and store from the main pipeline
            // Note: This assumes the pipeline is configured externally
            $results[$source->namespace] = $pipeline->query($query);
        }

        return $results;
    }

    /**
     * Get formatted RAG context string for injection.
     */
    public function getFormattedRagContext(string $query): string
    {
        if (! $this->isRagEnabled()) {
            return '';
        }

        $result = $this->retrieveRagContext($query);

        if (! $result->hasContext()) {
            return '';
        }

        $sources = $this->getRagSources();
        $template = ! empty($sources)
            ? $sources[0]->getContextTemplate()
            : $this->getDefaultContextTemplate();

        return str_replace('{context}', $result->getContext(), $template);
    }

    /**
     * Get the default context template.
     */
    protected function getDefaultContextTemplate(): string
    {
        return <<<'TEMPLATE'
## Relevant Context

The following information may be helpful in answering the user's question:

{context}

---

TEMPLATE;
    }

    /**
     * Discover RAG source attributes on this class.
     *
     * @return array<RagSource>
     */
    public function getRagSources(): array
    {
        if ($this->ragSources !== null) {
            return $this->ragSources;
        }

        $this->ragSources = [];
        $reflection = new ReflectionClass($this);

        // Check class-level attributes
        foreach ($reflection->getAttributes(RagSource::class) as $attribute) {
            $this->ragSources[] = $attribute->newInstance();
        }

        // Check property-level attributes
        foreach ($reflection->getProperties() as $property) {
            foreach ($property->getAttributes(RagSource::class) as $attribute) {
                $this->ragSources[] = $attribute->newInstance();
            }
        }

        return $this->ragSources;
    }

    /**
     * Check if agent has RAG sources configured.
     */
    public function hasRagSources(): bool
    {
        return ! empty($this->getRagSources());
    }

    /**
     * Ingest documents into the RAG pipeline.
     *
     * @param  array<Document>  $documents
     */
    public function ingestDocuments(array $documents): RagPipelineResult
    {
        if ($this->ragPipeline === null) {
            throw new \RuntimeException('RAG pipeline is not configured');
        }

        return $this->ragPipeline->addDocuments($documents)->ingest();
    }

    /**
     * Ingest content from a path into the RAG pipeline.
     */
    public function ingestFromPath(string $path): RagPipelineResult
    {
        if ($this->ragPipeline === null) {
            throw new \RuntimeException('RAG pipeline is not configured');
        }

        return $this->ragPipeline->from($path)->ingest();
    }

    /**
     * Ingest text content into the RAG pipeline.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function ingestText(string $content, array $metadata = []): RagPipelineResult
    {
        if ($this->ragPipeline === null) {
            throw new \RuntimeException('RAG pipeline is not configured');
        }

        return $this->ragPipeline->fromText($content, $metadata)->ingest();
    }
}
