<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tracking;

/**
 * Cost Calculator - Calculates costs based on usage and pricing.
 */
class CostCalculator
{
    /**
     * Default pricing per 1000 tokens (in USD).
     * Prices as of 2025.
     */
    protected static array $defaultPricing = [
        // OpenAI
        'gpt-4o' => ['input' => 0.0025, 'output' => 0.010],
        'gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.0006],
        'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
        'gpt-4' => ['input' => 0.03, 'output' => 0.06],
        'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
        'o1' => ['input' => 0.015, 'output' => 0.060],
        'o1-mini' => ['input' => 0.003, 'output' => 0.012],

        // Anthropic
        'claude-3-5-sonnet' => ['input' => 0.003, 'output' => 0.015],
        'claude-3-opus' => ['input' => 0.015, 'output' => 0.075],
        'claude-3-sonnet' => ['input' => 0.003, 'output' => 0.015],
        'claude-3-haiku' => ['input' => 0.00025, 'output' => 0.00125],

        // Embeddings
        'text-embedding-3-small' => ['input' => 0.00002, 'output' => 0.0],
        'text-embedding-3-large' => ['input' => 0.00013, 'output' => 0.0],
        'text-embedding-ada-002' => ['input' => 0.0001, 'output' => 0.0],
    ];

    /**
     * Custom pricing overrides.
     */
    protected array $customPricing = [];

    /**
     * Create a new cost calculator.
     */
    public function __construct(?array $customPricing = null)
    {
        $this->customPricing = $customPricing ?? [];
    }

    /**
     * Create a new cost calculator.
     */
    public static function make(?array $customPricing = null): static
    {
        return new static($customPricing);
    }

    /**
     * Calculate cost for a request.
     */
    public function calculate(
        string $model,
        int $inputTokens,
        int $outputTokens,
    ): float {
        $pricing = $this->getPricing($model);

        if ($pricing === null) {
            // Unknown model, return 0 cost with warning
            return 0.0;
        }

        $inputCost = ($inputTokens / 1000) * $pricing['input'];
        $outputCost = ($outputTokens / 1000) * $pricing['output'];

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Calculate cost for embeddings.
     */
    public function calculateEmbedding(string $model, int $tokens): float
    {
        $pricing = $this->getPricing($model);

        if ($pricing === null) {
            return 0.0;
        }

        return round(($tokens / 1000) * $pricing['input'], 6);
    }

    /**
     * Get pricing for a model.
     *
     * @return array{input: float, output: float}|null
     */
    public function getPricing(string $model): ?array
    {
        // Check custom pricing first
        if (isset($this->customPricing[$model])) {
            return $this->customPricing[$model];
        }

        // Check default pricing with partial matching
        $modelLower = strtolower($model);

        // Exact match first
        if (isset(self::$defaultPricing[$modelLower])) {
            return self::$defaultPricing[$modelLower];
        }

        // Partial match
        foreach (self::$defaultPricing as $knownModel => $pricing) {
            if (str_contains($modelLower, $knownModel) || str_contains($knownModel, $modelLower)) {
                return $pricing;
            }
        }

        return null;
    }

    /**
     * Set custom pricing for a model.
     */
    public function setPricing(string $model, float $inputPrice, float $outputPrice): static
    {
        $this->customPricing[$model] = [
            'input' => $inputPrice,
            'output' => $outputPrice,
        ];

        return $this;
    }

    /**
     * Get all known models.
     *
     * @return array<string>
     */
    public function getKnownModels(): array
    {
        return array_unique([
            ...array_keys(self::$defaultPricing),
            ...array_keys($this->customPricing),
        ]);
    }

    /**
     * Estimate monthly cost based on usage.
     */
    public function estimateMonthly(
        string $model,
        int $averageInputTokens,
        int $averageOutputTokens,
        int $requestsPerDay,
    ): array {
        $costPerRequest = $this->calculate($model, $averageInputTokens, $averageOutputTokens);

        $dailyCost = $costPerRequest * $requestsPerDay;
        $weeklyCost = $dailyCost * 7;
        $monthlyCost = $dailyCost * 30;

        return [
            'per_request' => round($costPerRequest, 6),
            'daily' => round($dailyCost, 2),
            'weekly' => round($weeklyCost, 2),
            'monthly' => round($monthlyCost, 2),
            'requests_per_day' => $requestsPerDay,
            'model' => $model,
        ];
    }
}
