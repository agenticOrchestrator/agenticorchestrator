<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\AgentResponse;
use AgenticOrchestrator\Caching\CacheKeyGenerator;
use AgenticOrchestrator\Caching\EmbeddingCache;
use AgenticOrchestrator\Caching\ResponseCache;
use AgenticOrchestrator\Caching\ToolResultCache;
use AgenticOrchestrator\Tools\ToolResult;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

// CacheKeyGenerator Tests
test('generates consistent keys for same input', function () {
    $generator = new CacheKeyGenerator;

    $key1 = $generator->forResponse('agent', 'input', [], 'gpt-4');
    $key2 = $generator->forResponse('agent', 'input', [], 'gpt-4');

    expect($key1)->toBe($key2);
});

test('generates different keys for different inputs', function () {
    $generator = new CacheKeyGenerator;

    $key1 = $generator->forResponse('agent', 'input1', [], 'gpt-4');
    $key2 = $generator->forResponse('agent', 'input2', [], 'gpt-4');

    expect($key1)->not->toBe($key2);
});

test('generates embedding keys', function () {
    $generator = new CacheKeyGenerator;

    $key = $generator->forEmbedding('test text', 'text-embedding-3-small', 1536);

    expect($key)->toStartWith('agent_orchestrator:embedding:');
});

test('generates tool result keys', function () {
    $generator = new CacheKeyGenerator;

    $key = $generator->forToolResult('web_search', ['query' => 'test'], 123);

    expect($key)->toStartWith('agent_orchestrator:tool:');
});

test('normalizes context to remove volatile keys', function () {
    $generator = new CacheKeyGenerator;

    $key1 = $generator->forResponse('agent', 'input', [
        'data' => 'value',
        'timestamp' => 12345,
    ], null);

    $key2 = $generator->forResponse('agent', 'input', [
        'data' => 'value',
        'timestamp' => 67890,
    ], null);

    expect($key1)->toBe($key2);
});

test('sorts arguments for consistent hashing', function () {
    $generator = new CacheKeyGenerator;

    $key1 = $generator->forToolResult('tool', ['a' => 1, 'b' => 2], null);
    $key2 = $generator->forToolResult('tool', ['b' => 2, 'a' => 1], null);

    expect($key1)->toBe($key2);
});

test('custom prefix can be set', function () {
    $generator = new CacheKeyGenerator('my_prefix');

    $key = $generator->forResponse('agent', 'input', [], null);

    expect($key)->toStartWith('my_prefix:');
});

test('raw key generation', function () {
    $generator = new CacheKeyGenerator;

    $key = $generator->raw('part1', 'part2', 'part3');

    expect($key)->toBe('agent_orchestrator:part1:part2:part3');
});

// ResponseCache Tests
test('response cache stores and retrieves', function () {
    $cache = new ResponseCache;

    $response = new AgentResponse(content: 'test response');

    $cache->put('test-key', $response);
    $retrieved = $cache->get('test-key');

    expect($retrieved)->toBeInstanceOf(AgentResponse::class);
    expect($retrieved->content)->toBe('test response');
});

test('response cache remember returns cached value', function () {
    $cache = new ResponseCache;
    $callCount = 0;

    $callback = function () use (&$callCount) {
        $callCount++;

        return new AgentResponse(content: 'generated');
    };

    $result1 = $cache->remember('agent', 'input', [], 'gpt-4', $callback);
    $result2 = $cache->remember('agent', 'input', [], 'gpt-4', $callback);

    expect($callCount)->toBe(1);
    expect($result1->content)->toBe('generated');
    expect($result2->content)->toBe('generated');
});

test('response cache tracks statistics', function () {
    $cache = new ResponseCache;

    $callback = fn () => new AgentResponse(content: 'test');

    $cache->remember('agent', 'input1', [], null, $callback);
    $cache->remember('agent', 'input1', [], null, $callback); // Hit
    $cache->remember('agent', 'input2', [], null, $callback); // Miss

    $stats = $cache->getStats();

    expect($stats['hits'])->toBe(1);
    expect($stats['misses'])->toBe(2);
    expect($stats['stores'])->toBe(2);
    expect($stats['hit_rate'])->toBeGreaterThan(0);
});

test('response cache respects disabled state', function () {
    $cache = (new ResponseCache)->disable();
    $callCount = 0;

    $callback = function () use (&$callCount) {
        $callCount++;

        return new AgentResponse(content: 'generated');
    };

    $cache->remember('agent', 'input', [], null, $callback);
    $cache->remember('agent', 'input', [], null, $callback);

    expect($callCount)->toBe(2);
});

test('response cache agent-specific config', function () {
    $cache = new ResponseCache;

    $cache->configureAgent('cached-agent', ['enabled' => true, 'ttl' => 7200]);
    $cache->configureAgent('uncached-agent', ['enabled' => false]);

    $cachedCallback = fn () => new AgentResponse(content: 'cached');
    $uncachedCallback = fn () => new AgentResponse(content: 'uncached');

    $cache->remember('cached-agent', 'input', [], null, $cachedCallback);
    $cached = $cache->has('cached-agent', 'input');

    $cache->remember('uncached-agent', 'input', [], null, $uncachedCallback);
    $notCached = $cache->has('uncached-agent', 'input');

    expect($cached)->toBeTrue();
    expect($notCached)->toBeFalse();
});

// EmbeddingCache Tests
test('embedding cache stores and retrieves vectors', function () {
    $cache = new EmbeddingCache;

    $embedding = [0.1, 0.2, 0.3, 0.4, 0.5];
    $generator = new CacheKeyGenerator;
    $key = $generator->forEmbedding('test text', 'model', null);

    $cache->put($key, $embedding);
    $retrieved = $cache->get($key);

    expect($retrieved)->toBe($embedding);
});

test('embedding cache remember works', function () {
    $cache = new EmbeddingCache;
    $callCount = 0;

    $callback = function () use (&$callCount) {
        $callCount++;

        return [0.1, 0.2, 0.3];
    };

    $result1 = $cache->remember('text', 'model', 1536, $callback);
    $result2 = $cache->remember('text', 'model', 1536, $callback);

    expect($callCount)->toBe(1);
    expect($result1)->toBe([0.1, 0.2, 0.3]);
});

test('embedding cache remember many optimizes batch calls', function () {
    $cache = new EmbeddingCache;

    // Pre-cache one embedding
    $cache->remember('text1', 'model', null, fn () => [0.1, 0.2]);

    $callCount = 0;
    $callback = function ($texts) use (&$callCount) {
        $callCount++;
        $results = [];
        foreach ($texts as $text) {
            $results[$text] = [0.3, 0.4];
        }

        return $results;
    };

    $results = $cache->rememberMany(['text1', 'text2', 'text3'], 'model', null, $callback);

    // Callback should only be called for non-cached texts
    expect($callCount)->toBe(1);
    expect($results)->toHaveCount(3);
    expect($results['text1'])->toBe([0.1, 0.2]); // From cache
});

test('embedding cache tracks tokens saved', function () {
    $cache = new EmbeddingCache;

    $cache->remember('short text', 'model', null, fn () => [0.1, 0.2]);
    $cache->remember('short text', 'model', null, fn () => [0.1, 0.2]); // Hit

    $stats = $cache->getStats();

    expect($stats['tokens_saved'])->toBeGreaterThan(0);
});

// ToolResultCache Tests
test('tool result cache stores successful results', function () {
    $cache = new ToolResultCache;

    $callback = fn () => ToolResult::success(
        toolCallId: 'call-123',
        name: 'my_tool',
        arguments: ['arg' => 'value'],
        result: ['data' => 'value'],
    );

    $result = $cache->remember('my_tool', ['arg' => 'value'], null, $callback);

    expect($result->success)->toBeTrue();
    expect($cache->has('my_tool', ['arg' => 'value']))->toBeTrue();
});

test('tool result cache does not cache failures', function () {
    $cache = new ToolResultCache;

    $callback = fn () => ToolResult::failure(
        toolCallId: 'call-123',
        name: 'my_tool',
        arguments: ['arg' => 'value'],
        error: 'error occurred',
    );

    $result = $cache->remember('my_tool', ['arg' => 'value'], null, $callback);

    expect($result->success)->toBeFalse();
    expect($cache->has('my_tool', ['arg' => 'value']))->toBeFalse();
});

test('tool result cache respects tool-specific config', function () {
    $cache = new ToolResultCache;

    $cache->configureTool('cached_tool', ['enabled' => true, 'ttl' => 600]);
    $cache->configureTool('uncached_tool', ['enabled' => false]);

    $cache->remember('cached_tool', [], null, fn () => ToolResult::success(
        toolCallId: 'call-1',
        name: 'cached_tool',
        arguments: [],
        result: ['data' => 1],
    ));
    $cache->remember('uncached_tool', [], null, fn () => ToolResult::success(
        toolCallId: 'call-2',
        name: 'uncached_tool',
        arguments: [],
        result: ['data' => 2],
    ));

    expect($cache->has('cached_tool', []))->toBeTrue();
    expect($cache->has('uncached_tool', []))->toBeFalse();
});

test('tool result cache includes team scope in key', function () {
    $cache = new ToolResultCache;

    $cache->remember('tool', ['arg' => 1], 100, fn () => ToolResult::success(
        toolCallId: 'call-1',
        name: 'tool',
        arguments: ['arg' => 1],
        result: ['data' => 'team100'],
    ));
    $cache->remember('tool', ['arg' => 1], 200, fn () => ToolResult::success(
        toolCallId: 'call-2',
        name: 'tool',
        arguments: ['arg' => 1],
        result: ['data' => 'team200'],
    ));

    // Different teams should have different cache entries
    expect($cache->has('tool', ['arg' => 1], 100))->toBeTrue();
    expect($cache->has('tool', ['arg' => 1], 200))->toBeTrue();
});

test('cached responses include metadata', function () {
    $cache = new ResponseCache;

    $cache->put('test-key', new AgentResponse(content: 'test', metadata: ['original' => true]));
    $retrieved = $cache->get('test-key');

    expect($retrieved->metadata)->toHaveKey('from_cache');
    expect($retrieved->metadata['from_cache'])->toBeTrue();
    expect($retrieved->metadata)->toHaveKey('cached_at');
});

test('cache can be flushed', function () {
    $responseCache = new ResponseCache;
    $embeddingCache = new EmbeddingCache;
    $toolCache = new ToolResultCache;

    $responseCache->put('key1', new AgentResponse(content: 'test'));
    $keyGen = new CacheKeyGenerator;
    $embeddingCache->put($keyGen->forEmbedding('text', 'model', null), [0.1, 0.2]);

    $responseCache->flush();

    $stats = $responseCache->getStats();
    expect($stats['hits'])->toBe(0);
    expect($stats['misses'])->toBe(0);
});
