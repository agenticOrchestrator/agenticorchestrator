<?php

declare(strict_types=1);

use AgenticOrchestrator\Exceptions\RateLimitException;
use AgenticOrchestrator\RateLimiting\AgentRateLimiter;
use AgenticOrchestrator\RateLimiting\TeamRateLimiter;
use AgenticOrchestrator\RateLimiting\TokenRateLimiter;
use AgenticOrchestrator\RateLimiting\UserRateLimiter;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

test('agent rate limiter allows requests under limit', function () {
    $limiter = (new AgentRateLimiter)
        ->maxRequests(10)
        ->windowSeconds(60);

    for ($i = 0; $i < 10; $i++) {
        expect($limiter->attemptAgent('test-agent'))->toBeTrue();
    }

    expect($limiter->remainingForAgent('test-agent'))->toBe(0);
});

test('agent rate limiter blocks requests over limit', function () {
    $limiter = (new AgentRateLimiter)
        ->maxRequests(3)
        ->windowSeconds(60);

    $limiter->attemptAgent('test-agent');
    $limiter->attemptAgent('test-agent');
    $limiter->attemptAgent('test-agent');

    expect(fn () => $limiter->attemptAgent('test-agent'))
        ->toThrow(RateLimitException::class);
});

test('agent rate limiter check does not increment', function () {
    $limiter = (new AgentRateLimiter)
        ->maxRequests(10)
        ->windowSeconds(60);

    // Check multiple times
    expect($limiter->checkAgent('test-agent'))->toBeTrue();
    expect($limiter->checkAgent('test-agent'))->toBeTrue();
    expect($limiter->checkAgent('test-agent'))->toBeTrue();

    // All checks should remain
    expect($limiter->remainingForAgent('test-agent'))->toBe(10);
});

test('team rate limiter works with team objects', function () {
    $team = new class
    {
        public int $id = 123;
    };

    $limiter = (new TeamRateLimiter)
        ->maxRequests(5)
        ->windowSeconds(60);

    expect($limiter->attemptTeam($team))->toBeTrue();
    expect($limiter->remainingForTeam($team))->toBe(4);
});

test('team rate limiter works with team IDs', function () {
    $limiter = (new TeamRateLimiter)
        ->maxRequests(5)
        ->windowSeconds(60);

    expect($limiter->attemptTeam(123))->toBeTrue();
    expect($limiter->remainingForTeam(123))->toBe(4);
});

test('user rate limiter works with user objects', function () {
    $user = new class
    {
        public int $id = 456;
    };

    $limiter = (new UserRateLimiter)
        ->maxRequests(20)
        ->windowSeconds(60);

    expect($limiter->attemptUser($user))->toBeTrue();
    expect($limiter->remainingForUser($user))->toBe(19);
});

test('token rate limiter tracks token usage', function () {
    $limiter = (new TokenRateLimiter)
        ->maxTokens(10000)
        ->windowSeconds(60);

    expect($limiter->checkTokens('user:1', 1000))->toBeTrue();
    expect($limiter->attemptTokens('user:1', 1000))->toBeTrue();
    expect($limiter->remainingTokens('user:1'))->toBe(9000);
});

test('token rate limiter blocks when limit exceeded', function () {
    $limiter = (new TokenRateLimiter)
        ->maxTokens(1000)
        ->windowSeconds(60);

    expect($limiter->attemptTokens('user:1', 500))->toBeTrue();
    expect($limiter->attemptTokens('user:1', 400))->toBeTrue();

    expect(fn () => $limiter->attemptTokens('user:1', 200))
        ->toThrow(RateLimitException::class);
});

test('token rate limiter records input and output usage', function () {
    $limiter = (new TokenRateLimiter)
        ->maxTokens(10000)
        ->windowSeconds(60);

    expect($limiter->recordUsage('agent:1', 500, 300))->toBeTrue();
    expect($limiter->remainingTokens('agent:1'))->toBe(9200);
});

test('rate limiter can be reset', function () {
    $limiter = (new AgentRateLimiter)
        ->maxRequests(5)
        ->windowSeconds(60);

    $limiter->attemptAgent('test-agent');
    $limiter->attemptAgent('test-agent');

    expect($limiter->remainingForAgent('test-agent'))->toBe(3);

    $limiter->reset('test-agent');

    expect($limiter->remainingForAgent('test-agent'))->toBe(5);
});

test('rate limiter status includes all info', function () {
    $limiter = (new AgentRateLimiter)
        ->maxRequests(100)
        ->windowSeconds(60);

    $limiter->attemptAgent('test-agent');

    $status = $limiter->agentStatus('test-agent');

    expect($status)->toHaveKey('limit');
    expect($status)->toHaveKey('remaining');
    expect($status)->toHaveKey('current');
    expect($status)->toHaveKey('retry_after');
    expect($status)->toHaveKey('window_seconds');
    expect($status['limit'])->toBe(100);
    expect($status['remaining'])->toBe(99);
    expect($status['current'])->toBe(1);
});

test('rate limiter convenience methods', function () {
    $limiter = new AgentRateLimiter;

    $limiter->perMinute(60);
    $status = $limiter->status('test');
    expect($status['limit'])->toBe(60);
    expect($status['window_seconds'])->toBe(60);

    $limiter->perHour(1000);
    $status = $limiter->status('test');
    expect($status['limit'])->toBe(1000);
    expect($status['window_seconds'])->toBe(3600);

    $limiter->perDay(10000);
    $status = $limiter->status('test');
    expect($status['limit'])->toBe(10000);
    expect($status['window_seconds'])->toBe(86400);
});

test('on limit exceeded callback is called', function () {
    $callbackCalled = false;

    $limiter = (new AgentRateLimiter)
        ->maxRequests(1)
        ->windowSeconds(60)
        ->onLimitExceeded(function ($key, $retryAfter) use (&$callbackCalled) {
            $callbackCalled = true;
        });

    $limiter->attemptAgent('test-agent');

    try {
        $limiter->attemptAgent('test-agent');
    } catch (RateLimitException) {
    }

    expect($callbackCalled)->toBeTrue();
});

test('execute method runs callback when allowed', function () {
    $limiter = (new AgentRateLimiter)
        ->maxRequests(10)
        ->windowSeconds(60);

    $result = $limiter->execute('test-agent', fn () => 'executed');

    expect($result)->toBe('executed');
    expect($limiter->remainingForAgent('test-agent'))->toBe(9);
});

test('execute method throws when rate limited', function () {
    $limiter = (new AgentRateLimiter)
        ->maxRequests(1)
        ->windowSeconds(60);

    $limiter->attemptAgent('test-agent');

    expect(fn () => $limiter->execute('test-agent', fn () => 'executed'))
        ->toThrow(RateLimitException::class);
});

test('increment and decrement work correctly', function () {
    $limiter = (new AgentRateLimiter)
        ->maxRequests(100)
        ->windowSeconds(60);

    $limiter->increment('test', 5);
    expect($limiter->getCurrentCount('test'))->toBe(5);

    $limiter->increment('test', 3);
    expect($limiter->getCurrentCount('test'))->toBe(8);

    $limiter->decrement('test', 2);
    expect($limiter->getCurrentCount('test'))->toBe(6);
});

test('token rate limiter factory methods', function () {
    $team = new class
    {
        public int $id = 123;
    };

    $teamLimiter = TokenRateLimiter::forTeam($team, ['max_tokens' => 50000]);
    expect($teamLimiter->remainingTokens('default'))->toBe(50000);

    $user = new class
    {
        public int $id = 456;
    };

    $userLimiter = TokenRateLimiter::forUser($user, ['max_tokens' => 10000]);
    expect($userLimiter->remainingTokens('default'))->toBe(10000);
});
