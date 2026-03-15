<?php

declare(strict_types=1);

use AgenticOrchestrator\Exceptions\AgentException;
use AgenticOrchestrator\Exceptions\CircuitBreakerOpenException;
use AgenticOrchestrator\Exceptions\MemoryException;
use AgenticOrchestrator\Exceptions\ProviderException;
use AgenticOrchestrator\Exceptions\RateLimitException;
use AgenticOrchestrator\Exceptions\ToolExecutionException;
use AgenticOrchestrator\Exceptions\ValidationException;
use AgenticOrchestrator\Exceptions\WorkflowException;

test('agent exception stores context', function () {
    $exception = new AgentException('Test error', 0, null, [
        'agent' => 'test-agent',
        'attempt' => 1,
    ]);

    expect($exception->getMessage())->toBe('Test error');
    expect($exception->getContext())->toBe([
        'agent' => 'test-agent',
        'attempt' => 1,
    ]);
});

test('agent exception can be created with context', function () {
    $exception = AgentException::withContext('Error occurred', [
        'key' => 'value',
    ]);

    expect($exception->getMessage())->toBe('Error occurred');
    expect($exception->getContext())->toHaveKey('key');
    expect($exception->isRecoverable())->toBeFalse();
});

test('agent exception can be marked recoverable', function () {
    $exception = AgentException::recoverable('Temporary error', [
        'retry_after' => 5,
    ]);

    expect($exception->isRecoverable())->toBeTrue();
    expect($exception->getContext())->toHaveKey('retry_after');
});

test('agent exception can add context', function () {
    $exception = new AgentException('Error');
    $exception->addContext(['new_key' => 'new_value']);

    expect($exception->getContext())->toBe(['new_key' => 'new_value']);
});

test('agent exception converts to array', function () {
    $exception = new AgentException('Error', 0, null, ['key' => 'value']);

    $array = $exception->toArray();

    expect($array)->toHaveKey('type');
    expect($array)->toHaveKey('message');
    expect($array)->toHaveKey('context');
    expect($array['type'])->toBe(AgentException::class);
});

test('tool execution exception includes tool name', function () {
    $exception = new ToolExecutionException(
        'get_weather',
        'API error',
        ['location' => 'London']
    );

    expect($exception->getMessage())->toContain('get_weather');
    expect($exception->getMessage())->toContain('API error');
    expect($exception->getToolName())->toBe('get_weather');
    expect($exception->getArguments())->toBe(['location' => 'London']);
});

test('tool execution exception for timeout is recoverable', function () {
    $exception = ToolExecutionException::timeout('slow_tool', 30, ['arg' => 'value']);

    expect($exception->isRecoverable())->toBeTrue();
    expect($exception->getMessage())->toContain('30 seconds');
});

test('tool execution exception for validation includes errors', function () {
    $exception = ToolExecutionException::validation('my_tool', ['arg1' => 'bad'], [
        'arg1' => 'Invalid value',
    ]);

    expect($exception->getMessage())->toContain('Validation failed');
    expect($exception->getContext())->toHaveKey('validation_errors');
});

test('provider exception includes provider info', function () {
    $exception = new ProviderException('openai', 'Connection failed', 'gpt-4', 500);

    expect($exception->getProvider())->toBe('openai');
    expect($exception->getModel())->toBe('gpt-4');
    expect($exception->getStatusCode())->toBe(500);
    expect($exception->getMessage())->toContain('openai');
    expect($exception->getMessage())->toContain('gpt-4');
});

test('provider exception for rate limit is recoverable', function () {
    $exception = ProviderException::rateLimited('openai', 'gpt-4', 60);

    expect($exception->isRecoverable())->toBeTrue();
    expect($exception->isRateLimited())->toBeTrue();
    expect($exception->getStatusCode())->toBe(429);
    expect($exception->getContext())->toHaveKey('retry_after');
});

test('provider exception for server error is recoverable', function () {
    $exception = ProviderException::serverError('anthropic', 'claude-3', 503);

    expect($exception->isRecoverable())->toBeTrue();
    expect($exception->isServerError())->toBeTrue();
});

test('provider exception for authentication failure', function () {
    $exception = ProviderException::authenticationFailed('openai');

    expect($exception->getStatusCode())->toBe(401);
    expect($exception->isRecoverable())->toBeFalse();
});

test('rate limit exception includes all info', function () {
    $exception = new RateLimitException('agent', 'test-agent', 30, 100, 0);

    expect($exception->getLimiterType())->toBe('agent');
    expect($exception->getIdentifier())->toBe('test-agent');
    expect($exception->getRetryAfter())->toBe(30);
    expect($exception->getLimit())->toBe(100);
    expect($exception->getRemaining())->toBe(0);
    expect($exception->isRecoverable())->toBeTrue();
});

test('rate limit exception factory methods', function () {
    $agentLimit = RateLimitException::forAgent('my-agent', 60, 100);
    expect($agentLimit->getLimiterType())->toBe('agent');

    $teamLimit = RateLimitException::forTeam(123, 30, 1000);
    expect($teamLimit->getLimiterType())->toBe('team');

    $userLimit = RateLimitException::forUser('user-456', 15, 50);
    expect($userLimit->getLimiterType())->toBe('user');

    $tokenLimit = RateLimitException::forTokens('team:1', 120, 100000);
    expect($tokenLimit->getLimiterType())->toBe('tokens');
});

test('circuit breaker exception includes remaining time', function () {
    $openUntil = time() + 30;
    $exception = new CircuitBreakerOpenException('provider:openai', $openUntil, 5);

    expect($exception->getServiceName())->toBe('provider:openai');
    expect($exception->getOpenUntil())->toBe($openUntil);
    expect($exception->getFailureCount())->toBe(5);
    expect($exception->getRemainingSeconds())->toBeLessThanOrEqual(30);
    expect($exception->isRecoverable())->toBeTrue();
});

test('circuit breaker exception factory methods', function () {
    $providerException = CircuitBreakerOpenException::forProvider('openai');
    expect($providerException->getServiceName())->toBe('provider:openai');

    $agentException = CircuitBreakerOpenException::forAgent('my-agent');
    expect($agentException->getServiceName())->toBe('agent:my-agent');

    $toolException = CircuitBreakerOpenException::forTool('web_search');
    expect($toolException->getServiceName())->toBe('tool:web_search');
});

test('workflow exception includes step info', function () {
    $exception = new WorkflowException(
        'Step failed',
        'MyWorkflow',
        'step_one',
        0
    );

    expect($exception->getWorkflowName())->toBe('MyWorkflow');
    expect($exception->getStepName())->toBe('step_one');
    expect($exception->getStepIndex())->toBe(0);
    expect($exception->getMessage())->toContain('MyWorkflow');
    expect($exception->getMessage())->toContain('step_one');
});

test('workflow exception for timeout', function () {
    $exception = WorkflowException::timeout(30, 'MyWorkflow', 'slow_step');

    expect($exception->getMessage())->toContain('30 seconds');
    expect($exception->isRecoverable())->toBeTrue();
});

test('workflow exception for invalid state', function () {
    $exception = WorkflowException::invalidState('running', 'paused', 'MyWorkflow');

    expect($exception->getMessage())->toContain('running');
    expect($exception->getMessage())->toContain('paused');
});

test('memory exception includes driver info', function () {
    $exception = new MemoryException('Connection lost', 'redis', 'write');

    expect($exception->getDriver())->toBe('redis');
    expect($exception->getOperation())->toBe('write');
    expect($exception->getMessage())->toContain('redis');
});

test('memory exception for connection failure is recoverable', function () {
    $exception = MemoryException::connectionFailed('redis');

    expect($exception->isRecoverable())->toBeTrue();
    expect($exception->getOperation())->toBe('connect');
});

test('memory exception for read failure includes key', function () {
    $exception = MemoryException::readFailed('cache', 'my:key');

    expect($exception->getMessage())->toContain('my:key');
    expect($exception->getContext())->toHaveKey('key');
});

test('validation exception with multiple errors', function () {
    $exception = new ValidationException([
        'name' => ['Name is required'],
        'email' => ['Email is invalid', 'Email already exists'],
    ]);

    expect($exception->getErrors())->toHaveCount(2);
    expect($exception->getFieldErrors('name'))->toHaveCount(1);
    expect($exception->getFieldErrors('email'))->toHaveCount(2);
    expect($exception->hasFieldErrors('name'))->toBeTrue();
    expect($exception->hasFieldErrors('phone'))->toBeFalse();
    expect($exception->getAllMessages())->toHaveCount(3);
});

test('validation exception normalizes string errors to arrays', function () {
    $exception = ValidationException::withErrors([
        'name' => 'Name is required',
    ]);

    expect($exception->getFieldErrors('name'))->toBe(['Name is required']);
});

test('validation exception for required fields', function () {
    $exception = ValidationException::required(['name', 'email']);

    expect($exception->getErrors())->toHaveCount(2);
    expect($exception->hasFieldErrors('name'))->toBeTrue();
    expect($exception->hasFieldErrors('email'))->toBeTrue();
});

test('validation exception for invalid type', function () {
    $exception = ValidationException::invalidType('age', 'integer', 'string');

    expect($exception->getMessage())->toContain('integer');
    expect($exception->getMessage())->toContain('string');
});
