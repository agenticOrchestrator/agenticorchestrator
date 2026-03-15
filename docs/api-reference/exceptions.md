# Exceptions

Exception classes thrown by the Agent Orchestrator package.

## Available Exceptions

All exceptions are in the `AgenticOrchestrator\Exceptions` namespace:

| Exception | Purpose |
|-----------|---------|
| `AgentException` | General agent errors |
| `AgentNotFoundException` | Agent not found in registry |
| `AgentAccessDeniedException` | Access denied to agent |
| `ToolExecutionException` | Tool execution failures |
| `ValidationException` | Validation errors |
| `MemoryException` | Memory operation errors |
| `WorkflowException` | Workflow execution errors |
| `ProviderException` | LLM provider errors |
| `RateLimitException` | API rate limit exceeded |
| `CircuitBreakerOpenException` | Circuit breaker is open |

## AgentException

Base exception for agent-related errors.

```php
use AgenticOrchestrator\Exceptions\AgentException;

try {
    $response = $agent->respond($message);
} catch (AgentException $e) {
    Log::error('Agent error', [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
    ]);
}
```

## AgentNotFoundException

Thrown when an agent cannot be found in the registry.

```php
use AgenticOrchestrator\Exceptions\AgentNotFoundException;

try {
    $agent = AgentRegistry::get('unknown-agent');
} catch (AgentNotFoundException $e) {
    // Agent 'unknown-agent' not registered
}
```

## AgentAccessDeniedException

Thrown when access to an agent is denied (e.g., team-scoped agent accessed by wrong team).

```php
use AgenticOrchestrator\Exceptions\AgentAccessDeniedException;

try {
    $agent = $agent->forTeam($team);
    $response = $agent->respond($message);
} catch (AgentAccessDeniedException $e) {
    // User doesn't have access to this agent
}
```

## ToolExecutionException

Thrown when a tool fails to execute.

```php
use AgenticOrchestrator\Exceptions\ToolExecutionException;

try {
    $result = $tool->execute($arguments);
} catch (ToolExecutionException $e) {
    Log::error('Tool failed', [
        'tool' => $e->getMessage(),
        'error' => $e->getMessage(),
    ]);
}
```

## ValidationException

Thrown when validation fails (e.g., tool arguments, agent configuration).

```php
use AgenticOrchestrator\Exceptions\ValidationException;

try {
    $result = $tool->execute(['invalid' => 'args']);
} catch (ValidationException $e) {
    Log::warning('Validation failed', [
        'errors' => $e->getMessage(),
    ]);
}
```

## MemoryException

Thrown when memory operations fail.

```php
use AgenticOrchestrator\Exceptions\MemoryException;

try {
    $memory->store('key', $value);
} catch (MemoryException $e) {
    Log::error('Memory operation failed', [
        'error' => $e->getMessage(),
    ]);
}
```

## WorkflowException

Thrown when workflow execution fails.

```php
use AgenticOrchestrator\Exceptions\WorkflowException;

try {
    $result = $workflow->run($input);
} catch (WorkflowException $e) {
    Log::error('Workflow failed', [
        'error' => $e->getMessage(),
    ]);
}
```

## ProviderException

Thrown when LLM provider operations fail.

```php
use AgenticOrchestrator\Exceptions\ProviderException;

try {
    $response = $agent->respond($message);
} catch (ProviderException $e) {
    Log::error('Provider error', [
        'error' => $e->getMessage(),
    ]);

    // Use fallback provider or cached response
}
```

## RateLimitException

Thrown when API rate limits are exceeded.

```php
use AgenticOrchestrator\Exceptions\RateLimitException;

try {
    $response = $agent->respond($message);
} catch (RateLimitException $e) {
    Log::warning('Rate limited', [
        'error' => $e->getMessage(),
    ]);

    // Queue for retry later
    dispatch(new RetryAgentCall($message))->delay(now()->addMinutes(1));
}
```

## CircuitBreakerOpenException

Thrown when a circuit breaker is open and preventing requests.

```php
use AgenticOrchestrator\Exceptions\CircuitBreakerOpenException;

try {
    $response = $agent->respond($message);
} catch (CircuitBreakerOpenException $e) {
    Log::warning('Circuit breaker open', [
        'error' => $e->getMessage(),
    ]);

    // Use cached response or fallback
    return $this->getCachedResponse($message);
}
```

## Error Handling Best Practices

### Comprehensive Handler

```php
use AgenticOrchestrator\Exceptions\AgentException;
use AgenticOrchestrator\Exceptions\RateLimitException;
use AgenticOrchestrator\Exceptions\CircuitBreakerOpenException;
use AgenticOrchestrator\Exceptions\ProviderException;

public function handleAgentRequest(string $message)
{
    try {
        return $this->agent->respond($message);
    } catch (RateLimitException $e) {
        return $this->handleRateLimit($e);
    } catch (CircuitBreakerOpenException $e) {
        return $this->handleCircuitOpen($e);
    } catch (ProviderException $e) {
        return $this->handleProviderError($e);
    } catch (AgentException $e) {
        return $this->handleGenericError($e);
    }
}
```

### Custom Exception Handling in Laravel

```php
// In App\Exceptions\Handler.php

public function register()
{
    $this->renderable(function (RateLimitException $e, $request) {
        return response()->json([
            'error' => 'rate_limited',
            'message' => 'Too many requests. Please try again later.',
        ], 429);
    });

    $this->renderable(function (AgentException $e, $request) {
        return response()->json([
            'error' => 'agent_error',
            'message' => $e->getMessage(),
        ], 500);
    });
}
```

### Logging and Monitoring

```php
use AgenticOrchestrator\Exceptions\AgentException;

try {
    $response = $agent->respond($message);
} catch (AgentException $e) {
    // Log with context
    Log::error('Agent operation failed', [
        'agent' => $agent->getName(),
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);

    // Report to monitoring service
    report($e);

    // Re-throw or handle gracefully
    throw $e;
}
```
