<?php

declare(strict_types=1);

use AgenticOrchestrator\Embeddings\Providers\OpenAIEmbeddings;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Helper to inject a faked HTTP client into an OpenAIEmbeddings instance.
 * The class creates its own HttpClient in the constructor, so Http::fake()
 * on the facade does not intercept those calls.
 */
function injectFakedHttp(OpenAIEmbeddings $provider): HttpClient
{
    $http = new HttpClient;
    $ref = new ReflectionProperty($provider, 'http');
    $ref->setValue($provider, $http);

    return $http;
}

describe('OpenAIEmbeddings', function () {
    beforeEach(function () {
        $this->provider = new OpenAIEmbeddings(
            apiKey: 'test-api-key',
            model: 'text-embedding-3-small',
            cacheTtl: 0, // Disable cache for most tests
        );
    });

    describe('constructor', function () {
        it('creates with default parameters', function () {
            $provider = new OpenAIEmbeddings(apiKey: 'key');

            expect($provider->getModel())->toBe('text-embedding-3-small')
                ->and($provider->getDimension())->toBe(1536);
        });

        it('creates with custom model', function () {
            $provider = new OpenAIEmbeddings(
                apiKey: 'key',
                model: 'text-embedding-3-large',
            );

            expect($provider->getModel())->toBe('text-embedding-3-large')
                ->and($provider->getDimension())->toBe(3072);
        });

        it('creates with custom dimensions', function () {
            $provider = new OpenAIEmbeddings(
                apiKey: 'key',
                model: 'text-embedding-3-small',
                dimensions: 512,
            );

            expect($provider->getDimension())->toBe(512);
        });
    });

    describe('fromConfig', function () {
        it('creates from config array', function () {
            $provider = OpenAIEmbeddings::fromConfig([
                'api_key' => 'config-key',
                'model' => 'text-embedding-ada-002',
                'dimensions' => null,
                'cache_ttl' => 3600,
            ]);

            expect($provider->getModel())->toBe('text-embedding-ada-002')
                ->and($provider->getDimension())->toBe(1536);
        });

        it('uses defaults for missing config values', function () {
            $provider = OpenAIEmbeddings::fromConfig([
                'api_key' => 'key',
            ]);

            expect($provider->getModel())->toBe('text-embedding-3-small');
        });
    });

    describe('getDimension', function () {
        it('returns model default for ada-002', function () {
            $provider = new OpenAIEmbeddings(apiKey: 'key', model: 'text-embedding-ada-002');

            expect($provider->getDimension())->toBe(1536);
        });

        it('returns model default for 3-small', function () {
            $provider = new OpenAIEmbeddings(apiKey: 'key', model: 'text-embedding-3-small');

            expect($provider->getDimension())->toBe(1536);
        });

        it('returns model default for 3-large', function () {
            $provider = new OpenAIEmbeddings(apiKey: 'key', model: 'text-embedding-3-large');

            expect($provider->getDimension())->toBe(3072);
        });

        it('returns custom dimension when set', function () {
            $provider = new OpenAIEmbeddings(apiKey: 'key', dimensions: 256);

            expect($provider->getDimension())->toBe(256);
        });

        it('returns fallback 1536 for unknown model', function () {
            $provider = new OpenAIEmbeddings(apiKey: 'key', model: 'unknown-model');

            expect($provider->getDimension())->toBe(1536);
        });
    });

    describe('getModel', function () {
        it('returns the configured model name', function () {
            expect($this->provider->getModel())->toBe('text-embedding-3-small');
        });
    });

    describe('getMaxInputLength', function () {
        it('returns max tokens for known models', function () {
            $provider = new OpenAIEmbeddings(apiKey: 'key', model: 'text-embedding-ada-002');

            expect($provider->getMaxInputLength())->toBe(8191);
        });

        it('returns fallback for unknown models', function () {
            $provider = new OpenAIEmbeddings(apiKey: 'key', model: 'unknown');

            expect($provider->getMaxInputLength())->toBe(8191);
        });
    });

    describe('embedBatch', function () {
        it('returns empty array for empty input', function () {
            $result = $this->provider->embedBatch([]);

            expect($result)->toBe([]);
        });

        it('sends correct payload to OpenAI API and returns embeddings', function () {
            $http = injectFakedHttp($this->provider);
            $http->fake([
                'api.openai.com/v1/embeddings' => $http->response([
                    'data' => [
                        ['index' => 0, 'embedding' => [0.1, 0.2, 0.3]],
                    ],
                ]),
            ]);

            $result = $this->provider->embedBatch(['Hello world']);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBe([0.1, 0.2, 0.3]);

            $http->assertSent(function ($request) {
                return str_contains($request->url(), 'api.openai.com/v1/embeddings')
                    && $request['model'] === 'text-embedding-3-small'
                    && $request['input'] === ['Hello world'];
            });
        });

        it('handles multiple texts and sorts by index', function () {
            $http = injectFakedHttp($this->provider);
            $http->fake([
                'api.openai.com/v1/embeddings' => $http->response([
                    'data' => [
                        ['index' => 1, 'embedding' => [0.4, 0.5]],
                        ['index' => 0, 'embedding' => [0.1, 0.2]],
                    ],
                ]),
            ]);

            $result = $this->provider->embedBatch(['text1', 'text2']);

            expect($result)->toHaveCount(2)
                ->and($result[0])->toBe([0.1, 0.2])
                ->and($result[1])->toBe([0.4, 0.5]);
        });

        it('includes dimensions for v3 models when custom dimensions set', function () {
            $provider = new OpenAIEmbeddings(
                apiKey: 'test-key',
                model: 'text-embedding-3-small',
                dimensions: 256,
                cacheTtl: 0,
            );

            $http = injectFakedHttp($provider);
            $http->fake([
                'api.openai.com/v1/embeddings' => $http->response([
                    'data' => [
                        ['index' => 0, 'embedding' => [0.1]],
                    ],
                ]),
            ]);

            $provider->embedBatch(['test']);

            $http->assertSent(function ($request) {
                return $request['dimensions'] === 256;
            });
        });

        it('does not include dimensions for non-v3 models', function () {
            $provider = new OpenAIEmbeddings(
                apiKey: 'test-key',
                model: 'text-embedding-ada-002',
                dimensions: 256,
                cacheTtl: 0,
            );

            $http = injectFakedHttp($provider);
            $http->fake([
                'api.openai.com/v1/embeddings' => $http->response([
                    'data' => [
                        ['index' => 0, 'embedding' => [0.1]],
                    ],
                ]),
            ]);

            $provider->embedBatch(['test']);

            $http->assertSent(function ($request) {
                return ! isset($request['dimensions']);
            });
        });

        it('throws on API failure', function () {
            $http = injectFakedHttp($this->provider);
            $http->fake([
                'api.openai.com/v1/embeddings' => $http->response('Server Error', 500),
            ]);

            expect(fn () => $this->provider->embedBatch(['test']))
                ->toThrow(RuntimeException::class, 'OpenAI embedding request failed');
        });
    });

    describe('embed', function () {
        it('embeds a single text', function () {
            $http = injectFakedHttp($this->provider);
            $http->fake([
                'api.openai.com/v1/embeddings' => $http->response([
                    'data' => [
                        ['index' => 0, 'embedding' => [0.1, 0.2, 0.3]],
                    ],
                ]),
            ]);

            $result = $this->provider->embed('Hello');

            expect($result)->toBe([0.1, 0.2, 0.3]);
        });

        it('uses cache when enabled', function () {
            $provider = new OpenAIEmbeddings(
                apiKey: 'test-key',
                model: 'text-embedding-3-small',
                cacheTtl: 3600,
            );

            $cacheKey = sprintf(
                'embeddings:text-embedding-3-small:default:%s',
                md5('cached text')
            );

            Cache::shouldReceive('has')->with($cacheKey)->once()->andReturn(true);
            Cache::shouldReceive('get')->with($cacheKey)->once()->andReturn([0.5, 0.6]);

            $result = $provider->embed('cached text');

            expect($result)->toBe([0.5, 0.6]);
        });

        it('stores result in cache when enabled', function () {
            $provider = new OpenAIEmbeddings(
                apiKey: 'test-key',
                model: 'text-embedding-3-small',
                cacheTtl: 3600,
            );

            $http = injectFakedHttp($provider);
            $http->fake([
                'api.openai.com/v1/embeddings' => $http->response([
                    'data' => [
                        ['index' => 0, 'embedding' => [0.1, 0.2]],
                    ],
                ]),
            ]);

            $cacheKey = sprintf(
                'embeddings:text-embedding-3-small:default:%s',
                md5('new text')
            );

            Cache::shouldReceive('has')->with($cacheKey)->once()->andReturn(false);
            Cache::shouldReceive('put')->with($cacheKey, [0.1, 0.2], 3600)->once();

            $result = $provider->embed('new text');

            expect($result)->toBe([0.1, 0.2]);
        });
    });

    describe('clearCache', function () {
        it('clears cache for a specific text', function () {
            $cacheKey = sprintf(
                'embeddings:text-embedding-3-small:default:%s',
                md5('some text')
            );

            Cache::shouldReceive('forget')->with($cacheKey)->once();

            $this->provider->clearCache('some text');
        });
    });
});
