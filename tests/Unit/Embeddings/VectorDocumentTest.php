<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Embeddings;

use AgenticOrchestrator\Embeddings\VectorDocument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(VectorDocument::class)]
class VectorDocumentTest extends TestCase
{
    #[Test]
    public function it_creates_document(): void
    {
        $doc = new VectorDocument(
            id: 'doc-1',
            content: 'Hello world',
            embedding: [0.1, 0.2, 0.3],
            metadata: ['source' => 'test'],
        );

        $this->assertSame('doc-1', $doc->id);
        $this->assertSame('Hello world', $doc->content);
        $this->assertSame([0.1, 0.2, 0.3], $doc->embedding);
        $this->assertSame('test', $doc->getMeta('source'));
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $doc = VectorDocument::fromArray([
            'id' => 'doc-2',
            'content' => 'Test content',
            'embedding' => [0.5, 0.6],
            'metadata' => ['type' => 'document'],
        ]);

        $this->assertSame('doc-2', $doc->id);
        $this->assertSame('Test content', $doc->content);
        $this->assertSame('document', $doc->getMeta('type'));
    }

    #[Test]
    public function it_returns_default_for_missing_metadata(): void
    {
        $doc = new VectorDocument(id: 'doc-1', content: 'Test');

        $this->assertNull($doc->getMeta('missing'));
        $this->assertSame('default', $doc->getMeta('missing', 'default'));
    }

    #[Test]
    public function it_checks_metadata_existence(): void
    {
        $doc = new VectorDocument(
            id: 'doc-1',
            content: 'Test',
            metadata: ['key' => 'value'],
        );

        $this->assertTrue($doc->hasMeta('key'));
        $this->assertFalse($doc->hasMeta('missing'));
    }

    #[Test]
    public function it_creates_copy_with_embedding(): void
    {
        $doc = new VectorDocument(id: 'doc-1', content: 'Test');
        $newDoc = $doc->withEmbedding([0.1, 0.2]);

        $this->assertEmpty($doc->embedding);
        $this->assertSame([0.1, 0.2], $newDoc->embedding);
        $this->assertSame('doc-1', $newDoc->id);
    }

    #[Test]
    public function it_creates_copy_with_metadata(): void
    {
        $doc = new VectorDocument(
            id: 'doc-1',
            content: 'Test',
            metadata: ['a' => 1],
        );

        $newDoc = $doc->withMetadata(['b' => 2]);

        $this->assertSame(1, $newDoc->getMeta('a'));
        $this->assertSame(2, $newDoc->getMeta('b'));
    }

    #[Test]
    public function it_gets_embedding_dimension(): void
    {
        $doc = new VectorDocument(
            id: 'doc-1',
            content: 'Test',
            embedding: [0.1, 0.2, 0.3, 0.4],
        );

        $this->assertSame(4, $doc->getDimension());
    }

    #[Test]
    public function it_checks_if_has_embedding(): void
    {
        $docWithEmbedding = new VectorDocument(
            id: 'doc-1',
            content: 'Test',
            embedding: [0.1],
        );

        $docWithoutEmbedding = new VectorDocument(id: 'doc-2', content: 'Test');

        $this->assertTrue($docWithEmbedding->hasEmbedding());
        $this->assertFalse($docWithoutEmbedding->hasEmbedding());
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $doc = new VectorDocument(
            id: 'doc-1',
            content: 'Test',
            embedding: [0.1],
            metadata: ['key' => 'value'],
        );

        $array = $doc->toArray();

        $this->assertSame('doc-1', $array['id']);
        $this->assertSame('Test', $array['content']);
        $this->assertSame([0.1], $array['embedding']);
        $this->assertSame(['key' => 'value'], $array['metadata']);
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $doc = new VectorDocument(id: 'doc-1', content: 'Test');
        $json = json_encode($doc);

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('doc-1', $decoded['id']);
    }
}
