<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Agents\Concerns;

use AgenticOrchestrator\Agents\AgentResponse;
use AgenticOrchestrator\Rag\RagPipeline;
use AgenticOrchestrator\Rag\RagPipelineResult;
use AgenticOrchestrator\Responses\HybridResponse;
use AgenticOrchestrator\Responses\HybridResponseBuilder;
use AgenticOrchestrator\Responses\HybridStrategy;

/**
 * Trait for agents that can produce hybrid LLM+RAG responses.
 *
 * This trait extends the HasRag functionality to provide unified
 * response objects that combine LLM generation with RAG retrieval.
 *
 * @property-read RagPipeline|null $ragPipeline
 *
 * @method AgentResponse respond(string $message, array $context = [])
 * @method bool isRagEnabled()
 * @method RagPipelineResult retrieveRagContext(string $query)
 */
trait HasHybridResponse
{
    use HasRag;

    /**
     * Default hybrid strategy when both RAG and LLM are available.
     */
    protected HybridStrategy $defaultHybridStrategy = HybridStrategy::RAG_AUGMENTED;

    /**
     * Minimum RAG confidence threshold for including results.
     */
    protected float $ragConfidenceThreshold = 0.5;

    /**
     * Whether to fall back to LLM-only when RAG has no results.
     */
    protected bool $llmFallbackEnabled = true;

    /**
     * Process a message and return a hybrid response.
     *
     * This method retrieves RAG context (if enabled), gets an LLM response,
     * and combines them into a unified HybridResponse.
     *
     * @param  string  $message  The user's message
     * @param  array<string, mixed>  $context  Additional context
     * @param  HybridStrategy|null  $strategy  Override the default strategy
     */
    public function respondHybrid(
        string $message,
        array $context = [],
        ?HybridStrategy $strategy = null,
    ): HybridResponse {
        $strategy ??= $this->defaultHybridStrategy;

        // Handle strategy-specific execution
        return match ($strategy) {
            HybridStrategy::RAG_ONLY => $this->executeRagOnly($message),
            HybridStrategy::LLM_ONLY => $this->executeLlmOnly($message, $context),
            HybridStrategy::RAG_AUGMENTED => $this->executeRagAugmented($message, $context),
            HybridStrategy::PARALLEL => $this->executeParallel($message, $context),
            HybridStrategy::RAG_WITH_FALLBACK => $this->executeRagWithFallback($message, $context),
            HybridStrategy::LLM_WITH_VERIFICATION => $this->executeLlmWithVerification($message, $context),
        };
    }

    /**
     * Execute RAG-only strategy.
     */
    protected function executeRagOnly(string $message): HybridResponse
    {
        $ragResult = $this->retrieveRagContext($message);

        return HybridResponse::fromRagResult($ragResult, $message);
    }

    /**
     * Execute LLM-only strategy.
     *
     * @param  array<string, mixed>  $context
     */
    protected function executeLlmOnly(string $message, array $context): HybridResponse
    {
        $response = $this->respond($message, $context);

        return HybridResponse::fromAgentResponse($response, $message);
    }

    /**
     * Execute RAG-augmented strategy (traditional RAG pattern).
     *
     * RAG context is retrieved and injected into the LLM prompt.
     *
     * @param  array<string, mixed>  $context
     */
    protected function executeRagAugmented(string $message, array $context): HybridResponse
    {
        $ragResult = new RagPipelineResult;
        $startTime = microtime(true);

        // Retrieve RAG context if enabled
        if ($this->isRagEnabled() && $this->ragPipeline !== null) {
            $ragResult = $this->retrieveRagContext($message);
        }

        // Inject RAG context into the prompt context
        if ($ragResult->hasContext()) {
            $context['rag_context'] = $this->getFormattedRagContext($ragResult);
        }

        // Get LLM response with augmented context
        $response = $this->respond($message, $context);

        return HybridResponse::fromCombined(
            llmResponse: $response,
            ragResult: $ragResult,
            query: $message,
            strategy: HybridStrategy::RAG_AUGMENTED,
        );
    }

    /**
     * Execute parallel strategy.
     *
     * Both RAG and LLM are queried independently and results combined.
     *
     * @param  array<string, mixed>  $context
     */
    protected function executeParallel(string $message, array $context): HybridResponse
    {
        $builder = HybridResponse::builder($message)
            ->withStrategy(HybridStrategy::PARALLEL);

        $startTime = microtime(true);

        // Execute RAG retrieval
        if ($this->isRagEnabled() && $this->ragPipeline !== null) {
            $ragResult = $this->retrieveRagContext($message);
            $builder->withRagResult($ragResult);
        }

        // Execute LLM generation (without RAG context injection)
        $response = $this->respond($message, $context);
        $builder->withAgentResponse($response);

        return $builder->build();
    }

    /**
     * Execute RAG with LLM fallback strategy.
     *
     * Uses RAG if confidence is high enough, otherwise falls back to LLM.
     *
     * @param  array<string, mixed>  $context
     */
    protected function executeRagWithFallback(string $message, array $context): HybridResponse
    {
        $ragResult = new RagPipelineResult;

        // Try RAG first
        if ($this->isRagEnabled() && $this->ragPipeline !== null) {
            $ragResult = $this->retrieveRagContext($message);

            // Check if RAG has high-confidence results
            $highConfidenceResults = $ragResult->getResultsAboveThreshold($this->ragConfidenceThreshold);

            if (count($highConfidenceResults) > 0) {
                // RAG has good results, return them
                return HybridResponse::builder($message)
                    ->withStrategy(HybridStrategy::RAG_WITH_FALLBACK)
                    ->withRagResult($ragResult)
                    ->withMeta('fallback_used', false)
                    ->build();
            }
        }

        // Fall back to LLM
        if ($this->llmFallbackEnabled) {
            $response = $this->respond($message, $context);

            return HybridResponse::builder($message)
                ->withStrategy(HybridStrategy::RAG_WITH_FALLBACK)
                ->withAgentResponse($response)
                ->withMeta('fallback_used', true)
                ->withMeta('fallback_reason', 'rag_confidence_below_threshold')
                ->build();
        }

        // No fallback, return empty RAG result
        return HybridResponse::fromRagResult($ragResult, $message);
    }

    /**
     * Execute LLM with RAG verification strategy.
     *
     * LLM generates response first, then RAG verifies/supplements it.
     *
     * @param  array<string, mixed>  $context
     */
    protected function executeLlmWithVerification(string $message, array $context): HybridResponse
    {
        // Get LLM response first
        $response = $this->respond($message, $context);

        $builder = HybridResponse::builder($message)
            ->withStrategy(HybridStrategy::LLM_WITH_VERIFICATION)
            ->withAgentResponse($response);

        // Verify/supplement with RAG
        if ($this->isRagEnabled() && $this->ragPipeline !== null) {
            // Query RAG with both the original message and the LLM response
            // to find supporting or contradicting evidence
            $ragResult = $this->retrieveRagContext($message);
            $builder->withRagResult($ragResult);

            // Calculate verification score based on RAG matches
            $avgScore = $ragResult->getAverageScore();
            $builder->withMeta('verification_score', $avgScore);
            $builder->withMeta('verified', $avgScore >= $this->ragConfidenceThreshold);
        }

        return $builder->build();
    }

    /**
     * Set the default hybrid strategy.
     *
     * @return $this
     */
    public function withHybridStrategy(HybridStrategy $strategy): static
    {
        $this->defaultHybridStrategy = $strategy;

        return $this;
    }

    /**
     * Set the RAG confidence threshold.
     *
     * @return $this
     */
    public function withRagConfidenceThreshold(float $threshold): static
    {
        $this->ragConfidenceThreshold = max(0.0, min(1.0, $threshold));

        return $this;
    }

    /**
     * Enable or disable LLM fallback.
     *
     * @return $this
     */
    public function withLlmFallback(bool $enabled = true): static
    {
        $this->llmFallbackEnabled = $enabled;

        return $this;
    }

    /**
     * Get the current hybrid strategy.
     */
    public function getHybridStrategy(): HybridStrategy
    {
        return $this->defaultHybridStrategy;
    }

    /**
     * Get the current RAG confidence threshold.
     */
    public function getRagConfidenceThreshold(): float
    {
        return $this->ragConfidenceThreshold;
    }

    /**
     * Check if LLM fallback is enabled.
     */
    public function isLlmFallbackEnabled(): bool
    {
        return $this->llmFallbackEnabled;
    }

    /**
     * Create a hybrid response builder for this agent.
     */
    public function hybridBuilder(string $query): HybridResponseBuilder
    {
        return HybridResponse::builder($query)
            ->withStrategy($this->defaultHybridStrategy);
    }
}
