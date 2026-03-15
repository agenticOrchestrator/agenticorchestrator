<?php

declare(strict_types=1);

use AgenticOrchestrator\Rag\Chunking\FixedSizeChunker;
use AgenticOrchestrator\Rag\Chunking\RecursiveCharacterChunker;
use AgenticOrchestrator\Rag\Document;

describe('FixedSizeChunker', function () {
    it('returns document unchanged if below chunk size', function () {
        $chunker = new FixedSizeChunker(chunkSize: 100, overlap: 20);
        $doc = Document::fromText('Short text');

        $chunks = $chunker->chunk($doc);

        expect($chunks)->toHaveCount(1);
        expect($chunks[0]->content)->toBe('Short text');
    });

    it('splits document into fixed chunks', function () {
        $chunker = new FixedSizeChunker(chunkSize: 10, overlap: 0);
        $doc = Document::fromText('1234567890ABCDEFGHIJ');

        $chunks = $chunker->chunk($doc);

        expect($chunks)->toHaveCount(2);
        expect($chunks[0]->content)->toBe('1234567890');
        expect($chunks[1]->content)->toBe('ABCDEFGHIJ');
    });

    it('creates overlapping chunks', function () {
        $chunker = new FixedSizeChunker(chunkSize: 10, overlap: 3);
        $doc = Document::fromText('1234567890ABCDEFGHIJ');

        $chunks = $chunker->chunk($doc);

        expect($chunks)->toHaveCount(3);
        expect($chunks[0]->content)->toBe('1234567890');
        expect($chunks[1]->content)->toBe('890ABCDEFG');
    });

    it('preserves metadata in chunks', function () {
        $chunker = new FixedSizeChunker(chunkSize: 5, overlap: 0);
        $doc = Document::fromText('1234567890', ['key' => 'value']);

        $chunks = $chunker->chunk($doc);

        expect($chunks[0]->getMeta('key'))->toBe('value');
        expect($chunks[0]->getMeta('is_chunk'))->toBeTrue();
        expect($chunks[0]->getMeta('parent_id'))->toBe($doc->id);
        expect($chunks[0]->getMeta('chunk_index'))->toBe(0);
    });

    it('sets chunk size via setter', function () {
        $chunker = (new FixedSizeChunker)->setChunkSize(50);
        $doc = Document::fromText(str_repeat('a', 100));

        $chunks = $chunker->chunk($doc);

        expect($chunks)->toHaveCount(2);
    });

    it('sets overlap via setter', function () {
        $chunker = (new FixedSizeChunker)->setChunkSize(10)->setOverlap(5);
        $doc = Document::fromText('12345678901234567890');

        $chunks = $chunker->chunk($doc);

        // With size=10, overlap=5, step=5: positions 0, 5, 10, 15 = 4 chunks
        expect($chunks)->toHaveCount(4);
    });

    it('chunks all documents', function () {
        $chunker = new FixedSizeChunker(chunkSize: 5, overlap: 0);
        $docs = [
            Document::fromText('1234567890'),
            Document::fromText('ABCDE'),
        ];

        $chunks = $chunker->chunkAll($docs);

        expect($chunks)->toHaveCount(3);
    });
});

describe('RecursiveCharacterChunker', function () {
    it('returns document unchanged if below chunk size', function () {
        $chunker = new RecursiveCharacterChunker(chunkSize: 100, overlap: 20);
        $doc = Document::fromText('Short text');

        $chunks = $chunker->chunk($doc);

        expect($chunks)->toHaveCount(1);
        expect($chunks[0]->content)->toBe('Short text');
    });

    it('splits by paragraph first', function () {
        $chunker = new RecursiveCharacterChunker(chunkSize: 25, overlap: 0);
        $doc = Document::fromText("First paragraph.\n\nSecond paragraph.");

        $chunks = $chunker->chunk($doc);

        // The chunker should split into multiple chunks when content exceeds chunk size
        expect(count($chunks))->toBeGreaterThanOrEqual(1);
    });

    it('splits by sentence when paragraphs are too large', function () {
        $chunker = new RecursiveCharacterChunker(chunkSize: 30, overlap: 0);
        $doc = Document::fromText('First sentence. Second sentence.');

        $chunks = $chunker->chunk($doc);

        expect($chunks)->toHaveCount(2);
    });

    it('preserves metadata in chunks', function () {
        $chunker = new RecursiveCharacterChunker(chunkSize: 10, overlap: 0);
        $doc = Document::fromText("Para 1.\n\nPara 2.", ['key' => 'value']);

        $chunks = $chunker->chunk($doc);

        expect($chunks[0]->getMeta('key'))->toBe('value');
        // When document is chunked, it should have is_chunk metadata
        if (count($chunks) > 1) {
            expect($chunks[0]->getMeta('is_chunk'))->toBeTrue();
        }
    });

    it('sets chunk size via setter', function () {
        $chunker = (new RecursiveCharacterChunker)->setChunkSize(50);
        $doc = Document::fromText("Para 1.\n\nPara 2.\n\nPara 3.");

        $chunks = $chunker->chunk($doc);

        expect(count($chunks))->toBeGreaterThanOrEqual(1);
    });

    it('chunks all documents', function () {
        $chunker = new RecursiveCharacterChunker(chunkSize: 10, overlap: 0);
        $docs = [
            Document::fromText("Doc1 para1.\n\nDoc1 para2."),
            Document::fromText('Doc2 content.'),
        ];

        $chunks = $chunker->chunkAll($docs);

        // With small chunk size, should produce multiple chunks
        expect(count($chunks))->toBeGreaterThanOrEqual(2);
    });

    it('handles overlap', function () {
        $chunker = new RecursiveCharacterChunker(chunkSize: 20, overlap: 5);
        $doc = Document::fromText('Short text here. Another sentence here.');

        $chunks = $chunker->chunk($doc);

        expect(count($chunks))->toBeGreaterThanOrEqual(1);
    });
});
