<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Embeddings;

use AgenticOrchestrator\Embeddings\Stores\ArrayVectorStore;
use AgenticOrchestrator\Embeddings\VectorDocument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayVectorStore::class)]
class ArrayVectorStoreTest extends TestCase
{
    private ArrayVectorStore $store;

    protected function setUp(): void
    {
        $this->store = new ArrayVectorStore;
    }

    #[Test]
    public function it_stores_and_retrieves_document(): void
    {
        $this->store->upsert(
            id: 'doc-1',
            embedding: [0.1, 0.2, 0.3],
            content: 'Hello world',
            metadata: ['source' => 'test'],
        );

        $doc = $this->store->get('doc-1');

        $this->assertNotNull($doc);
        $this->assertSame('Hello world', $doc->content);
        $this->assertSame('test', $doc->getMeta('source'));
    }

    #[Test]
    public function it_updates_existing_document(): void
    {
        $this->store->upsert('doc-1', [0.1], 'Original');
        $this->store->upsert('doc-1', [0.2], 'Updated');

        $doc = $this->store->get('doc-1');

        $this->assertSame('Updated', $doc->content);
        $this->assertSame([0.2], $doc->embedding);
    }

    #[Test]
    public function it_stores_batch_of_documents(): void
    {
        $docs = [
            new VectorDocument('doc-1', 'Content 1', [0.1, 0.2]),
            new VectorDocument('doc-2', 'Content 2', [0.3, 0.4]),
        ];

        $this->store->upsertBatch($docs);

        $this->assertSame(2, $this->store->count());
        $this->assertTrue($this->store->exists('doc-1'));
        $this->assertTrue($this->store->exists('doc-2'));
    }

    #[Test]
    public function it_searches_by_similarity(): void
    {
        // Store documents with distinct embeddings
        $this->store->upsert('doc-1', [1.0, 0.0], 'Document 1');
        $this->store->upsert('doc-2', [0.9, 0.1], 'Document 2');
        $this->store->upsert('doc-3', [0.0, 1.0], 'Document 3');

        // Search for something similar to doc-1
        $results = $this->store->search([1.0, 0.0], limit: 2);

        $this->assertCount(2, $results);
        $this->assertSame('doc-1', $results[0]->getId()); // Most similar
        $this->assertSame('doc-2', $results[1]->getId()); // Second most similar
    }

    #[Test]
    public function it_filters_search_by_metadata(): void
    {
        $this->store->upsert('doc-1', [1.0, 0.0], 'Doc 1', ['category' => 'A']);
        $this->store->upsert('doc-2', [0.9, 0.1], 'Doc 2', ['category' => 'B']);
        $this->store->upsert('doc-3', [0.8, 0.2], 'Doc 3', ['category' => 'A']);

        $results = $this->store->search(
            embedding: [1.0, 0.0],
            filter: ['category' => 'A'],
        );

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertSame('A', $result->getMeta('category'));
        }
    }

    #[Test]
    public function it_deletes_document(): void
    {
        $this->store->upsert('doc-1', [0.1], 'Content');

        $deleted = $this->store->delete('doc-1');

        $this->assertTrue($deleted);
        $this->assertFalse($this->store->exists('doc-1'));
    }

    #[Test]
    public function it_returns_false_when_deleting_nonexistent(): void
    {
        $deleted = $this->store->delete('nonexistent');

        $this->assertFalse($deleted);
    }

    #[Test]
    public function it_deletes_batch_of_documents(): void
    {
        $this->store->upsert('doc-1', [0.1], 'Content 1');
        $this->store->upsert('doc-2', [0.2], 'Content 2');
        $this->store->upsert('doc-3', [0.3], 'Content 3');

        $count = $this->store->deleteBatch(['doc-1', 'doc-2']);

        $this->assertSame(2, $count);
        $this->assertFalse($this->store->exists('doc-1'));
        $this->assertFalse($this->store->exists('doc-2'));
        $this->assertTrue($this->store->exists('doc-3'));
    }

    #[Test]
    public function it_deletes_by_filter(): void
    {
        $this->store->upsert('doc-1', [0.1], 'Doc 1', ['type' => 'temp']);
        $this->store->upsert('doc-2', [0.2], 'Doc 2', ['type' => 'temp']);
        $this->store->upsert('doc-3', [0.3], 'Doc 3', ['type' => 'perm']);

        $count = $this->store->deleteByFilter(['type' => 'temp']);

        $this->assertSame(2, $count);
        $this->assertSame(1, $this->store->count());
        $this->assertTrue($this->store->exists('doc-3'));
    }

    #[Test]
    public function it_clears_all_documents(): void
    {
        $this->store->upsert('doc-1', [0.1], 'Content 1');
        $this->store->upsert('doc-2', [0.2], 'Content 2');

        $this->store->clear();

        $this->assertSame(0, $this->store->count());
    }

    #[Test]
    public function it_returns_null_for_nonexistent_document(): void
    {
        $doc = $this->store->get('nonexistent');

        $this->assertNull($doc);
    }

    #[Test]
    public function it_returns_empty_results_for_empty_store(): void
    {
        $results = $this->store->search([0.1, 0.2]);

        $this->assertEmpty($results);
    }

    #[Test]
    public function it_supports_in_filter_operation(): void
    {
        $this->store->upsert('doc-1', [0.1], 'Doc 1', ['status' => 'active']);
        $this->store->upsert('doc-2', [0.2], 'Doc 2', ['status' => 'pending']);
        $this->store->upsert('doc-3', [0.3], 'Doc 3', ['status' => 'inactive']);

        $results = $this->store->search(
            embedding: [0.1],
            filter: ['status' => ['active', 'pending']],
        );

        $this->assertCount(2, $results);
    }
}
