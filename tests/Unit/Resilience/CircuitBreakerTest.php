<?php

declare(strict_types=1);

use AgenticOrchestrator\Exceptions\CircuitBreakerOpenException;
use AgenticOrchestrator\Resilience\CircuitBreaker;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

test('circuit starts in closed state', function () {
    $breaker = CircuitBreaker::for('test-service');

    expect($breaker->isClosed())->toBeTrue();
    expect($breaker->isOpen())->toBeFalse();
    expect($breaker->isHalfOpen())->toBeFalse();
    expect($breaker->getState())->toBe(CircuitBreaker::STATE_CLOSED);
});

test('circuit opens after failure threshold', function () {
    $breaker = CircuitBreaker::for('test-service')
        ->failureThreshold(3)
        ->recoveryTimeout(60);

    // Simulate failures
    for ($i = 0; $i < 3; $i++) {
        try {
            $breaker->execute(function () {
                throw new RuntimeException('Service unavailable');
            });
        } catch (RuntimeException) {
        }
    }

    expect($breaker->isOpen())->toBeTrue();
    expect($breaker->getFailureCount())->toBeGreaterThanOrEqual(3);
});

test('open circuit throws exception', function () {
    $breaker = CircuitBreaker::for('test-service')
        ->failureThreshold(1)
        ->recoveryTimeout(60);

    // Trip the circuit
    try {
        $breaker->execute(fn () => throw new RuntimeException('Error'));
    } catch (RuntimeException) {
    }

    expect(fn () => $breaker->execute(fn () => 'success'))
        ->toThrow(CircuitBreakerOpenException::class);
});

test('successful execution resets failure count', function () {
    $breaker = CircuitBreaker::for('test-service')
        ->failureThreshold(3);

    // Some failures
    for ($i = 0; $i < 2; $i++) {
        try {
            $breaker->execute(fn () => throw new RuntimeException('Error'));
        } catch (RuntimeException) {
        }
    }

    // Successful execution
    $breaker->execute(fn () => 'success');

    expect($breaker->getFailureCount())->toBe(0);
});

test('circuit can be manually reset', function () {
    $breaker = CircuitBreaker::for('test-service')
        ->failureThreshold(1);

    // Trip the circuit
    try {
        $breaker->execute(fn () => throw new RuntimeException('Error'));
    } catch (RuntimeException) {
    }

    expect($breaker->isOpen())->toBeTrue();

    $breaker->reset();

    expect($breaker->isClosed())->toBeTrue();
    expect($breaker->getFailureCount())->toBe(0);
});

test('circuit can be force opened', function () {
    $breaker = CircuitBreaker::for('test-service');

    expect($breaker->isClosed())->toBeTrue();

    $breaker->forceOpen();

    expect($breaker->isOpen())->toBeTrue();
});

test('ignores specified exception types', function () {
    $breaker = CircuitBreaker::for('test-service')
        ->failureThreshold(1)
        ->ignoreExceptions([InvalidArgumentException::class]);

    // This should not trip the circuit
    try {
        $breaker->execute(fn () => throw new InvalidArgumentException('Ignored'));
    } catch (InvalidArgumentException) {
    }

    expect($breaker->isClosed())->toBeTrue();
    expect($breaker->getFailureCount())->toBe(0);
});

test('only trips on specified exception types', function () {
    $breaker = CircuitBreaker::for('test-service')
        ->failureThreshold(1)
        ->tripOn([RuntimeException::class]);

    // This should not trip
    try {
        $breaker->execute(fn () => throw new InvalidArgumentException('Not on list'));
    } catch (InvalidArgumentException) {
    }

    expect($breaker->isClosed())->toBeTrue();

    // This should trip
    try {
        $breaker->execute(fn () => throw new RuntimeException('On the list'));
    } catch (RuntimeException) {
    }

    expect($breaker->isOpen())->toBeTrue();
});

test('custom trip callback', function () {
    $breaker = CircuitBreaker::for('test-service')
        ->failureThreshold(1)
        ->shouldTrip(fn ($e) => $e->getCode() >= 500);

    // 400 error should not trip
    try {
        $breaker->execute(fn () => throw new RuntimeException('Client error', 400));
    } catch (RuntimeException) {
    }

    expect($breaker->isClosed())->toBeTrue();

    // 500 error should trip
    try {
        $breaker->execute(fn () => throw new RuntimeException('Server error', 500));
    } catch (RuntimeException) {
    }

    expect($breaker->isOpen())->toBeTrue();
});

test('stats returns correct information', function () {
    $breaker = CircuitBreaker::for('test-service')
        ->failureThreshold(5)
        ->recoveryTimeout(30)
        ->successThreshold(2);

    $stats = $breaker->stats();

    expect($stats)->toHaveKey('service');
    expect($stats)->toHaveKey('state');
    expect($stats)->toHaveKey('failure_count');
    expect($stats)->toHaveKey('failure_threshold');
    expect($stats['service'])->toBe('test-service');
    expect($stats['failure_threshold'])->toBe(5);
    expect($stats['recovery_timeout'])->toBe(30);
    expect($stats['success_threshold'])->toBe(2);
});

test('fluent configuration', function () {
    $breaker = CircuitBreaker::for('test-service')
        ->failureThreshold(10)
        ->recoveryTimeout(120)
        ->successThreshold(5)
        ->failureWindow(300);

    $stats = $breaker->stats();

    expect($stats['failure_threshold'])->toBe(10);
    expect($stats['recovery_timeout'])->toBe(120);
    expect($stats['success_threshold'])->toBe(5);
});

test('on open callback is triggered', function () {
    $openCalled = false;

    $breaker = CircuitBreaker::for('test-service')
        ->failureThreshold(1)
        ->onOpen(function ($service) use (&$openCalled) {
            $openCalled = true;
            expect($service)->toBe('test-service');
        });

    try {
        $breaker->execute(fn () => throw new RuntimeException('Error'));
    } catch (RuntimeException) {
    }

    expect($openCalled)->toBeTrue();
});

test('on close callback is triggered', function () {
    $closeCalled = false;

    $breaker = CircuitBreaker::for('test-service')
        ->failureThreshold(1)
        ->onClose(function () use (&$closeCalled) {
            $closeCalled = true;
        });

    // Trip and reset
    try {
        $breaker->execute(fn () => throw new RuntimeException('Error'));
    } catch (RuntimeException) {
    }

    $breaker->reset();

    expect($closeCalled)->toBeTrue();
});
