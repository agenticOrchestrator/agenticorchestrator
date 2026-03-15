<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Evaluation;

use AgenticOrchestrator\Evaluation\Contracts\MetricInterface;
use AgenticOrchestrator\Evaluation\Metrics\AccuracyMetric;
use AgenticOrchestrator\Evaluation\Metrics\CompletenessMetric;
use AgenticOrchestrator\Evaluation\Metrics\HelpfulnessMetric;
use AgenticOrchestrator\Evaluation\Metrics\RelevanceMetric;
use AgenticOrchestrator\Evaluation\Metrics\SafetyMetric;
use AgenticOrchestrator\Evaluation\Metrics\ToneMetric;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;

/**
 * LLM Judge - Uses an LLM to evaluate agent responses on various metrics.
 */
class LlmJudge
{
    /**
     * Available metric classes.
     *
     * @var array<string, class-string<MetricInterface>>
     */
    protected static array $metrics = [
        'relevance' => RelevanceMetric::class,
        'accuracy' => AccuracyMetric::class,
        'helpfulness' => HelpfulnessMetric::class,
        'tone' => ToneMetric::class,
        'completeness' => CompletenessMetric::class,
        'safety' => SafetyMetric::class,
    ];

    /**
     * The LLM provider to use for judging.
     */
    protected Provider $provider;

    /**
     * The model to use for judging.
     */
    protected string $model;

    /**
     * Registered custom metrics.
     *
     * @var array<string, MetricInterface>
     */
    protected array $customMetrics = [];

    /**
     * Create a new LLM judge.
     */
    public function __construct(
        ?Provider $provider = null,
        ?string $model = null,
    ) {
        $this->provider = $provider ?? Provider::OpenAI;
        $this->model = $model ?? config('agent-orchestrator.evaluation.judge_model', 'gpt-4o-mini');
    }

    /**
     * Create a new LLM judge with specific settings.
     */
    public static function make(?Provider $provider = null, ?string $model = null): static
    {
        return new static($provider, $model);
    }

    /**
     * Set the provider.
     */
    public function withProvider(Provider $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Set the model.
     */
    public function withModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Register a custom metric.
     */
    public function registerMetric(MetricInterface $metric): static
    {
        $this->customMetrics[$metric->name()] = $metric;

        return $this;
    }

    /**
     * Evaluate a response on a specific metric.
     */
    public function evaluate(
        string $metricName,
        string $input,
        string $output,
        TestCase $testCase,
    ): MetricResult {
        $metric = $this->getMetric($metricName);

        if ($metric === null) {
            return new MetricResult(
                name: $metricName,
                score: 0.0,
                reasoning: "Unknown metric: {$metricName}",
                metadata: ['error' => 'metric_not_found'],
            );
        }

        $prompt = $metric->getPrompt($input, $output, $testCase);

        try {
            $response = Prism::text()
                ->using($this->provider, $this->model)
                ->withPrompt($prompt)
                ->asText();

            $config = $testCase->getMetric($metricName) ?? [];

            return $metric->parseResponse($response->text, $config);
        } catch (\Throwable $e) {
            return new MetricResult(
                name: $metricName,
                score: 0.0,
                reasoning: 'Evaluation failed: '.$e->getMessage(),
                metadata: ['error' => $e->getMessage()],
            );
        }
    }

    /**
     * Evaluate a response on multiple metrics.
     *
     * @param  array<string>  $metricNames
     * @return array<string, MetricResult>
     */
    public function evaluateAll(
        array $metricNames,
        string $input,
        string $output,
        TestCase $testCase,
    ): array {
        $results = [];

        foreach ($metricNames as $metricName) {
            $results[$metricName] = $this->evaluate($metricName, $input, $output, $testCase);
        }

        return $results;
    }

    /**
     * Evaluate using all available metrics.
     *
     * @return array<string, MetricResult>
     */
    public function evaluateComprehensive(
        string $input,
        string $output,
        TestCase $testCase,
    ): array {
        return $this->evaluateAll(
            array_keys(self::$metrics),
            $input,
            $output,
            $testCase,
        );
    }

    /**
     * Get a metric instance by name.
     */
    protected function getMetric(string $name): ?MetricInterface
    {
        // Check custom metrics first
        if (isset($this->customMetrics[$name])) {
            return $this->customMetrics[$name];
        }

        // Check built-in metrics
        if (isset(self::$metrics[$name])) {
            return new self::$metrics[$name];
        }

        return null;
    }

    /**
     * Get all available metric names.
     *
     * @return array<string>
     */
    public function getAvailableMetrics(): array
    {
        return array_unique([
            ...array_keys(self::$metrics),
            ...array_keys($this->customMetrics),
        ]);
    }

    /**
     * Register a built-in metric class.
     *
     * @param  class-string<MetricInterface>  $metricClass
     */
    public static function registerBuiltinMetric(string $name, string $metricClass): void
    {
        self::$metrics[$name] = $metricClass;
    }
}
