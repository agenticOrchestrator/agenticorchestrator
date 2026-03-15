<?php

declare(strict_types=1);

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\MultiTenancy\Contracts\TenantResolverInterface;
use AgenticOrchestrator\MultiTenancy\Resolvers\NullResolver;

describe('NullResolver', function () {
    beforeEach(function () {
        $this->resolver = new NullResolver;
    });

    describe('getDriverName', function () {
        it('returns null as the driver name', function () {
            expect($this->resolver->getDriverName())->toBe('null');
        });
    });

    describe('isConfigured', function () {
        it('is always configured', function () {
            expect($this->resolver->isConfigured())->toBeTrue();
        });
    });

    describe('current', function () {
        it('returns null for current tenant', function () {
            expect($this->resolver->current())->toBeNull();
        });
    });

    describe('find', function () {
        it('returns null when finding by integer id', function () {
            expect($this->resolver->find(1))->toBeNull();
        });

        it('returns null when finding by string id', function () {
            expect($this->resolver->find('tenant-abc'))->toBeNull();
        });
    });

    describe('forUser', function () {
        it('returns empty iterable for any user', function () {
            $user = new stdClass;

            $results = $this->resolver->forUser($user);

            expect($results)->toBeArray()
                ->and($results)->toBeEmpty();
        });
    });

    describe('setCurrent', function () {
        it('accepts a tenant without error (no-op)', function () {
            $tenant = Mockery::mock(TenantInterface::class);

            $this->resolver->setCurrent($tenant);

            // NullResolver does not track state, so current still returns null
            expect($this->resolver->current())->toBeNull();
        });

        it('accepts null without error', function () {
            $this->resolver->setCurrent(null);

            expect($this->resolver->current())->toBeNull();
        });
    });

    describe('runAs', function () {
        it('executes callback and returns its result', function () {
            $tenant = Mockery::mock(TenantInterface::class);

            $result = $this->resolver->runAs($tenant, function () {
                return 'callback-result';
            });

            expect($result)->toBe('callback-result');
        });

        it('executes callback without any context switching', function () {
            $tenant = Mockery::mock(TenantInterface::class);
            $callbackExecuted = false;

            $this->resolver->runAs($tenant, function () use (&$callbackExecuted) {
                $callbackExecuted = true;
            });

            expect($callbackExecuted)->toBeTrue();
        });

        it('returns typed values from callback', function () {
            $tenant = Mockery::mock(TenantInterface::class);

            $intResult = $this->resolver->runAs($tenant, fn () => 42);
            expect($intResult)->toBe(42);

            $arrayResult = $this->resolver->runAs($tenant, fn () => ['key' => 'value']);
            expect($arrayResult)->toBe(['key' => 'value']);

            $nullResult = $this->resolver->runAs($tenant, fn () => null);
            expect($nullResult)->toBeNull();
        });

        it('propagates exceptions from the callback', function () {
            $tenant = Mockery::mock(TenantInterface::class);

            expect(fn () => $this->resolver->runAs($tenant, function () {
                throw new RuntimeException('Callback error');
            }))->toThrow(RuntimeException::class, 'Callback error');
        });
    });

    describe('implements TenantResolverInterface', function () {
        it('implements the contract interface', function () {
            expect($this->resolver)->toBeInstanceOf(
                TenantResolverInterface::class
            );
        });
    });
});
