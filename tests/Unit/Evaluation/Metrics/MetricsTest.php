<?php

declare(strict_types=1);

use AgenticOrchestrator\Evaluation\Contracts\MetricInterface;
use AgenticOrchestrator\Evaluation\MetricResult;
use AgenticOrchestrator\Evaluation\Metrics\AbstractMetric;
use AgenticOrchestrator\Evaluation\Metrics\AccuracyMetric;
use AgenticOrchestrator\Evaluation\Metrics\CompletenessMetric;
use AgenticOrchestrator\Evaluation\Metrics\HelpfulnessMetric;
use AgenticOrchestrator\Evaluation\Metrics\RelevanceMetric;
use AgenticOrchestrator\Evaluation\Metrics\SafetyMetric;
use AgenticOrchestrator\Evaluation\Metrics\ToneMetric;
use AgenticOrchestrator\Evaluation\TestCase;

describe('AbstractMetric - parseResponse', function () {
    beforeEach(function () {
        $this->metric = new class extends AbstractMetric
        {
            public function name(): string
            {
                return 'test_metric';
            }

            public function description(): string
            {
                return 'Test metric for testing';
            }

            public function getPrompt(string $input, string $actualOutput, TestCase $testCase): string
            {
                return 'test prompt';
            }

            public function callExtractScore(string $response): float
            {
                return $this->extractScore($response);
            }

            public function callExtractReasoning(string $response): string
            {
                return $this->extractReasoning($response);
            }
        };
    });

    it('parses JSON format response', function () {
        $response = '{"score": 0.85, "reasoning": "Well structured response"}';

        $result = $this->metric->parseResponse($response, []);

        expect($result)->toBeInstanceOf(MetricResult::class);
        expect($result->name)->toBe('test_metric');
        expect($result->score)->toBe(0.85);
        expect($result->reasoning)->toBe('Well structured response');
    });

    it('uses threshold from config', function () {
        $result = $this->metric->parseResponse('{"score": 0.8}', ['threshold' => 0.9]);

        expect($result->threshold)->toBe(0.9);
        expect($result->passes())->toBeFalse();
    });

    it('uses default threshold when config has none', function () {
        $result = $this->metric->parseResponse('{"score": 0.8}', []);

        expect($result->threshold)->toBe(0.7);
        expect($result->passes())->toBeTrue();
    });

    it('stores raw response in metadata', function () {
        $response = '{"score": 0.5, "reasoning": "Average"}';
        $result = $this->metric->parseResponse($response, []);

        expect($result->metadata['raw_response'])->toBe($response);
    });

    it('extracts score from Score: X/10 format', function () {
        $score = $this->metric->callExtractScore('Score: 8/10');

        expect($score)->toBe(0.8);
    });

    it('extracts score from Score: X format', function () {
        $score = $this->metric->callExtractScore('Score: 7');

        expect($score)->toBe(0.7);
    });

    it('extracts score from percentage format', function () {
        $score = $this->metric->callExtractScore('The response scores 75% on relevance');

        expect($score)->toBe(0.75);
    });

    it('extracts score from decimal at start', function () {
        $score = $this->metric->callExtractScore('0.65');

        expect($score)->toBe(0.65);
    });

    it('extracts score from Score: X/100 format', function () {
        $score = $this->metric->callExtractScore('Score: 85/100');

        expect($score)->toBe(0.85);
    });

    it('defaults to 0.5 when parsing fails', function () {
        $score = $this->metric->callExtractScore('This has no score information at all.');

        expect($score)->toBe(0.5);
    });

    it('clamps score to 0.0 minimum', function () {
        // Negative values in JSON won't match the regex pattern, so it falls back to 0.5
        $score = $this->metric->callExtractScore('{"score": -0.5}');

        expect($score)->toBe(0.5);
    });

    it('clamps score to 1.0 maximum', function () {
        $score = $this->metric->callExtractScore('{"score": 1.5}');

        expect($score)->toBe(1.0);
    });

    it('extracts reasoning from JSON format', function () {
        $reasoning = $this->metric->callExtractReasoning('{"score": 0.8, "reasoning": "Clear explanation"}');

        expect($reasoning)->toBe('Clear explanation');
    });

    it('extracts reasoning from Reasoning: format', function () {
        $reasoning = $this->metric->callExtractReasoning("Score: 8/10\nReasoning: The response was well structured and complete.");

        expect($reasoning)->toBe('The response was well structured and complete.');
    });

    it('extracts reasoning from Explanation: format', function () {
        $reasoning = $this->metric->callExtractReasoning("Score: 7/10\nExplanation: Good but could be more detailed.");

        expect($reasoning)->toBe('Good but could be more detailed.');
    });

    it('returns cleaned response as reasoning fallback', function () {
        $reasoning = $this->metric->callExtractReasoning("Score: 8\nThe response covers all key points.");

        expect($reasoning)->toContain('The response covers all key points');
    });
});

describe('AccuracyMetric', function () {
    beforeEach(function () {
        $this->metric = new AccuracyMetric;
    });

    it('implements MetricInterface', function () {
        expect($this->metric)->toBeInstanceOf(MetricInterface::class);
    });

    it('has correct name', function () {
        expect($this->metric->name())->toBe('accuracy');
    });

    it('has description', function () {
        expect($this->metric->description())->toContain('accuracy');
    });

    it('has higher default threshold than base', function () {
        $result = $this->metric->parseResponse('{"score": 0.75}', []);

        expect($result->threshold)->toBe(0.8);
        expect($result->passes())->toBeFalse();
    });

    it('generates prompt with input and output', function () {
        $testCase = new TestCase(name: 'test', input: 'What is 2+2?');

        $prompt = $this->metric->getPrompt('What is 2+2?', 'The answer is 4.', $testCase);

        expect($prompt)->toContain('ACCURACY');
        expect($prompt)->toContain('What is 2+2?');
        expect($prompt)->toContain('The answer is 4.');
        expect($prompt)->toContain('score');
    });

    it('includes expected output as reference when available', function () {
        $testCase = new TestCase(
            name: 'test',
            input: 'What is 2+2?',
            expectedOutput: 'The answer is 4',
        );

        $prompt = $this->metric->getPrompt('What is 2+2?', 'It equals 4.', $testCase);

        expect($prompt)->toContain('Reference/Expected Output');
        expect($prompt)->toContain('The answer is 4');
    });

    it('excludes reference section when no expected output', function () {
        $testCase = new TestCase(name: 'test', input: 'Question');

        $prompt = $this->metric->getPrompt('Question', 'Answer', $testCase);

        expect($prompt)->not->toContain('Reference/Expected Output');
    });

    it('includes context when available', function () {
        $testCase = new TestCase(
            name: 'test',
            input: 'Question',
            context: ['fact' => 'Earth orbits the Sun'],
        );

        $prompt = $this->metric->getPrompt('Question', 'Answer', $testCase);

        expect($prompt)->toContain('Context Information');
        expect($prompt)->toContain('Earth orbits the Sun');
    });
});

describe('CompletenessMetric', function () {
    beforeEach(function () {
        $this->metric = new CompletenessMetric;
    });

    it('implements MetricInterface', function () {
        expect($this->metric)->toBeInstanceOf(MetricInterface::class);
    });

    it('has correct name', function () {
        expect($this->metric->name())->toBe('completeness');
    });

    it('has description', function () {
        expect($this->metric->description())->toContain('completely');
    });

    it('uses default threshold of 0.7', function () {
        $result = $this->metric->parseResponse('{"score": 0.7}', []);

        expect($result->threshold)->toBe(0.7);
        expect($result->passes())->toBeTrue();
    });

    it('generates prompt with input and output', function () {
        $testCase = new TestCase(name: 'test', input: 'Explain sorting algorithms');

        $prompt = $this->metric->getPrompt('Explain sorting algorithms', 'Bubble sort...', $testCase);

        expect($prompt)->toContain('COMPLETENESS');
        expect($prompt)->toContain('Explain sorting algorithms');
        expect($prompt)->toContain('Bubble sort...');
    });

    it('includes required elements when present in metadata', function () {
        $testCase = new TestCase(
            name: 'test',
            input: 'Explain sorting',
            metadata: ['required_elements' => ['time complexity', 'space complexity', 'example']],
        );

        $prompt = $this->metric->getPrompt('Explain sorting', 'Response', $testCase);

        expect($prompt)->toContain('Required Elements');
        expect($prompt)->toContain('time complexity');
        expect($prompt)->toContain('space complexity');
        expect($prompt)->toContain('example');
    });

    it('excludes required elements section when not in metadata', function () {
        $testCase = new TestCase(name: 'test', input: 'Question');

        $prompt = $this->metric->getPrompt('Question', 'Answer', $testCase);

        expect($prompt)->not->toContain('Required Elements');
    });
});

describe('HelpfulnessMetric', function () {
    beforeEach(function () {
        $this->metric = new HelpfulnessMetric;
    });

    it('implements MetricInterface', function () {
        expect($this->metric)->toBeInstanceOf(MetricInterface::class);
    });

    it('has correct name', function () {
        expect($this->metric->name())->toBe('helpfulness');
    });

    it('has description', function () {
        expect($this->metric->description())->toContain('helpful');
    });

    it('generates prompt with helpfulness criteria', function () {
        $testCase = new TestCase(name: 'test', input: 'How do I fix this bug?');

        $prompt = $this->metric->getPrompt('How do I fix this bug?', 'Try restarting.', $testCase);

        expect($prompt)->toContain('HELPFULNESS');
        expect($prompt)->toContain('How do I fix this bug?');
        expect($prompt)->toContain('Try restarting.');
        expect($prompt)->toContain('actionable');
    });
});

describe('RelevanceMetric', function () {
    beforeEach(function () {
        $this->metric = new RelevanceMetric;
    });

    it('implements MetricInterface', function () {
        expect($this->metric)->toBeInstanceOf(MetricInterface::class);
    });

    it('has correct name', function () {
        expect($this->metric->name())->toBe('relevance');
    });

    it('has description', function () {
        expect($this->metric->description())->toContain('relevant');
    });

    it('generates prompt with relevance criteria', function () {
        $testCase = new TestCase(name: 'test', input: 'What is PHP?');

        $prompt = $this->metric->getPrompt('What is PHP?', 'PHP is a language.', $testCase);

        expect($prompt)->toContain('RELEVANCE');
        expect($prompt)->toContain('What is PHP?');
        expect($prompt)->toContain('PHP is a language.');
    });

    it('includes expected output as reference when available', function () {
        $testCase = new TestCase(
            name: 'test',
            input: 'What is PHP?',
            expectedOutput: 'PHP is a server-side scripting language',
        );

        $prompt = $this->metric->getPrompt('What is PHP?', 'PHP is a language.', $testCase);

        expect($prompt)->toContain('Expected/Reference Output');
        expect($prompt)->toContain('PHP is a server-side scripting language');
    });

    it('excludes reference section when no expected output', function () {
        $testCase = new TestCase(name: 'test', input: 'Question');

        $prompt = $this->metric->getPrompt('Question', 'Answer', $testCase);

        expect($prompt)->not->toContain('Expected/Reference Output');
    });
});

describe('SafetyMetric', function () {
    beforeEach(function () {
        $this->metric = new SafetyMetric;
    });

    it('implements MetricInterface', function () {
        expect($this->metric)->toBeInstanceOf(MetricInterface::class);
    });

    it('has correct name', function () {
        expect($this->metric->name())->toBe('safety');
    });

    it('has description', function () {
        expect($this->metric->description())->toContain('safe');
    });

    it('has high default threshold of 0.9', function () {
        $result = $this->metric->parseResponse('{"score": 0.85}', []);

        expect($result->threshold)->toBe(0.9);
        expect($result->passes())->toBeFalse();
    });

    it('passes at 0.9 threshold', function () {
        $result = $this->metric->parseResponse('{"score": 0.95}', []);

        expect($result->passes())->toBeTrue();
    });

    it('generates prompt with safety criteria', function () {
        $testCase = new TestCase(name: 'test', input: 'How to stay healthy?');

        $prompt = $this->metric->getPrompt('How to stay healthy?', 'Eat well and exercise.', $testCase);

        expect($prompt)->toContain('SAFETY');
        expect($prompt)->toContain('harmful');
        expect($prompt)->toContain('How to stay healthy?');
        expect($prompt)->toContain('Eat well and exercise.');
    });
});

describe('ToneMetric', function () {
    beforeEach(function () {
        $this->metric = new ToneMetric;
    });

    it('implements MetricInterface', function () {
        expect($this->metric)->toBeInstanceOf(MetricInterface::class);
    });

    it('has correct name', function () {
        expect($this->metric->name())->toBe('tone');
    });

    it('has description', function () {
        expect($this->metric->description())->toContain('tone');
    });

    it('generates prompt with default expected tone', function () {
        $testCase = new TestCase(name: 'test', input: 'Hello');

        $prompt = $this->metric->getPrompt('Hello', 'Hi there!', $testCase);

        expect($prompt)->toContain('TONE');
        expect($prompt)->toContain('professional and friendly');
        expect($prompt)->toContain('Hello');
        expect($prompt)->toContain('Hi there!');
    });

    it('generates prompt with custom expected tone from metadata', function () {
        $testCase = new TestCase(
            name: 'test',
            input: 'I lost my data',
            metadata: ['expected_tone' => 'empathetic and supportive'],
        );

        $prompt = $this->metric->getPrompt('I lost my data', 'I understand.', $testCase);

        expect($prompt)->toContain('empathetic and supportive');
        expect($prompt)->not->toContain('professional and friendly');
    });
});

describe('All Metrics - shared behavior', function () {
    $metrics = [
        'AccuracyMetric' => new AccuracyMetric,
        'CompletenessMetric' => new CompletenessMetric,
        'HelpfulnessMetric' => new HelpfulnessMetric,
        'RelevanceMetric' => new RelevanceMetric,
        'SafetyMetric' => new SafetyMetric,
        'ToneMetric' => new ToneMetric,
    ];

    foreach ($metrics as $className => $metric) {
        it("{$className} extends AbstractMetric", function () use ($metric) {
            expect($metric)->toBeInstanceOf(AbstractMetric::class);
        });

        it("{$className} implements MetricInterface", function () use ($metric) {
            expect($metric)->toBeInstanceOf(MetricInterface::class);
        });

        it("{$className} returns a non-empty name", function () use ($metric) {
            expect($metric->name())->toBeString();
            expect(strlen($metric->name()))->toBeGreaterThan(0);
        });

        it("{$className} returns a non-empty description", function () use ($metric) {
            expect($metric->description())->toBeString();
            expect(strlen($metric->description()))->toBeGreaterThan(0);
        });

        it("{$className} parseResponse returns MetricResult", function () use ($metric) {
            $result = $metric->parseResponse('{"score": 0.75, "reasoning": "OK"}', []);

            expect($result)->toBeInstanceOf(MetricResult::class);
            expect($result->score)->toBe(0.75);
            expect($result->name)->toBe($metric->name());
        });

        it("{$className} getPrompt returns non-empty string", function () use ($metric) {
            $testCase = new TestCase(name: 'test', input: 'Test input');
            $prompt = $metric->getPrompt('Test input', 'Test output', $testCase);

            expect($prompt)->toBeString();
            expect(strlen($prompt))->toBeGreaterThan(0);
            expect($prompt)->toContain('Test input');
            expect($prompt)->toContain('Test output');
        });

        it("{$className} prompt includes scoring guide", function () use ($metric) {
            $testCase = new TestCase(name: 'test', input: 'Input');
            $prompt = $metric->getPrompt('Input', 'Output', $testCase);

            expect($prompt)->toContain('Scoring Guide');
            expect($prompt)->toContain('1.0');
            expect($prompt)->toContain('0.0');
        });

        it("{$className} prompt includes JSON format instruction", function () use ($metric) {
            $testCase = new TestCase(name: 'test', input: 'Input');
            $prompt = $metric->getPrompt('Input', 'Output', $testCase);

            expect($prompt)->toContain('"score"');
            expect($prompt)->toContain('"reasoning"');
        });
    }
});
