# Retry Strategy

The `RetryStrategy` class provides configurable retry logic with multiple backoff strategies. It automatically retries failed operations while preventing resource exhaustion through intelligent delay mechanisms.

## Overview

Retry strategies are essential for handling transient failures such as:

- Network timeouts and connection errors
- Rate limiting responses (HTTP 429)
- Temporary provider unavailability
- Intermittent server errors

## Basic Usage

### Creating a Retry Strategy

```php
use AgenticOrchestrator\Resilience\RetryStrategy;

// Create with default settings (3 attempts, exponential backoff)
$strategy = RetryStrategy::default();

// Execute an operation with retry
$response = $strategy->execute(function () use ($agent, $message) {
    return $agent->respond($message);
});
```

### Factory Methods

The `RetryStrategy` class provides several factory methods for common configurations:

```php
// Default: 3 attempts, exponential backoff, 1s base delay
$strategy = RetryStrategy::default();

// No retries (single attempt)
$strategy = RetryStrategy::none();

// Constant delay between attempts
$strategy = RetryStrategy::constant(
    maxAttempts: 5,
    delayMs: 2000  // 2 seconds between each attempt
);

// Linear backoff (delay increases linearly)
$strategy = RetryStrategy::linear(
    maxAttempts: 4,
    baseDelayMs: 1000  // 1s, 2s, 3s, 4s
);

// Exponential backoff (delay doubles each time)
$strategy = RetryStrategy::exponential(
    maxAttempts: 5,
    baseDelayMs: 500  // 500ms, 1s, 2s, 4s, 8s
);
```

## Backoff Strategies

### Constant Backoff

The same delay is used between each retry attempt.

```
Attempt 1: Immediate
Attempt 2: Wait 1000ms
Attempt 3: Wait 1000ms
Attempt 4: Wait 1000ms
```

**Best for**: Predictable, fixed-rate APIs where timing is critical.

```php
$strategy = RetryStrategy::constant(3, 1000);
```

### Linear Backoff

Delay increases linearly with each attempt: `delay = baseDelay * attemptNumber`

```
Attempt 1: Immediate
Attempt 2: Wait 1000ms  (1000 * 1)
Attempt 3: Wait 2000ms  (1000 * 2)
Attempt 4: Wait 3000ms  (1000 * 3)
```

**Best for**: Moderate load scenarios where gradual backoff is preferred.

```php
$strategy = RetryStrategy::linear(4, 1000);
```

### Exponential Backoff

Delay doubles with each attempt: `delay = baseDelay * 2^(attemptNumber - 1)`

```
Attempt 1: Immediate
Attempt 2: Wait 1000ms  (1000 * 2^0)
Attempt 3: Wait 2000ms  (1000 * 2^1)
Attempt 4: Wait 4000ms  (1000 * 2^2)
Attempt 5: Wait 8000ms  (1000 * 2^3)
```

**Best for**: Rate limiting, high-load scenarios, and distributed systems.

```php
$strategy = RetryStrategy::exponential(5, 1000);
```

## Configuration Options

### Fluent Configuration

Build a custom strategy using fluent methods:

```php
$strategy = (new RetryStrategy())
    ->maxAttempts(5)
    ->backoff(RetryStrategy::BACKOFF_EXPONENTIAL)
    ->baseDelay(1000)      // 1 second
    ->maxDelay(30000)      // Max 30 seconds
    ->withJitter(0.25);    // +/- 25% randomization
```

### Array Configuration

Configure from an array (useful for config files):

```php
$strategy = new RetryStrategy([
    'max_attempts' => 5,
    'backoff' => 'exponential',
    'base_delay_ms' => 1000,
    'max_delay_ms' => 30000,
    'jitter' => 0.25,
    'retry_on' => [
        \GuzzleHttp\Exception\ConnectException::class,
        \AgenticOrchestrator\Exceptions\RateLimitException::class,
    ],
    'dont_retry_on' => [
        \AgenticOrchestrator\Exceptions\AuthenticationException::class,
    ],
]);
```

### Configuration Reference

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `max_attempts` | int | 3 | Maximum number of attempts (including first) |
| `backoff` | string | `exponential` | Backoff type: `constant`, `linear`, `exponential` |
| `base_delay_ms` | int | 1000 | Base delay in milliseconds |
| `max_delay_ms` | int | 30000 | Maximum delay cap in milliseconds |
| `jitter` | float | 0.1 | Jitter factor (0-1) for randomization |
| `retry_on` | array | `[]` | Exception types that trigger retry |
| `dont_retry_on` | array | `[]` | Exception types that never trigger retry |

## Jitter

Jitter adds randomization to delays, preventing the "thundering herd" problem where many clients retry simultaneously.

```php
// Add 25% jitter to delays
$strategy = RetryStrategy::exponential(5, 1000)
    ->withJitter(0.25);

// With base delay of 1000ms and 0.25 jitter:
// Actual delay will be between 750ms and 1250ms
```

**Jitter calculation**: `delay +/- (delay * jitter)`

| Jitter | Effect |
|--------|--------|
| 0.0 | No randomization |
| 0.1 | +/- 10% variation |
| 0.25 | +/- 25% variation |
| 0.5 | +/- 50% variation |
| 1.0 | 0 to 2x delay |

## Exception Filtering

Control which exceptions trigger retries:

### Retry Only Specific Exceptions

```php
use GuzzleHttp\Exception\ConnectException;
use AgenticOrchestrator\Exceptions\RateLimitException;
use AgenticOrchestrator\Exceptions\ProviderException;

$strategy = RetryStrategy::exponential(3, 1000)
    ->retryOn([
        ConnectException::class,
        RateLimitException::class,
    ]);

// Only retries ConnectException and RateLimitException
// All other exceptions are thrown immediately
```

### Exclude Specific Exceptions

```php
use AgenticOrchestrator\Exceptions\AuthenticationException;
use AgenticOrchestrator\Exceptions\ContentFilterException;

$strategy = RetryStrategy::exponential(3, 1000)
    ->dontRetryOn([
        AuthenticationException::class,
        ContentFilterException::class,
    ]);

// Retries all exceptions except those listed
```

### Custom Retry Logic

Use a callback for complex retry decisions:

```php
$strategy = RetryStrategy::exponential(3, 1000)
    ->shouldRetry(function (Throwable $e): bool {
        // Retry rate limits
        if ($e instanceof ProviderException && $e->isRateLimited()) {
            return true;
        }

        // Retry server errors
        if ($e instanceof ProviderException && $e->isServerError()) {
            return true;
        }

        // Retry network errors
        if ($e instanceof ConnectException) {
            return true;
        }

        // Don't retry authentication failures
        if ($e instanceof ProviderException && $e->getStatusCode() === 401) {
            return false;
        }

        // Default: check if exception is marked recoverable
        return $e instanceof AgentException && $e->isRecoverable();
    });
```

## Retry Callbacks

Execute code before each retry attempt:

```php
use Illuminate\Support\Facades\Log;

$strategy = RetryStrategy::exponential(5, 1000)
    ->onRetry(function (int $attempt, Throwable $e, int $delayMs) {
        Log::warning('Retry attempt', [
            'attempt' => $attempt,
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'delay_ms' => $delayMs,
        ]);

        // You could also:
        // - Send metrics to monitoring
        // - Notify administrators
        // - Update user-facing status
    });
```

## Calculating Delays

You can calculate delays without executing:

```php
$strategy = RetryStrategy::exponential(5, 1000)
    ->withJitter(0);

echo $strategy->calculateDelay(1); // 1000
echo $strategy->calculateDelay(2); // 2000
echo $strategy->calculateDelay(3); // 4000
echo $strategy->calculateDelay(4); // 8000
echo $strategy->calculateDelay(5); // 16000 (capped at max_delay)
```

## Inspecting Configuration

Export the configuration as an array:

```php
$strategy = RetryStrategy::exponential(5, 1000)
    ->withJitter(0.25)
    ->maxDelay(30000);

$config = $strategy->toArray();
// [
//     'max_attempts' => 5,
//     'backoff' => 'exponential',
//     'base_delay_ms' => 1000,
//     'max_delay_ms' => 30000,
//     'jitter' => 0.25,
//     'retry_on' => [],
//     'dont_retry_on' => [],
// ]
```

## Integration with Agents

### Within Agent Class

```php
use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Resilience\RetryStrategy;

class CustomerSupportAgent extends Agent
{
    protected function getRetryStrategy(): RetryStrategy
    {
        return RetryStrategy::exponential(3, 1000)
            ->withJitter(0.1)
            ->retryOn([
                \AgenticOrchestrator\Exceptions\ProviderException::class,
            ]);
    }
}
```

### With Provider Fallback Chain

```php
use AgenticOrchestrator\Resilience\ProviderFallbackChain;
use AgenticOrchestrator\Resilience\RetryStrategy;

$chain = (new ProviderFallbackChain())
    ->addProvider('openai', 'gpt-4o')
    ->addProvider('anthropic', 'claude-3-5-sonnet-20241022')
    ->withRetry(RetryStrategy::exponential(3, 1000));

// Retry strategy applies to each provider attempt
// before moving to the next provider in the chain
```

## Backoff Strategy Comparison

| Strategy | Formula | Attempt 2 | Attempt 3 | Attempt 4 | Attempt 5 |
|----------|---------|-----------|-----------|-----------|-----------|
| Constant (1s) | `base` | 1s | 1s | 1s | 1s |
| Linear (1s) | `base * n` | 1s | 2s | 3s | 4s |
| Exponential (1s) | `base * 2^(n-1)` | 1s | 2s | 4s | 8s |

## Best Practices

1. **Always set a maximum delay**: Prevent excessively long waits with `maxDelay()`.

2. **Use jitter in production**: Prevents synchronized retries from multiple clients.

3. **Be selective about retryable exceptions**: Not all errors should trigger retries.

4. **Log retry attempts**: Track retry frequency to identify systemic issues.

5. **Consider total timeout**: Calculate maximum possible wait time for user experience.

```php
// Example: Maximum wait time calculation
// With 5 attempts, exponential backoff, 1s base, 30s max:
// Worst case: 0 + 1 + 2 + 4 + 8 = 15 seconds of waiting
// Plus 5 * operation_time
```

## Related Documentation

- [Circuit Breaker](./circuit-breaker.md): Complements retry with failure detection
- [Fallback Handler](./fallback.md): Provides alternatives when retries are exhausted
- [Configuration](./configuration.md): Global retry configuration
- [Best Practices](./best-practices.md): Production patterns
