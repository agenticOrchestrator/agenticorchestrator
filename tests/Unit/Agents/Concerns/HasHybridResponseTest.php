<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\Concerns\HasHybridResponse;
use AgenticOrchestrator\Responses\HybridResponseBuilder;
use AgenticOrchestrator\Responses\HybridStrategy;

describe('HasHybridResponse', function () {

    beforeEach(function () {
        $this->hybridAgent = new class
        {
            use HasHybridResponse;

            public function respond(string $message, array $context = []): object
            {
                return (object) ['content' => 'llm response', 'usage' => []];
            }

            public function getId(): string
            {
                return 'hybrid-agent';
            }

            public function getName(): string
            {
                return 'Hybrid Agent';
            }
        };
        // Disable RAG so it does not try to discover sources or use pipeline
        $this->hybridAgent->disableRag();
    });

    describe('withHybridStrategy', function () {
        it('sets the default hybrid strategy with fluent return', function () {
            $result = $this->hybridAgent->withHybridStrategy(HybridStrategy::PARALLEL);

            expect($result)->toBe($this->hybridAgent);
            expect($this->hybridAgent->getHybridStrategy())->toBe(HybridStrategy::PARALLEL);
        });
    });

    describe('getHybridStrategy', function () {
        it('returns RAG_AUGMENTED by default', function () {
            expect($this->hybridAgent->getHybridStrategy())->toBe(HybridStrategy::RAG_AUGMENTED);
        });
    });

    describe('withRagConfidenceThreshold', function () {
        it('sets the threshold with fluent return', function () {
            $result = $this->hybridAgent->withRagConfidenceThreshold(0.8);

            expect($result)->toBe($this->hybridAgent);
            expect($this->hybridAgent->getRagConfidenceThreshold())->toBe(0.8);
        });

        it('clamps the threshold to minimum 0.0', function () {
            $this->hybridAgent->withRagConfidenceThreshold(-0.5);

            expect($this->hybridAgent->getRagConfidenceThreshold())->toBe(0.0);
        });

        it('clamps the threshold to maximum 1.0', function () {
            $this->hybridAgent->withRagConfidenceThreshold(1.5);

            expect($this->hybridAgent->getRagConfidenceThreshold())->toBe(1.0);
        });
    });

    describe('getRagConfidenceThreshold', function () {
        it('returns 0.5 by default', function () {
            expect($this->hybridAgent->getRagConfidenceThreshold())->toBe(0.5);
        });
    });

    describe('withLlmFallback', function () {
        it('enables LLM fallback with fluent return', function () {
            $result = $this->hybridAgent->withLlmFallback(true);

            expect($result)->toBe($this->hybridAgent);
            expect($this->hybridAgent->isLlmFallbackEnabled())->toBeTrue();
        });

        it('disables LLM fallback', function () {
            $this->hybridAgent->withLlmFallback(false);

            expect($this->hybridAgent->isLlmFallbackEnabled())->toBeFalse();
        });
    });

    describe('isLlmFallbackEnabled', function () {
        it('returns true by default', function () {
            expect($this->hybridAgent->isLlmFallbackEnabled())->toBeTrue();
        });
    });

    describe('hybridBuilder', function () {
        it('returns a HybridResponseBuilder for the given query', function () {
            $builder = $this->hybridAgent->hybridBuilder('test query');

            expect($builder)->toBeInstanceOf(HybridResponseBuilder::class);
        });
    });
});
