<?php

declare(strict_types=1);

use AgenticOrchestrator\Resilience\FallbackHandler;

test('executes primary when successful', function () {
    $result = FallbackHandler::try(fn () => 'primary result')
        ->fallback(fn () => 'fallback result')
        ->execute();

    expect($result)->toBe('primary result');
});

test('executes fallback when primary fails', function () {
    $result = FallbackHandler::try(fn () => throw new RuntimeException('Primary failed'))
        ->fallback(fn () => 'fallback result')
        ->execute();

    expect($result)->toBe('fallback result');
});

test('chains multiple fallbacks', function () {
    $result = FallbackHandler::try(fn () => throw new RuntimeException('Primary failed'))
        ->fallback(fn () => throw new RuntimeException('Fallback 1 failed'))
        ->fallback(fn () => 'fallback 2 result')
        ->execute();

    expect($result)->toBe('fallback 2 result');
});

test('returns default when all fallbacks fail', function () {
    $result = FallbackHandler::try(fn () => throw new RuntimeException('Primary failed'))
        ->fallback(fn () => throw new RuntimeException('Fallback failed'))
        ->default('default value')
        ->execute();

    expect($result)->toBe('default value');
});

test('throws when no fallback or default', function () {
    expect(function () {
        FallbackHandler::try(fn () => throw new RuntimeException('Error'))
            ->execute();
    })->toThrow(RuntimeException::class);
});

test('throws when all fallbacks fail and no default', function () {
    expect(function () {
        FallbackHandler::try(fn () => throw new RuntimeException('Primary'))
            ->fallback(fn () => throw new RuntimeException('Fallback'))
            ->execute();
    })->toThrow(RuntimeException::class, 'Fallback');
});

test('conditional fallback is executed when condition matches', function () {
    $result = FallbackHandler::try(fn () => throw new RuntimeException('Primary', 500))
        ->fallbackWhen(
            fn ($e) => $e->getCode() === 500,
            fn () => 'server error fallback'
        )
        ->execute();

    expect($result)->toBe('server error fallback');
});

test('conditional fallback is skipped when condition does not match', function () {
    $result = FallbackHandler::try(fn () => throw new RuntimeException('Primary', 400))
        ->fallbackWhen(
            fn ($e) => $e->getCode() === 500,
            fn () => 'server error fallback'
        )
        ->fallback(fn () => 'general fallback')
        ->execute();

    expect($result)->toBe('general fallback');
});

test('fallback for specific exception type', function () {
    $result = FallbackHandler::try(fn () => throw new InvalidArgumentException('Bad input'))
        ->fallbackFor(InvalidArgumentException::class, fn () => 'validation fallback')
        ->fallback(fn () => 'general fallback')
        ->execute();

    expect($result)->toBe('validation fallback');
});

test('only triggers fallback on specified exceptions', function () {
    expect(function () {
        FallbackHandler::try(fn () => throw new InvalidArgumentException('Error'))
            ->fallbackOn([RuntimeException::class])
            ->fallback(fn () => 'fallback')
            ->execute();
    })->toThrow(InvalidArgumentException::class);
});

test('does not trigger fallback on excluded exceptions', function () {
    expect(function () {
        FallbackHandler::try(fn () => throw new InvalidArgumentException('Error'))
            ->dontFallbackOn([InvalidArgumentException::class])
            ->fallback(fn () => 'fallback')
            ->execute();
    })->toThrow(InvalidArgumentException::class);
});

test('on fallback callback is called', function () {
    $fallbackUsed = null;

    $result = FallbackHandler::try(fn () => throw new RuntimeException('Error'))
        ->fallback(fn () => 'fallback result', 'my-fallback')
        ->onFallback(function ($name, $exception) use (&$fallbackUsed) {
            $fallbackUsed = $name;
        })
        ->execute();

    expect($fallbackUsed)->toBe('my-fallback');
    expect($result)->toBe('fallback result');
});

test('named fallbacks', function () {
    $fallbackUsed = null;

    FallbackHandler::try(fn () => throw new RuntimeException('Error'))
        ->fallback(fn ($e) => throw $e, 'first-fallback')
        ->fallback(fn () => 'result', 'second-fallback')
        ->onFallback(function ($name) use (&$fallbackUsed) {
            $fallbackUsed = $name;
        })
        ->execute();

    expect($fallbackUsed)->toBe('second-fallback');
});

test('fallback receives original exception', function () {
    $receivedException = null;

    FallbackHandler::try(fn () => throw new RuntimeException('Original error'))
        ->fallback(function ($exception) use (&$receivedException) {
            $receivedException = $exception;

            return 'handled';
        })
        ->execute();

    expect($receivedException)->toBeInstanceOf(RuntimeException::class);
    expect($receivedException->getMessage())->toBe('Original error');
});

test('throws when no primary operation defined', function () {
    expect(function () {
        (new FallbackHandler)->execute();
    })->toThrow(RuntimeException::class, 'No primary operation defined');
});

test('primary can be set via method', function () {
    $result = (new FallbackHandler)
        ->primary(fn () => 'primary result')
        ->execute();

    expect($result)->toBe('primary result');
});
