<?php

declare(strict_types=1);

use AgenticOrchestrator\Rag\RagConfig;

describe('RagConfig', function () {
    it('creates config with defaults', function () {
        $config = new RagConfig;

        expect($config->namespace)->toBe('default');
        expect($config->chunkSize)->toBe(1000);
        expect($config->chunkOverlap)->toBe(200);
        expect($config->chunker)->toBe('recursive');
        expect($config->retriever)->toBe('vector');
        expect($config->retrieveLimit)->toBe(5);
        expect($config->scoreThreshold)->toBe(0.7);
        expect($config->tenantId)->toBeNull();
    });

    it('creates config with custom values', function () {
        $config = new RagConfig(
            namespace: 'custom',
            chunkSize: 500,
            chunkOverlap: 100,
            chunker: 'fixed',
            retriever: 'hybrid',
            retrieveLimit: 10,
            scoreThreshold: 0.8,
            tenantId: 'tenant-1',
            extra: ['custom' => 'value'],
        );

        expect($config->namespace)->toBe('custom');
        expect($config->chunkSize)->toBe(500);
        expect($config->chunkOverlap)->toBe(100);
        expect($config->chunker)->toBe('fixed');
        expect($config->retriever)->toBe('hybrid');
        expect($config->retrieveLimit)->toBe(10);
        expect($config->scoreThreshold)->toBe(0.8);
        expect($config->tenantId)->toBe('tenant-1');
        expect($config->extra)->toBe(['custom' => 'value']);
    });

    it('creates from config array', function () {
        $config = RagConfig::fromConfig([
            'namespace' => 'from-config',
            'chunking' => ['size' => 800, 'overlap' => 150],
            'retrieval' => ['limit' => 8, 'threshold' => 0.75],
            'default_chunker' => 'fixed',
            'default_retriever' => 'hybrid',
            'tenant_id' => 't1',
        ]);

        expect($config->namespace)->toBe('from-config');
        expect($config->chunkSize)->toBe(800);
        expect($config->chunkOverlap)->toBe(150);
        expect($config->retrieveLimit)->toBe(8);
        expect($config->scoreThreshold)->toBe(0.75);
        expect($config->chunker)->toBe('fixed');
        expect($config->retriever)->toBe('hybrid');
        expect($config->tenantId)->toBe('t1');
    });

    it('creates copy with namespace', function () {
        $original = new RagConfig(namespace: 'original');
        $updated = $original->withNamespace('updated');

        expect($original->namespace)->toBe('original');
        expect($updated->namespace)->toBe('updated');
    });

    it('creates copy with chunk size', function () {
        $original = new RagConfig(chunkSize: 1000);
        $updated = $original->withChunkSize(500);

        expect($original->chunkSize)->toBe(1000);
        expect($updated->chunkSize)->toBe(500);
    });

    it('creates copy with chunk overlap', function () {
        $original = new RagConfig(chunkOverlap: 200);
        $updated = $original->withChunkOverlap(100);

        expect($original->chunkOverlap)->toBe(200);
        expect($updated->chunkOverlap)->toBe(100);
    });

    it('creates copy with retrieve limit', function () {
        $original = new RagConfig(retrieveLimit: 5);
        $updated = $original->withRetrieveLimit(10);

        expect($original->retrieveLimit)->toBe(5);
        expect($updated->retrieveLimit)->toBe(10);
    });

    it('creates copy with score threshold', function () {
        $original = new RagConfig(scoreThreshold: 0.7);
        $updated = $original->withScoreThreshold(0.9);

        expect($original->scoreThreshold)->toBe(0.7);
        expect($updated->scoreThreshold)->toBe(0.9);
    });

    it('creates copy with tenant ID', function () {
        $original = new RagConfig;
        $updated = $original->withTenantId('tenant-123');

        expect($original->tenantId)->toBeNull();
        expect($updated->tenantId)->toBe('tenant-123');
    });

    it('gets effective namespace without tenant', function () {
        $config = new RagConfig(namespace: 'knowledge');

        expect($config->getEffectiveNamespace())->toBe('knowledge');
    });

    it('gets effective namespace with tenant', function () {
        $config = new RagConfig(
            namespace: 'knowledge',
            tenantId: '42',
        );

        expect($config->getEffectiveNamespace())->toBe('tenant_42_knowledge');
    });

    it('gets extra config value', function () {
        $config = new RagConfig(extra: ['key' => 'value']);

        expect($config->getExtra('key'))->toBe('value');
        expect($config->getExtra('missing'))->toBeNull();
        expect($config->getExtra('missing', 'default'))->toBe('default');
    });

    it('converts to array', function () {
        $config = new RagConfig(
            namespace: 'test',
            chunkSize: 500,
            tenantId: 't1',
        );

        $array = $config->toArray();

        expect($array)->toHaveKey('namespace', 'test');
        expect($array)->toHaveKey('chunk_size', 500);
        expect($array)->toHaveKey('tenant_id', 't1');
    });

    it('serializes to JSON', function () {
        $config = new RagConfig(namespace: 'json-test');
        $json = json_encode($config);

        expect($json)->toContain('"namespace":"json-test"');
    });
});
