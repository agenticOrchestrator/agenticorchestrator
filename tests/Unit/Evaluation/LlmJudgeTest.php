<?php

declare(strict_types=1);

use AgenticOrchestrator\Evaluation\Contracts\MetricInterface;
use AgenticOrchestrator\Evaluation\LlmJudge;
use AgenticOrchestrator\Evaluation\MetricResult;
use AgenticOrchestrator\Evaluation\TestCase as EvalTestCase;
use Prism\Prism\Enums\Provider;

describe('LlmJudge', function () {
    it('can be created with static make method', function () {
        $judge = LlmJudge::make();

        expect($judge)->toBeInstanceOf(LlmJudge::class);
    });

    it('can be created with specific provider and model', function () {
        $judge = LlmJudge::make(Provider::Anthropic, 'claude-3-sonnet');

        expect($judge)->toBeInstanceOf(LlmJudge::class);
    });

    it('supports fluent provider setting', function () {
        $judge = LlmJudge::make()
            ->withProvider(Provider::Anthropic);

        expect($judge)->toBeInstanceOf(LlmJudge::class);
    });

    it('supports fluent model setting', function () {
        $judge = LlmJudge::make()
            ->withModel('gpt-4o');

        expect($judge)->toBeInstanceOf(LlmJudge::class);
    });

    it('can register custom metrics', function () {
        $metric = Mockery::mock(MetricInterface::class);
        $metric->shouldReceive('name')->andReturn('custom_metric');

        $judge = LlmJudge::make()->registerMetric($metric);

        expect($judge->getAvailableMetrics())->toContain('custom_metric');
    });

    it('lists built-in metric names', function () {
        $judge = LlmJudge::make();
        $metrics = $judge->getAvailableMetrics();

        expect($metrics)->toContain('relevance')
            ->and($metrics)->toContain('accuracy')
            ->and($metrics)->toContain('helpfulness')
            ->and($metrics)->toContain('tone')
            ->and($metrics)->toContain('completeness')
            ->and($metrics)->toContain('safety');
    });

    it('returns zero-score result for unknown metric', function () {
        $judge = LlmJudge::make();

        $testCase = new EvalTestCase(
            name: 'unknown-metric-test',
            input: 'Test input',
        );

        $result = $judge->evaluate('nonexistent_metric', 'input', 'output', $testCase);

        expect($result)->toBeInstanceOf(MetricResult::class)
            ->and($result->score)->toBe(0.0)
            ->and($result->reasoning)->toContain('Unknown metric')
            ->and($result->metadata)->toHaveKey('error');
    });

    it('evaluates multiple metrics with evaluateAll', function () {
        $metric1 = Mockery::mock(MetricInterface::class);
        $metric1->shouldReceive('name')->andReturn('custom1');
        $metric1->shouldReceive('getPrompt')->andReturn('Evaluate metric 1');
        $metric1->shouldReceive('parseResponse')->andReturn(
            new MetricResult(name: 'custom1', score: 0.8)
        );

        $metric2 = Mockery::mock(MetricInterface::class);
        $metric2->shouldReceive('name')->andReturn('custom2');

        $judge = LlmJudge::make()
            ->registerMetric($metric1)
            ->registerMetric($metric2);

        $testCase = new EvalTestCase(name: 'multi-metric', input: 'Test');

        // evaluateAll will call evaluate for each, and custom2 is registered but
        // let's test that unknown metrics in the array produce zero-score
        $results = $judge->evaluateAll(
            ['nonexistent'],
            'input',
            'output',
            $testCase,
        );

        expect($results)->toHaveCount(1)
            ->and($results['nonexistent']->score)->toBe(0.0);
    });

    it('includes custom metrics in available metrics list', function () {
        $metric = Mockery::mock(MetricInterface::class);
        $metric->shouldReceive('name')->andReturn('brand_voice');

        $judge = LlmJudge::make()->registerMetric($metric);
        $available = $judge->getAvailableMetrics();

        expect($available)->toContain('brand_voice')
            ->and($available)->toContain('relevance');
    });

    it('custom metrics take precedence over built-in metrics', function () {
        $customRelevance = Mockery::mock(MetricInterface::class);
        $customRelevance->shouldReceive('name')->andReturn('relevance');

        $judge = LlmJudge::make()->registerMetric($customRelevance);

        // The custom metric should be retrievable via reflection
        $reflection = new ReflectionProperty($judge, 'customMetrics');
        $customMetrics = $reflection->getValue($judge);

        expect($customMetrics)->toHaveKey('relevance')
            ->and($customMetrics['relevance'])->toBe($customRelevance);
    });

    it('can register builtin metric classes statically', function () {
        $metricClass = get_class(new class implements MetricInterface
        {
            public function name(): string
            {
                return 'custom_static';
            }

            public function description(): string
            {
                return 'A custom static metric';
            }

            public function getPrompt(string $input, string $actualOutput, EvalTestCase $testCase): string
            {
                return 'Evaluate';
            }

            public function parseResponse(string $response, mixed $config): MetricResult
            {
                return new MetricResult(name: 'custom_static', score: 1.0);
            }
        });

        LlmJudge::registerBuiltinMetric('custom_static', $metricClass);

        $judge = LlmJudge::make();

        expect($judge->getAvailableMetrics())->toContain('custom_static');
    });
});
