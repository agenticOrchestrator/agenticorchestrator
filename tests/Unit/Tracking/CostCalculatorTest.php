<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Tracking;

use AgenticOrchestrator\Tracking\CostCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CostCalculator::class)]
class CostCalculatorTest extends TestCase
{
    #[Test]
    public function it_calculates_cost_for_known_model(): void
    {
        $calculator = CostCalculator::make();

        $cost = $calculator->calculate('gpt-4o', 1000, 500);

        // gpt-4o: input=0.0025, output=0.010 per 1K
        // (1000 * 0.0025 + 500 * 0.010) / 1000 = 0.0025 + 0.005 = 0.0075
        $this->assertEqualsWithDelta(0.0075, $cost, 0.0001);
    }

    #[Test]
    public function it_returns_zero_for_unknown_model(): void
    {
        $calculator = CostCalculator::make();

        $cost = $calculator->calculate('unknown-model', 1000, 500);

        $this->assertSame(0.0, $cost);
    }

    #[Test]
    public function it_uses_custom_pricing(): void
    {
        $calculator = CostCalculator::make(['custom-model' => ['input' => 0.1, 'output' => 0.2]]);

        $cost = $calculator->calculate('custom-model', 1000, 1000);

        // (1000 * 0.1 + 1000 * 0.2) / 1000 = 0.3
        $this->assertEqualsWithDelta(0.3, $cost, 0.0001);
    }

    #[Test]
    public function it_calculates_embedding_cost(): void
    {
        $calculator = CostCalculator::make();

        $cost = $calculator->calculateEmbedding('text-embedding-3-small', 1000);

        // 0.00002 per 1K tokens
        $this->assertEqualsWithDelta(0.00002, $cost, 0.000001);
    }

    #[Test]
    public function it_sets_custom_pricing(): void
    {
        $calculator = CostCalculator::make()
            ->setPricing('my-model', 0.05, 0.1);

        $pricing = $calculator->getPricing('my-model');

        $this->assertSame(0.05, $pricing['input']);
        $this->assertSame(0.1, $pricing['output']);
    }

    #[Test]
    public function it_lists_known_models(): void
    {
        $calculator = CostCalculator::make();

        $models = $calculator->getKnownModels();

        $this->assertContains('gpt-4o', $models);
        $this->assertContains('claude-3-5-sonnet', $models);
    }

    #[Test]
    public function it_estimates_monthly_cost(): void
    {
        $calculator = CostCalculator::make();

        $estimate = $calculator->estimateMonthly(
            model: 'gpt-4o-mini',
            averageInputTokens: 500,
            averageOutputTokens: 200,
            requestsPerDay: 100
        );

        $this->assertArrayHasKey('per_request', $estimate);
        $this->assertArrayHasKey('daily', $estimate);
        $this->assertArrayHasKey('monthly', $estimate);
        $this->assertSame(100, $estimate['requests_per_day']);
    }
}
