<?php

declare(strict_types=1);

use AgenticOrchestrator\Rag\Document;

describe('Document', function () {
    it('creates document with constructor', function () {
        $doc = new Document(
            id: 'test-id',
            content: 'Test content',
            metadata: ['key' => 'value'],
            source: '/path/to/file.txt',
        );

        expect($doc->id)->toBe('test-id');
        expect($doc->content)->toBe('Test content');
        expect($doc->metadata)->toBe(['key' => 'value']);
        expect($doc->source)->toBe('/path/to/file.txt');
    });

    it('creates document from text', function () {
        $doc = Document::fromText('Hello world', ['type' => 'greeting']);

        expect($doc->content)->toBe('Hello world');
        expect($doc->getMeta('type'))->toBe('greeting');
        expect($doc->id)->toStartWith('doc_');
    });

    it('creates document from file', function () {
        $doc = Document::fromFile('/path/to/file.txt', 'File content', ['extra' => 'data']);

        expect($doc->content)->toBe('File content');
        expect($doc->source)->toBe('/path/to/file.txt');
        expect($doc->getMeta('source_type'))->toBe('file');
        expect($doc->getMeta('file_name'))->toBe('file.txt');
        expect($doc->getMeta('file_path'))->toBe('/path/to/file.txt');
        expect($doc->getMeta('extra'))->toBe('data');
    });

    it('creates document from array', function () {
        $doc = Document::fromArray([
            'id' => 'array-id',
            'content' => 'Array content',
            'metadata' => ['from' => 'array'],
            'source' => '/source/path',
        ]);

        expect($doc->id)->toBe('array-id');
        expect($doc->content)->toBe('Array content');
        expect($doc->getMeta('from'))->toBe('array');
        expect($doc->source)->toBe('/source/path');
    });

    it('generates unique IDs', function () {
        $id1 = Document::generateId('content1');
        $id2 = Document::generateId('content2');

        expect($id1)->not->toBe($id2);
        expect($id1)->toStartWith('doc_');
        expect(strlen($id1))->toBe(20); // doc_ + 16 chars
    });

    it('gets metadata with default', function () {
        $doc = Document::fromText('content', ['existing' => 'value']);

        expect($doc->getMeta('existing'))->toBe('value');
        expect($doc->getMeta('missing'))->toBeNull();
        expect($doc->getMeta('missing', 'default'))->toBe('default');
    });

    it('checks for metadata key', function () {
        $doc = Document::fromText('content', ['exists' => null]);

        expect($doc->hasMeta('exists'))->toBeTrue();
        expect($doc->hasMeta('missing'))->toBeFalse();
    });

    it('gets content length', function () {
        $doc = Document::fromText('Hello');

        expect($doc->getLength())->toBe(5);
    });

    it('checks if empty', function () {
        $emptyDoc = Document::fromText('');
        $whitespaceDoc = Document::fromText('   ');
        $contentDoc = Document::fromText('content');

        expect($emptyDoc->isEmpty())->toBeTrue();
        expect($whitespaceDoc->isEmpty())->toBeTrue();
        expect($contentDoc->isEmpty())->toBeFalse();
    });

    it('creates copy with new content', function () {
        $original = Document::fromText('original', ['key' => 'value']);
        $updated = $original->withContent('updated');

        expect($original->content)->toBe('original');
        expect($updated->content)->toBe('updated');
        expect($updated->getMeta('key'))->toBe('value');
    });

    it('creates copy with additional metadata', function () {
        $original = Document::fromText('content', ['a' => 1]);
        $updated = $original->withMetadata(['b' => 2]);

        expect($original->metadata)->toBe(['a' => 1]);
        expect($updated->getMeta('a'))->toBe(1);
        expect($updated->getMeta('b'))->toBe(2);
    });

    it('creates copy with new ID', function () {
        $original = Document::fromText('content');
        $updated = $original->withId('new-id');

        expect($original->id)->not->toBe('new-id');
        expect($updated->id)->toBe('new-id');
    });

    it('creates chunks from document', function () {
        $original = Document::fromFile('/path/file.txt', 'Full content', ['key' => 'value']);
        $chunk = $original->createChunk('Chunk content', 2, 100);

        expect($chunk->id)->toBe("{$original->id}_chunk_2");
        expect($chunk->content)->toBe('Chunk content');
        expect($chunk->getMeta('parent_id'))->toBe($original->id);
        expect($chunk->getMeta('chunk_index'))->toBe(2);
        expect($chunk->getMeta('start_offset'))->toBe(100);
        expect($chunk->getMeta('is_chunk'))->toBeTrue();
        expect($chunk->getMeta('key'))->toBe('value');
    });

    it('converts to array', function () {
        $doc = new Document(
            id: 'test-id',
            content: 'Test content',
            metadata: ['key' => 'value'],
            source: '/source',
        );

        expect($doc->toArray())->toBe([
            'id' => 'test-id',
            'content' => 'Test content',
            'metadata' => ['key' => 'value'],
            'source' => '/source',
        ]);
    });

    it('serializes to JSON', function () {
        $doc = Document::fromText('content');
        $json = json_encode($doc);

        expect($json)->toContain('"content":"content"');
    });
});
