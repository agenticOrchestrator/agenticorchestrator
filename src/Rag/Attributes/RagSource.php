<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Rag\Attributes;

use Attribute;

/**
 * RagSource - Attribute for auto-injecting RAG context into agent instructions.
 *
 * When applied to an agent class or property, this attribute configures
 * automatic RAG context retrieval and injection into the agent's prompts.
 *
 * @example
 * ```php
 * #[RagSource(namespace: 'faq', limit: 5)]
 * class CustomerSupportAgent extends Agent
 * {
 *     // RAG context will be automatically injected
 * }
 * ```
 * @example
 * ```php
 * class CustomerSupportAgent extends Agent
 * {
 *     #[RagSource(namespace: 'product_docs', threshold: 0.8)]
 *     protected string $ragNamespace = 'product_documentation';
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class RagSource
{
    /**
     * Create a new RAG source attribute.
     *
     * @param  string  $namespace  The RAG namespace to query
     * @param  int  $limit  Maximum number of results to include
     * @param  float  $threshold  Minimum similarity score threshold
     * @param  string|null  $contextTemplate  Template for formatting context
     * @param  bool  $enabled  Whether RAG is enabled for this source
     * @param  array<string, mixed>  $filter  Additional metadata filters
     */
    public function __construct(
        public readonly string $namespace,
        public readonly int $limit = 5,
        public readonly float $threshold = 0.7,
        public readonly ?string $contextTemplate = null,
        public readonly bool $enabled = true,
        public readonly array $filter = [],
    ) {}

    /**
     * Get the context template or default.
     */
    public function getContextTemplate(): string
    {
        return $this->contextTemplate ?? <<<'TEMPLATE'
## Relevant Context

The following information may be helpful in answering the user's question:

{context}

---

TEMPLATE;
    }
}
