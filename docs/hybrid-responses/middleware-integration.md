# Middleware Integration Guide

This guide shows how to integrate HybridResponse into your Laravel application's HTTP layer, including controllers, middleware, and API responses.

> **Related**: [Overview & API Reference](README.md) | [Strategies Guide](strategies.md) | [Agent Integration](../rag/agent-integration.md)

## Basic Controller Integration

### Simple Endpoint

```php
<?php

namespace App\Http\Controllers;

use AgenticOrchestrator\Agents\Concerns\HasHybridResponse;
use AgenticOrchestrator\Responses\HybridStrategy;
use App\Agents\CustomerSupportAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AskController extends Controller
{
    public function __invoke(Request $request, CustomerSupportAgent $agent): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|max:1000',
            'strategy' => 'nullable|string|in:rag_only,llm_only,rag_augmented,parallel',
        ]);

        $strategy = $validated['strategy']
            ? HybridStrategy::from($validated['strategy'])
            : null;

        $response = $agent->respondHybrid(
            message: $validated['question'],
            strategy: $strategy,
        );

        return response()->json($response->toApiResponse());
    }
}
```

### Response Formats

```php
class AskController extends Controller
{
    public function ask(Request $request, CustomerSupportAgent $agent): JsonResponse
    {
        $response = $agent->respondHybrid($request->input('question'));

        // Format based on accept header or query param
        return match ($request->input('format', 'standard')) {
            'full' => $this->fullResponse($response),
            'simple' => $this->simpleResponse($response),
            'attributed' => $this->attributedResponse($response),
            default => response()->json($response->toApiResponse()),
        };
    }

    protected function fullResponse($response): JsonResponse
    {
        return response()->json($response->toArray());
    }

    protected function simpleResponse($response): JsonResponse
    {
        return response()->json([
            'answer' => $response->getContent(),
            'sources' => $response->getSources(),
        ]);
    }

    protected function attributedResponse($response): JsonResponse
    {
        return response()->json([
            'segments' => $response->getAttributedContent(),
            'strategy' => $response->strategy->value,
        ]);
    }
}
```

## Response Transformer

Create a dedicated transformer for consistent API responses:

```php
<?php

namespace App\Http\Resources;

use AgenticOrchestrator\Responses\HybridResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HybridResponseResource extends JsonResource
{
    /**
     * @param  HybridResponse  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        /** @var HybridResponse $response */
        $response = $this->resource;

        return [
            'data' => [
                'content' => $response->getContent(),
                'segments' => $this->when(
                    $request->boolean('include_segments'),
                    fn() => $response->getAttributedContent()
                ),
            ],
            'meta' => [
                'strategy' => $response->strategy->value,
                'is_hybrid' => $response->isHybrid(),
                'sources' => $response->getSources(),
                'confidence' => $response->getAverageRagConfidence(),
                'latency_ms' => $response->getTotalLatencyMs(),
                'tokens' => $this->when(
                    $response->getTotalTokens() > 0,
                    $response->getTotalTokens()
                ),
            ],
            'debug' => $this->when(
                config('app.debug'),
                fn() => [
                    'latency_breakdown' => $response->getLatencyBreakdown(),
                    'segment_count' => $response->segmentCount(),
                    'usage' => $response->usage,
                ]
            ),
        ];
    }
}
```

Usage:

```php
public function ask(Request $request, CustomerSupportAgent $agent): HybridResponseResource
{
    $response = $agent->respondHybrid($request->input('question'));

    return new HybridResponseResource($response);
}
```

## Middleware

### Rate Limiting by Strategy

```php
<?php

namespace App\Http\Middleware;

use AgenticOrchestrator\Responses\HybridStrategy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class HybridResponseRateLimiter
{
    public function handle(Request $request, Closure $next)
    {
        $strategy = $request->input('strategy', 'rag_augmented');
        $hybridStrategy = HybridStrategy::tryFrom($strategy) ?? HybridStrategy::RAG_AUGMENTED;

        // Different rate limits based on cost
        $limiterKey = $this->getLimiterKey($request, $hybridStrategy);
        $maxAttempts = $this->getMaxAttempts($hybridStrategy);

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            return response()->json([
                'error' => 'Too many requests',
                'retry_after' => RateLimiter::availableIn($limiterKey),
            ], 429);
        }

        RateLimiter::hit($limiterKey, 60);

        return $next($request);
    }

    protected function getLimiterKey(Request $request, HybridStrategy $strategy): string
    {
        $userId = $request->user()?->id ?? $request->ip();
        return "hybrid_response:{$userId}:{$strategy->value}";
    }

    protected function getMaxAttempts(HybridStrategy $strategy): int
    {
        return match ($strategy) {
            HybridStrategy::RAG_ONLY => 100,      // Cheap
            HybridStrategy::RAG_WITH_FALLBACK => 60,
            HybridStrategy::RAG_AUGMENTED => 30,
            HybridStrategy::PARALLEL => 20,
            HybridStrategy::LLM_ONLY => 30,
            HybridStrategy::LLM_WITH_VERIFICATION => 15,  // Expensive
        };
    }
}
```

### Response Caching

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CacheHybridResponse
{
    public function handle(Request $request, Closure $next)
    {
        // Only cache GET requests with RAG_ONLY strategy
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        $strategy = $request->input('strategy');
        if ($strategy !== 'rag_only') {
            return $next($request);
        }

        $cacheKey = $this->getCacheKey($request);
        $ttl = config('hybrid-response.cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, function () use ($next, $request) {
            $response = $next($request);
            return $response->getContent();
        });
    }

    protected function getCacheKey(Request $request): string
    {
        $question = $request->input('question');
        $normalized = strtolower(trim($question));
        return 'hybrid_response:' . md5($normalized);
    }
}
```

### Logging Middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogHybridResponse
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);

        $response = $next($request);

        $this->logResponse($request, $response, $startTime);

        return $response;
    }

    protected function logResponse(Request $request, $response, float $startTime): void
    {
        $content = json_decode($response->getContent(), true);

        Log::channel('hybrid_responses')->info('Hybrid response generated', [
            'question' => $request->input('question'),
            'strategy' => $content['meta']['strategy'] ?? 'unknown',
            'is_hybrid' => $content['meta']['is_hybrid'] ?? false,
            'confidence' => $content['meta']['confidence'] ?? null,
            'sources' => $content['meta']['sources'] ?? [],
            'latency_ms' => $content['meta']['latency_ms'] ?? 0,
            'total_time_ms' => (microtime(true) - $startTime) * 1000,
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);
    }
}
```

## API Routes

```php
// routes/api.php

use App\Http\Controllers\AskController;
use App\Http\Middleware\CacheHybridResponse;
use App\Http\Middleware\HybridResponseRateLimiter;
use App\Http\Middleware\LogHybridResponse;

Route::prefix('v1')->group(function () {
    Route::post('/ask', AskController::class)
        ->middleware([
            'auth:sanctum',
            HybridResponseRateLimiter::class,
            LogHybridResponse::class,
        ]);

    // Cached endpoint for FAQ-style queries
    Route::get('/faq', [AskController::class, 'faq'])
        ->middleware([
            CacheHybridResponse::class,
            LogHybridResponse::class,
        ]);
});
```

## Streaming Responses

For real-time streaming of hybrid responses:

```php
<?php

namespace App\Http\Controllers;

use App\Agents\CustomerSupportAgent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController extends Controller
{
    public function stream(Request $request, CustomerSupportAgent $agent): StreamedResponse
    {
        $question = $request->input('question');

        return response()->stream(function () use ($agent, $question) {
            // Send RAG results first
            $this->sendEvent('rag_start', ['status' => 'retrieving']);

            $ragResult = $agent->getRagPipeline()?->query($question);

            if ($ragResult && $ragResult->hasContext()) {
                $this->sendEvent('rag_complete', [
                    'segments' => collect($ragResult->getResults())->map(fn($r) => [
                        'content' => $r->getContent(),
                        'score' => $r->score,
                        'source' => $r->getMeta('source'),
                    ])->all(),
                ]);
            }

            // Then stream LLM response
            $this->sendEvent('llm_start', ['status' => 'generating']);

            // For actual streaming, you'd use the agent's stream() method
            $response = $agent->respond($question, [
                'rag_context' => $ragResult?->getContext() ?? '',
            ]);

            $this->sendEvent('llm_complete', [
                'content' => $response->content,
            ]);

            // Final event with combined response
            $this->sendEvent('complete', [
                'strategy' => 'rag_augmented',
                'sources' => $ragResult?->getSources() ?? [],
            ]);

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    protected function sendEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";
        ob_flush();
        flush();
    }
}
```

Frontend consumption:

```javascript
const eventSource = new EventSource('/api/v1/stream?question=' + encodeURIComponent(question));

eventSource.addEventListener('rag_complete', (event) => {
    const data = JSON.parse(event.data);
    displayRagResults(data.segments);
});

eventSource.addEventListener('llm_complete', (event) => {
    const data = JSON.parse(event.data);
    displayAnswer(data.content);
});

eventSource.addEventListener('complete', (event) => {
    const data = JSON.parse(event.data);
    displaySources(data.sources);
    eventSource.close();
});
```

## Error Handling

```php
<?php

namespace App\Exceptions;

use AgenticOrchestrator\Exceptions\AgentException;
use AgenticOrchestrator\Exceptions\RagException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $e)
    {
        if ($request->expectsJson()) {
            if ($e instanceof RagException) {
                return response()->json([
                    'error' => 'Knowledge base error',
                    'message' => $e->getMessage(),
                    'fallback_available' => true,
                ], 503);
            }

            if ($e instanceof AgentException) {
                return response()->json([
                    'error' => 'Agent error',
                    'message' => config('app.debug') ? $e->getMessage() : 'Unable to process request',
                ], 500);
            }
        }

        return parent::render($request, $e);
    }
}
```

## Testing

```php
<?php

namespace Tests\Feature;

use App\Agents\CustomerSupportAgent;
use AgenticOrchestrator\Responses\HybridStrategy;
use Tests\TestCase;

class HybridResponseApiTest extends TestCase
{
    public function test_ask_endpoint_returns_hybrid_response(): void
    {
        $response = $this->postJson('/api/v1/ask', [
            'question' => 'What is your return policy?',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'content',
                'segments',
                'strategy',
                'sources',
                'latency_ms',
            ]);
    }

    public function test_ask_with_specific_strategy(): void
    {
        $response = $this->postJson('/api/v1/ask', [
            'question' => 'What is your return policy?',
            'strategy' => 'rag_only',
        ]);

        $response->assertOk()
            ->assertJsonPath('strategy', 'rag_only');
    }

    public function test_rate_limiting_applies_to_expensive_strategies(): void
    {
        // Make many requests with expensive strategy
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/v1/ask', [
                'question' => 'Test question',
                'strategy' => 'llm_with_verification',
            ]);
        }

        // Next request should be rate limited
        $response = $this->postJson('/api/v1/ask', [
            'question' => 'Test question',
            'strategy' => 'llm_with_verification',
        ]);

        $response->assertStatus(429);
    }
}
```
