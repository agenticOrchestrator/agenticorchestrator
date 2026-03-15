<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Responses;

/**
 * Defines the strategy used for generating a hybrid response.
 *
 * This enum indicates how the response was constructed from
 * different sources (LLM, RAG, or a combination).
 */
enum HybridStrategy: string
{
    /**
     * Response generated purely from RAG retrieval.
     * No LLM processing, just retrieved documents.
     */
    case RAG_ONLY = 'rag_only';

    /**
     * Response generated purely from LLM.
     * No RAG context was used.
     */
    case LLM_ONLY = 'llm_only';

    /**
     * RAG-augmented LLM response.
     * RAG context was injected into the LLM prompt.
     * This is the traditional RAG pattern.
     */
    case RAG_AUGMENTED = 'rag_augmented';

    /**
     * Parallel execution of RAG and LLM.
     * Both sources queried independently and combined.
     */
    case PARALLEL = 'parallel';

    /**
     * RAG with LLM fallback.
     * Uses RAG if confidence is high enough, otherwise falls back to LLM.
     */
    case RAG_WITH_FALLBACK = 'rag_with_fallback';

    /**
     * LLM with RAG verification.
     * LLM generates response, RAG verifies/supplements it.
     */
    case LLM_WITH_VERIFICATION = 'llm_with_verification';

    /**
     * Get a human-readable description of the strategy.
     */
    public function description(): string
    {
        return match ($this) {
            self::RAG_ONLY => 'Pure retrieval from knowledge base',
            self::LLM_ONLY => 'Pure LLM generation without retrieval',
            self::RAG_AUGMENTED => 'LLM generation augmented with retrieved context',
            self::PARALLEL => 'Parallel RAG retrieval and LLM generation',
            self::RAG_WITH_FALLBACK => 'RAG retrieval with LLM fallback',
            self::LLM_WITH_VERIFICATION => 'LLM generation verified by RAG',
        };
    }

    /**
     * Check if this strategy uses RAG.
     */
    public function usesRag(): bool
    {
        return $this !== self::LLM_ONLY;
    }

    /**
     * Check if this strategy uses LLM.
     */
    public function usesLlm(): bool
    {
        return $this !== self::RAG_ONLY;
    }

    /**
     * Check if this strategy combines multiple sources.
     */
    public function isHybrid(): bool
    {
        return $this->usesRag() && $this->usesLlm();
    }
}
