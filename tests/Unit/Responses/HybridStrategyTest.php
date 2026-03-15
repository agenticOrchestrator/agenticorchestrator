<?php

declare(strict_types=1);

namespace Tests\Unit\Responses;

use AgenticOrchestrator\Responses\HybridStrategy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HybridStrategyTest extends TestCase
{
    #[Test]
    public function it_has_all_expected_strategies(): void
    {
        $strategies = HybridStrategy::cases();

        $this->assertCount(6, $strategies);
        $this->assertContains(HybridStrategy::RAG_ONLY, $strategies);
        $this->assertContains(HybridStrategy::LLM_ONLY, $strategies);
        $this->assertContains(HybridStrategy::RAG_AUGMENTED, $strategies);
        $this->assertContains(HybridStrategy::PARALLEL, $strategies);
        $this->assertContains(HybridStrategy::RAG_WITH_FALLBACK, $strategies);
        $this->assertContains(HybridStrategy::LLM_WITH_VERIFICATION, $strategies);
    }

    #[Test]
    public function it_provides_descriptions_for_all_strategies(): void
    {
        foreach (HybridStrategy::cases() as $strategy) {
            $description = $strategy->description();
            $this->assertNotEmpty($description);
            $this->assertIsString($description);
        }
    }

    #[Test]
    public function it_correctly_identifies_rag_usage(): void
    {
        $this->assertTrue(HybridStrategy::RAG_ONLY->usesRag());
        $this->assertFalse(HybridStrategy::LLM_ONLY->usesRag());
        $this->assertTrue(HybridStrategy::RAG_AUGMENTED->usesRag());
        $this->assertTrue(HybridStrategy::PARALLEL->usesRag());
        $this->assertTrue(HybridStrategy::RAG_WITH_FALLBACK->usesRag());
        $this->assertTrue(HybridStrategy::LLM_WITH_VERIFICATION->usesRag());
    }

    #[Test]
    public function it_correctly_identifies_llm_usage(): void
    {
        $this->assertFalse(HybridStrategy::RAG_ONLY->usesLlm());
        $this->assertTrue(HybridStrategy::LLM_ONLY->usesLlm());
        $this->assertTrue(HybridStrategy::RAG_AUGMENTED->usesLlm());
        $this->assertTrue(HybridStrategy::PARALLEL->usesLlm());
        $this->assertTrue(HybridStrategy::RAG_WITH_FALLBACK->usesLlm());
        $this->assertTrue(HybridStrategy::LLM_WITH_VERIFICATION->usesLlm());
    }

    #[Test]
    public function it_correctly_identifies_hybrid_strategies(): void
    {
        $this->assertFalse(HybridStrategy::RAG_ONLY->isHybrid());
        $this->assertFalse(HybridStrategy::LLM_ONLY->isHybrid());
        $this->assertTrue(HybridStrategy::RAG_AUGMENTED->isHybrid());
        $this->assertTrue(HybridStrategy::PARALLEL->isHybrid());
        $this->assertTrue(HybridStrategy::RAG_WITH_FALLBACK->isHybrid());
        $this->assertTrue(HybridStrategy::LLM_WITH_VERIFICATION->isHybrid());
    }

    #[Test]
    public function it_has_correct_string_values(): void
    {
        $this->assertEquals('rag_only', HybridStrategy::RAG_ONLY->value);
        $this->assertEquals('llm_only', HybridStrategy::LLM_ONLY->value);
        $this->assertEquals('rag_augmented', HybridStrategy::RAG_AUGMENTED->value);
        $this->assertEquals('parallel', HybridStrategy::PARALLEL->value);
        $this->assertEquals('rag_with_fallback', HybridStrategy::RAG_WITH_FALLBACK->value);
        $this->assertEquals('llm_with_verification', HybridStrategy::LLM_WITH_VERIFICATION->value);
    }
}
