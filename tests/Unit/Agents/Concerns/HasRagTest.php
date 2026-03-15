<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\Concerns\HasRag;
use AgenticOrchestrator\Rag\RagPipeline;
use AgenticOrchestrator\Rag\RagPipelineResult;

describe('HasRag', function () {

    beforeEach(function () {
        $this->ragAgent = new class
        {
            use HasRag;

            public function getId(): string
            {
                return 'rag-test-agent';
            }

            public function getName(): string
            {
                return 'RAG Test Agent';
            }
        };
    });

    describe('withRag', function () {
        it('sets the RAG pipeline with fluent return', function () {
            $pipeline = Mockery::mock(RagPipeline::class);

            $result = $this->ragAgent->withRag($pipeline);

            expect($result)->toBe($this->ragAgent);
            expect($this->ragAgent->getRagPipeline())->toBe($pipeline);
        });
    });

    describe('getRagPipeline', function () {
        it('returns null by default', function () {
            expect($this->ragAgent->getRagPipeline())->toBeNull();
        });

        it('returns the set pipeline', function () {
            $pipeline = Mockery::mock(RagPipeline::class);
            $this->ragAgent->withRag($pipeline);

            expect($this->ragAgent->getRagPipeline())->toBe($pipeline);
        });
    });

    describe('enableRag / disableRag', function () {
        it('enables RAG with fluent return', function () {
            $this->ragAgent->disableRag();
            $result = $this->ragAgent->enableRag();

            expect($result)->toBe($this->ragAgent);
        });

        it('disables RAG with fluent return', function () {
            $result = $this->ragAgent->disableRag();

            expect($result)->toBe($this->ragAgent);
        });
    });

    describe('isRagEnabled', function () {
        it('returns false when no pipeline and no sources', function () {
            expect($this->ragAgent->isRagEnabled())->toBeFalse();
        });

        it('returns true when pipeline is set and rag is enabled', function () {
            $pipeline = Mockery::mock(RagPipeline::class);
            $this->ragAgent->withRag($pipeline);

            expect($this->ragAgent->isRagEnabled())->toBeTrue();
        });

        it('returns false when rag is disabled even with pipeline', function () {
            $pipeline = Mockery::mock(RagPipeline::class);
            $this->ragAgent->withRag($pipeline)->disableRag();

            expect($this->ragAgent->isRagEnabled())->toBeFalse();
        });
    });

    describe('retrieveRagContext', function () {
        it('returns empty result when no pipeline is set', function () {
            $result = $this->ragAgent->retrieveRagContext('test query');

            expect($result)->toBeInstanceOf(RagPipelineResult::class);
            expect($result->isEmpty())->toBeTrue();
        });

        it('queries the pipeline when set', function () {
            $expectedResult = new RagPipelineResult;
            $pipeline = Mockery::mock(RagPipeline::class);
            $pipeline->shouldReceive('query')
                ->once()
                ->with('test query')
                ->andReturn($expectedResult);

            $this->ragAgent->withRag($pipeline);

            $result = $this->ragAgent->retrieveRagContext('test query');

            expect($result)->toBe($expectedResult);
        });
    });

    describe('getRagSources', function () {
        it('returns empty array when no RagSource attributes are defined', function () {
            expect($this->ragAgent->getRagSources())->toBe([]);
        });

        it('caches the discovered sources', function () {
            $first = $this->ragAgent->getRagSources();
            $second = $this->ragAgent->getRagSources();

            expect($first)->toBe($second);
        });
    });

    describe('hasRagSources', function () {
        it('returns false when no sources are configured', function () {
            expect($this->ragAgent->hasRagSources())->toBeFalse();
        });
    });

    describe('ingestDocuments', function () {
        it('throws RuntimeException when no pipeline is configured', function () {
            expect(fn () => $this->ragAgent->ingestDocuments([]))
                ->toThrow(RuntimeException::class, 'RAG pipeline is not configured');
        });
    });

    describe('ingestFromPath', function () {
        it('throws RuntimeException when no pipeline is configured', function () {
            expect(fn () => $this->ragAgent->ingestFromPath('/tmp/docs'))
                ->toThrow(RuntimeException::class, 'RAG pipeline is not configured');
        });
    });

    describe('ingestText', function () {
        it('throws RuntimeException when no pipeline is configured', function () {
            expect(fn () => $this->ragAgent->ingestText('some content'))
                ->toThrow(RuntimeException::class, 'RAG pipeline is not configured');
        });
    });

    describe('getFormattedRagContext', function () {
        it('returns empty string when RAG is not enabled', function () {
            $result = $this->ragAgent->getFormattedRagContext('test query');

            expect($result)->toBe('');
        });
    });
});
