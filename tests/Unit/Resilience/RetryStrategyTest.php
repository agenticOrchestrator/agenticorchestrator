<?php

declare(strict_types=1);

use AgenticOrchestrator\Exceptions\AgentException;
use AgenticOrchestrator\Resilience\RetryStrategy;

test('default strategy has correct settings', function () {
    $strategy = RetryStrategy::default();
    $config = $strategy->toArray();

    expect($config['max_attempts'])->toBe(3);
    expect($config['backoff'])->toBe(RetryStrategy::BACKOFF_EXPONENTIAL);
    expect($config['base_delay_ms'])->toBe(1000);
});

test('none strategy has single attempt', function () {
    $strategy = RetryStrategy::none();
    $config = $strategy->toArray();

    expect($config['max_attempts'])->toBe(1);
});

test('constant backoff calculates delay correctly', function () {
    $strategy = RetryStrategy::constant(3, 1000);

    expect($strategy->calculateDelay(1))->toBe(1000);
    expect($strategy->calculateDelay(2))->toBe(1000);
    expect($strategy->calculateDelay(3))->toBe(1000);
});

test('linear backoff calculates delay correctly', function () {
    $strategy = RetryStrategy::linear(3, 1000)->withJitter(0);

    expect($strategy->calculateDelay(1))->toBe(1000);
    expect($strategy->calculateDelay(2))->toBe(2000);
    expect($strategy->calculateDelay(3))->toBe(3000);
});

test('exponential backoff calculates delay correctly', function () {
    $strategy = RetryStrategy::exponential(5, 1000)->withJitter(0);

    expect($strategy->calculateDelay(1))->toBe(1000);
    expect($strategy->calculateDelay(2))->toBe(2000);
    expect($strategy->calculateDelay(3))->toBe(4000);
    expect($strategy->calculateDelay(4))->toBe(8000);
});

test('delay is clamped to max delay', function () {
    $strategy = RetryStrategy::exponential(10, 1000)
        ->maxDelay(5000)
        ->withJitter(0);

    expect($strategy->calculateDelay(10))->toBe(5000);
});

test('jitter adds randomness to delay', function () {
    $strategy = RetryStrategy::constant(3, 1000)->withJitter(0.5);

    $delays = [];
    for ($i = 0; $i < 10; $i++) {
        $delays[] = $strategy->calculateDelay(1);
    }

    // With 50% jitter on 1000ms, delays should be between 500 and 1500
    foreach ($delays as $delay) {
        expect($delay)->toBeGreaterThanOrEqual(500);
        expect($delay)->toBeLessThanOrEqual(1500);
    }
});

test('execute succeeds on first attempt', function () {
    $strategy = RetryStrategy::default();
    $attempts = 0;

    $result = $strategy->execute(function () use (&$attempts) {
        $attempts++;

        return 'success';
    });

    expect($result)->toBe('success');
    expect($attempts)->toBe(1);
});

test('execute retries on failure', function () {
    $strategy = (new RetryStrategy)
        ->maxAttempts(3)
        ->baseDelay(1); // 1ms to make test fast

    $attempts = 0;

    $result = $strategy->execute(function () use (&$attempts) {
        $attempts++;
        if ($attempts < 3) {
            throw new RuntimeException('Temporary error');
        }

        return 'success';
    });

    expect($result)->toBe('success');
    expect($attempts)->toBe(3);
});

test('execute throws after max attempts', function () {
    $strategy = (new RetryStrategy)
        ->maxAttempts(3)
        ->baseDelay(1);

    $attempts = 0;

    expect(function () use ($strategy, &$attempts) {
        $strategy->execute(function () use (&$attempts) {
            $attempts++;
            throw new RuntimeException('Permanent error');
        });
    })->toThrow(RuntimeException::class);

    expect($attempts)->toBe(3);
});

test('retries only on specified exception types', function () {
    $strategy = (new RetryStrategy)
        ->maxAttempts(3)
        ->baseDelay(1)
        ->retryOn([RuntimeException::class]);

    $attempts = 0;

    expect(function () use ($strategy, &$attempts) {
        $strategy->execute(function () use (&$attempts) {
            $attempts++;
            throw new InvalidArgumentException('Wrong type');
        });
    })->toThrow(InvalidArgumentException::class);

    expect($attempts)->toBe(1);
});

test('does not retry on excluded exception types', function () {
    $strategy = (new RetryStrategy)
        ->maxAttempts(3)
        ->baseDelay(1)
        ->dontRetryOn([InvalidArgumentException::class]);

    $attempts = 0;

    expect(function () use ($strategy, &$attempts) {
        $strategy->execute(function () use (&$attempts) {
            $attempts++;
            throw new InvalidArgumentException('No retry');
        });
    })->toThrow(InvalidArgumentException::class);

    expect($attempts)->toBe(1);
});

test('respects recoverable flag on AgentException', function () {
    $strategy = (new RetryStrategy)
        ->maxAttempts(3)
        ->baseDelay(1);

    $attempts = 0;

    expect(function () use ($strategy, &$attempts) {
        $strategy->execute(function () use (&$attempts) {
            $attempts++;
            throw new AgentException('Non-recoverable');
        });
    })->toThrow(AgentException::class);

    // Non-recoverable exceptions should not be retried
    expect($attempts)->toBe(1);
});

test('retries recoverable AgentException', function () {
    $strategy = (new RetryStrategy)
        ->maxAttempts(3)
        ->baseDelay(1);

    $attempts = 0;

    expect(function () use ($strategy, &$attempts) {
        $strategy->execute(function () use (&$attempts) {
            $attempts++;
            throw AgentException::recoverable('Temporary issue');
        });
    })->toThrow(AgentException::class);

    expect($attempts)->toBe(3);
});

test('custom should retry callback', function () {
    $strategy = (new RetryStrategy)
        ->maxAttempts(3)
        ->baseDelay(1)
        ->shouldRetry(fn ($e) => $e->getCode() === 500);

    $attempts = 0;

    expect(function () use ($strategy, &$attempts) {
        $strategy->execute(function () use (&$attempts) {
            $attempts++;
            throw new RuntimeException('Not 500', 400);
        });
    })->toThrow(RuntimeException::class);

    expect($attempts)->toBe(1);
});

test('on retry callback is called', function () {
    $strategy = (new RetryStrategy)
        ->maxAttempts(3)
        ->baseDelay(1);

    $retryLogs = [];
    $strategy->onRetry(function ($attempt, $exception, $delay) use (&$retryLogs) {
        $retryLogs[] = ['attempt' => $attempt, 'delay' => $delay];
    });

    try {
        $strategy->execute(function () {
            throw new RuntimeException('Error');
        });
    } catch (RuntimeException) {
    }

    expect($retryLogs)->toHaveCount(2); // 2 retries before giving up
    expect($retryLogs[0]['attempt'])->toBe(1);
    expect($retryLogs[1]['attempt'])->toBe(2);
});

test('fluent configuration', function () {
    $strategy = (new RetryStrategy)
        ->maxAttempts(5)
        ->backoff(RetryStrategy::BACKOFF_LINEAR)
        ->baseDelay(500)
        ->maxDelay(10000)
        ->withJitter(0.2);

    $config = $strategy->toArray();

    expect($config['max_attempts'])->toBe(5);
    expect($config['backoff'])->toBe(RetryStrategy::BACKOFF_LINEAR);
    expect($config['base_delay_ms'])->toBe(500);
    expect($config['max_delay_ms'])->toBe(10000);
    expect($config['jitter'])->toBe(0.2);
});
