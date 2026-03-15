# Resilience

Agent Orchestrator provides a comprehensive set of resilience patterns to ensure your AI agents remain operational and responsive even when external LLM providers experience issues. These patterns protect your application from cascade failures, provide graceful degradation, and enable automatic recovery.

## Why Resilience Matters for AI Agents

AI agents depend on external LLM providers that can experience:

- **Rate limiting**: Providers enforce usage quotas that may be exceeded during high traffic
- **Transient failures**: Network issues, timeouts, and temporary service unavailability
- **Provider outages**: Complete service disruptions that may last minutes to hours
- **Capacity issues**: Slow responses during peak usage periods

Without proper resilience patterns, these issues can cascade through your application, leading to poor user experience, wasted compute resources, and lost transactions.

## Available Resilience Patterns

Agent Orchestrator implements four complementary resilience patterns:

### Retry Strategy

Automatically retry failed operations with configurable backoff strategies. Supports constant, linear, and exponential backoff with optional jitter to prevent thundering herd problems.

[Learn more about Retry Strategy](./retry-strategy.md)

### Circuit Breaker

Prevent cascade failures by detecting when a service is failing and temporarily blocking requests. The circuit breaker pattern monitors failure rates and automatically stops calling a failing service, giving it time to recover.

[Learn more about Circuit Breaker](./circuit-breaker.md)

### Fallback Handler

Define alternative behaviors when primary operations fail. Supports conditional fallbacks, exception-specific handlers, and default values.

[Learn more about Fallback Handler](./fallback.md)

### Provider Fallback Chain

Automatically switch between LLM providers when one fails. Configure a priority list of providers with their models and let the system handle failover transparently.

[Learn more about Provider Fallback Chain](./fallback.md#provider-fallback-chain)

## Pattern Comparison

| Pattern | Purpose | Best For |
|---------|---------|----------|
| Retry Strategy | Recover from transient failures | Network timeouts, rate limits |
| Circuit Breaker | Prevent cascade failures | Detecting provider outages |
| Fallback Handler | Provide alternative behavior | Graceful degradation |
| Provider Fallback Chain | Switch between providers | Provider redundancy |

## How Patterns Work Together

These patterns are designed to complement each other. A typical resilience configuration combines all four:

```php
use AgenticOrchestrator\Resilience\CircuitBreaker;
use AgenticOrchestrator\Resilience\RetryStrategy;
use AgenticOrchestrator\Resilience\ProviderFallbackChain;

// The fallback chain uses circuit breakers per provider
// and applies retry strategy before triggering failover
$chain = (new ProviderFallbackChain())
    ->addProvider('openai', 'gpt-4o')
    ->addProvider('anthropic', 'claude-3-5-sonnet-20241022')
    ->addProvider('ollama', 'llama3.2')
    ->withCircuitBreaker([
        'failure_threshold' => 5,
        'recovery_timeout' => 60,
    ])
    ->withRetry(RetryStrategy::exponential(3, 1000));

// Execute with full resilience
$response = $chain->execute(function ($provider, $model, $config) {
    return $this->callProvider($provider, $model);
});
```

**Execution flow:**

```
Request
  |
  v
[Provider 1: OpenAI]
  |-- Circuit Breaker: Open? --> Skip to Provider 2
  |-- Circuit Breaker: Closed/Half-Open
        |
        v
      [Retry Strategy]
        |-- Attempt 1: Success --> Return
        |-- Attempt 1: Fail --> Wait (exponential backoff)
        |-- Attempt 2: Success --> Return
        |-- Attempt 2: Fail --> Wait
        |-- Attempt 3: Fail --> Record failure in Circuit Breaker
              |
              v
[Provider 2: Anthropic]
  |-- Same flow as above
        |
        v
[Provider 3: Ollama]
  |-- Same flow as above
        |
        v
[All Providers Failed] --> Throw Exception
```

## Quick Start

### Basic Retry

```php
use AgenticOrchestrator\Resilience\RetryStrategy;

$result = RetryStrategy::exponential(3, 1000)
    ->execute(function () use ($agent, $message) {
        return $agent->respond($message);
    });
```

### Basic Circuit Breaker

```php
use AgenticOrchestrator\Resilience\CircuitBreaker;
use AgenticOrchestrator\Exceptions\CircuitBreakerOpenException;

$breaker = CircuitBreaker::for('openai')
    ->failureThreshold(5)
    ->recoveryTimeout(60);

try {
    $response = $breaker->execute(function () use ($agent, $message) {
        return $agent->respond($message);
    });
} catch (CircuitBreakerOpenException $e) {
    // Circuit is open, handle gracefully
    $response = $this->fallbackResponse();
}
```

### Basic Fallback

```php
use AgenticOrchestrator\Resilience\FallbackHandler;

$response = FallbackHandler::try(function () use ($agent, $message) {
    return $agent->respond($message);
})
->fallback(fn ($e) => "I'm temporarily unavailable. Please try again.")
->default("Service unavailable")
->execute();
```

## Configuration Overview

Resilience can be configured at multiple levels:

| Level | Scope | Configuration Location |
|-------|-------|------------------------|
| Global | All agents | `config/agent-orchestrator.php` |
| Agent | Single agent class | Agent class definition |
| Runtime | Individual operation | Constructor/method parameters |

See [Configuration](./configuration.md) for detailed configuration options.

## Exception Handling

All resilience components use a consistent exception hierarchy:

| Exception | When Thrown | Recoverable |
|-----------|-------------|-------------|
| `CircuitBreakerOpenException` | Circuit is open, service unavailable | Yes |
| `ProviderException` | Provider returns an error | Depends |
| `RateLimitException` | Rate limit exceeded | Yes |
| `AgentException` | Base exception for all agent errors | Configurable |

```php
use AgenticOrchestrator\Exceptions\CircuitBreakerOpenException;

try {
    $response = $breaker->execute(fn() => $agent->respond($message));
} catch (CircuitBreakerOpenException $e) {
    $service = $e->getServiceName();       // Service name
    $openUntil = $e->getOpenUntil();       // Unix timestamp or null
    $failureCount = $e->getFailureCount(); // Failures that caused open
    $remaining = $e->getRemainingSeconds();// Seconds until recovery
}
```

## Next Steps

- [Retry Strategy](./retry-strategy.md): Configure automatic retries with backoff
- [Circuit Breaker](./circuit-breaker.md): Implement failure detection and recovery
- [Fallback Handler](./fallback.md): Define alternative behaviors
- [Configuration](./configuration.md): Configure resilience globally and per-agent
- [Best Practices](./best-practices.md): Production-ready resilience patterns
