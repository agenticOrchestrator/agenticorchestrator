<?php

declare(strict_types=1);

use AgenticOrchestrator\Embeddings\VectorDocument;
use AgenticOrchestrator\Embeddings\VectorSearchResult;
use Illuminate\Contracts\Support\Arrayable;

describe('VectorSearchResult', function () {
    beforeEach(function () {
        $this->document = new VectorDocument(
            id: 'doc-1',
            content: 'Sample content',
            embedding: [0.1, 0.2, 0.3],
            metadata: ['source' => 'test', 'category' => 'article'],
        );

        $this->result = new VectorSearchResult(
            document: $this->document,
            score: 0.95,
            distance: 0.05,
        );
    });

    describe('constructor', function () {
        it('creates with all parameters', function () {
            expect($this->result->document)->toBe($this->document)
                ->and($this->result->score)->toBe(0.95)
                ->and($this->result->distance)->toBe(0.05);
        });

        it('creates without distance (defaults to null)', function () {
            $result = new VectorSearchResult(
                document: $this->document,
                score: 0.8,
            );

            expect($result->distance)->toBeNull();
        });
    });

    describe('fromArray', function () {
        it('creates from array with document key', function () {
            $result = VectorSearchResult::fromArray([
                'document' => [
                    'id' => 'doc-2',
                    'content' => 'From array',
                    'embedding' => [0.5],
                    'metadata' => ['type' => 'test'],
                ],
                'score' => 0.88,
                'distance' => 0.12,
            ]);

            expect($result->score)->toBe(0.88)
                ->and($result->distance)->toBe(0.12)
                ->and($result->document->id)->toBe('doc-2')
                ->and($result->document->content)->toBe('From array');
        });

        it('creates from flat array without document key', function () {
            $result = VectorSearchResult::fromArray([
                'id' => 'doc-3',
                'content' => 'Flat data',
                'score' => 0.7,
            ]);

            expect($result->score)->toBe(0.7)
                ->and($result->document->id)->toBe('doc-3');
        });

        it('defaults score to 0.0 when not provided', function () {
            $result = VectorSearchResult::fromArray([
                'id' => 'doc-4',
                'content' => 'No score',
            ]);

            expect($result->score)->toBe(0.0);
        });

        it('defaults distance to null when not provided', function () {
            $result = VectorSearchResult::fromArray([
                'id' => 'doc-5',
                'content' => 'No distance',
                'score' => 0.5,
            ]);

            expect($result->distance)->toBeNull();
        });
    });

    describe('getId', function () {
        it('returns the document id', function () {
            expect($this->result->getId())->toBe('doc-1');
        });
    });

    describe('getContent', function () {
        it('returns the document content', function () {
            expect($this->result->getContent())->toBe('Sample content');
        });
    });

    describe('getMetadata', function () {
        it('returns the document metadata', function () {
            expect($this->result->getMetadata())->toBe([
                'source' => 'test',
                'category' => 'article',
            ]);
        });
    });

    describe('getMeta', function () {
        it('returns a specific metadata value', function () {
            expect($this->result->getMeta('source'))->toBe('test');
        });

        it('returns default when key not found', function () {
            expect($this->result->getMeta('missing', 'fallback'))->toBe('fallback');
        });

        it('returns null as default when key not found', function () {
            expect($this->result->getMeta('missing'))->toBeNull();
        });
    });

    describe('isAboveThreshold', function () {
        it('returns true when score is above threshold', function () {
            expect($this->result->isAboveThreshold(0.9))->toBeTrue();
        });

        it('returns true when score equals threshold', function () {
            expect($this->result->isAboveThreshold(0.95))->toBeTrue();
        });

        it('returns false when score is below threshold', function () {
            expect($this->result->isAboveThreshold(0.99))->toBeFalse();
        });
    });

    describe('toArray', function () {
        it('converts to array', function () {
            $array = $this->result->toArray();

            expect($array)->toHaveKeys(['document', 'score', 'distance'])
                ->and($array['score'])->toBe(0.95)
                ->and($array['distance'])->toBe(0.05)
                ->and($array['document'])->toBe($this->document->toArray());
        });
    });

    describe('jsonSerialize', function () {
        it('returns same as toArray', function () {
            expect($this->result->jsonSerialize())->toBe($this->result->toArray());
        });

        it('serializes to valid JSON', function () {
            $json = json_encode($this->result);

            expect($json)->toBeString();

            $decoded = json_decode($json, true);

            expect($decoded['score'])->toBe(0.95)
                ->and($decoded['document']['id'])->toBe('doc-1');
        });
    });

    describe('interface implementations', function () {
        it('implements Arrayable', function () {
            expect($this->result)->toBeInstanceOf(Arrayable::class);
        });

        it('implements JsonSerializable', function () {
            expect($this->result)->toBeInstanceOf(JsonSerializable::class);
        });
    });
});
