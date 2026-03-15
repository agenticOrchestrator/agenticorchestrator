# Circuit Breaker

The circuit breaker pattern prevents cascading failures by stopping requests to failing services.

## Overview

Circuit breakers have three states:

- **Closed**: Requests flow normally
- **Open**: Requests fail immediately
- **Half-Open**: Limited requests test if service recovered

## Configuration

### Global Configuration

```php
// config/agent-orchestrator.php
'resilience' => [
    'circuit_breaker' => [
        'enabled' => true,
        'failure_threshold' => 5,      // Failures before opening
        'success_threshold' => 2,      // Successes in half-open to close
        'recovery_timeout' => 30,      // Seconds before half-open
        'failure_window' => 60,        // Window in seconds to count failures
        'cache_store' => 'redis',      // Laravel cache store
    ],
],
```

### Per-Instance Configuration

```php
use AgenticOrchestrator\Resilience\CircuitBreaker;

// Using fluent API
$breaker = CircuitBreaker::for('my-service')
    ->failureThreshold(3)
    ->successThreshold(2)
    ->recoveryTimeout(60)
    ->failureWindow(120);

// Using config array
$breaker = new CircuitBreaker('my-service', [
    'failure_threshold' => 3,
    'success_threshold' => 2,
    'recovery_timeout' => 60,
    'failure_window' => 120,
]);
```

## Basic Usage

### Automatic Circuit Breaking

Circuit breakers are automatically applied to agent calls:

```php
use App\Agents\ExternalApiAgent;
use AgenticOrchestrator\Exceptions\CircuitBreakerOpenException;

try {
    $response = ExternalApiAgent::make()->respond($message);
} catch (CircuitBreakerOpenException $e) {
    // Circuit is open, service considered unavailable
    Log::warning('Circuit open for ExternalApiAgent');

    // Use fallback
    $response = $this->fallbackResponse();
}
```

### Manual Circuit Breaker

```php
use AgenticOrchestrator\Resilience\CircuitBreaker;

// Using the static factory with fluent configuration
$breaker = CircuitBreaker::for('external-api')
    ->failureThreshold(5)
    ->successThreshold(3)
    ->recoveryTimeout(30);

$result = $breaker->execute(function () {
    return $this->callExternalApi();
});

// Or using constructor with config array
$breaker = new CircuitBreaker('external-api', [
    'failure_threshold' => 5,
    'success_threshold' => 3,
    'recovery_timeout' => 30,
]);

$result = $breaker->execute(function () {
    return $this->callExternalApi();
});
```

## Circuit States

### Checking State

```php
use AgenticOrchestrator\Resilience\CircuitBreaker;

$breaker = CircuitBreaker::for('my-agent');

// Check if circuit is closed (available)
if ($breaker->isClosed()) {
    // Safe to call
}

// Check if circuit is open
if ($breaker->isOpen()) {
    // Service considered unavailable
}

// Check if circuit is half-open (testing recovery)
if ($breaker->isHalfOpen()) {
    // Limited requests allowed
}

// Get current state as string
$state = $breaker->getState();
// Returns: 'closed', 'open', or 'half_open'
```

### State Transitions

```php
// Circuit transitions:
// closed → open (after failure_threshold failures)
// open → half-open (after timeout expires)
// half-open → closed (after success_threshold successes)
// half-open → open (on any failure)
```

## Failure Detection

### Default Failure Detection

By default, exceptions trigger failures:

```php
$breaker->execute(function () {
    // This exception counts as a failure
    throw new Exception('API error');
});
```

### Custom Failure Detection

Use the `shouldTrip()` method to customize which exceptions trigger the circuit:

```php
$breaker = CircuitBreaker::for('api')
    ->shouldTrip(function (Throwable $e): bool {
        // Only trip on server errors, not validation errors
        if ($e instanceof ValidationException) {
            return false;
        }

        // Trip on connection errors
        if ($e instanceof ConnectionException) {
            return true;
        }

        return true; // Default: all exceptions trip
    });
```

### Ignored Exceptions

```php
$breaker = CircuitBreaker::for('api')
    ->ignoreExceptions([
        ValidationException::class,
        NotFoundException::class,
    ]);

// Or configure via array
$breaker = new CircuitBreaker('api', [
    'ignore_exceptions' => [
        ValidationException::class,
        NotFoundException::class,
    ],
]);
```

### Trip Only On Specific Exceptions

```php
$breaker = CircuitBreaker::for('api')
    ->tripOn([
        ConnectionException::class,
        TimeoutException::class,
    ]);
```

## Callbacks

### Listening to State Changes

Use callback methods to react to circuit state changes:

```php
$breaker = CircuitBreaker::for('api')
    ->onOpen(function (string $service) {
        Log::warning("Circuit opened: {$service}");
        Notification::send($admins, new CircuitBreakerAlert($service));
    })
    ->onClose(function (string $service) {
        Log::info("Circuit closed: {$service}");
    })
    ->onHalfOpen(function (string $service) {
        Log::info("Circuit half-open: {$service}");
    });
```

## Storage

Circuit breaker state is stored in Laravel's cache system by default.

### Configuring Cache Store

```php
// Via constructor config
$breaker = new CircuitBreaker('api', [
    'cache_store' => 'redis',
]);

// Via fluent configuration
$breaker = CircuitBreaker::for('api')
    ->configure(['cache_store' => 'redis']);
```

## Monitoring

### Statistics

```php
use AgenticOrchestrator\Resilience\CircuitBreaker;

$breaker = CircuitBreaker::for('my-agent');

// Get circuit statistics
$stats = $breaker->stats();

// Returns:
[
    'service' => 'my-agent',
    'state' => 'closed',
    'failure_count' => 2,
    'failure_threshold' => 5,
    'recovery_timeout' => 30,
    'opened_at' => null,
    'open_until' => null,
    'half_open_successes' => 0,
    'success_threshold' => 2,
]
```

### Individual Metrics

```php
$breaker = CircuitBreaker::for('my-agent');

// Get individual metrics
$failureCount = $breaker->getFailureCount();
$openedAt = $breaker->getOpenedAt();          // Unix timestamp or null
$openUntil = $breaker->getOpenUntil();        // Unix timestamp or null
$halfOpenSuccesses = $breaker->getHalfOpenSuccessCount();
```

## Testing

### Manual State Control

```php
use AgenticOrchestrator\Resilience\CircuitBreaker;
use AgenticOrchestrator\Exceptions\CircuitBreakerOpenException;

beforeEach(function () {
    $breaker = CircuitBreaker::for('my-agent');
    $breaker->reset();
});

it('opens after failures', function () {
    $breaker = CircuitBreaker::for('my-agent')
        ->failureThreshold(3);

    // Simulate failures
    for ($i = 0; $i < 3; $i++) {
        try {
            $breaker->execute(function () {
                throw new Exception('Service error');
            });
        } catch (Exception) {
        }
    }

    expect($breaker->isOpen())->toBeTrue();
});

it('throws exception when open', function () {
    $breaker = CircuitBreaker::for('my-agent');
    $breaker->forceOpen();

    expect(fn () => $breaker->execute(fn () => 'result'))
        ->toThrow(CircuitBreakerOpenException::class);
});

it('allows calls when closed', function () {
    $breaker = CircuitBreaker::for('my-agent');
    $breaker->reset();

    $result = $breaker->execute(fn () => 'result');

    expect($result)->toBe('result');
});
```

## Best Practices

1. **Set appropriate thresholds** - Balance between sensitivity and stability
2. **Use meaningful timeouts** - Allow services time to recover
3. **Monitor circuit states** - Alert on frequent opens
4. **Implement fallbacks** - Have alternatives when circuits open
5. **Test failure scenarios** - Verify circuit behavior in tests
6. **Log state changes** - Track circuit events for debugging
