<?php

declare(strict_types=1);

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\MultiTenancy\Resolvers\FilamentResolver;
use AgenticOrchestrator\MultiTenancy\Tenant;

describe('FilamentResolver', function () {
    beforeEach(function () {
        $this->resolver = new FilamentResolver(app(), []);
    });

    it('getDriverName returns filament', function () {
        expect($this->resolver->getDriverName())->toBe('filament');
    });

    it('isConfigured returns false when Filament class does not exist', function () {
        expect($this->resolver->isConfigured())->toBeFalse();
    });

    it('find returns null when not configured', function () {
        expect($this->resolver->find('tenant-1'))->toBeNull();
    });

    it('forUser returns empty when not configured', function () {
        $user = new stdClass;
        $result = iterator_to_array($this->resolver->forUser($user));

        expect($result)->toBe([]);
    });

    it('setCurrent with null', function () {
        $this->resolver->setCurrent(null);

        expect($this->resolver->current())->toBeNull();
    });

    it('setCurrent with tenant stores tenant', function () {
        $tenant = Mockery::mock(TenantInterface::class);

        $this->resolver->setCurrent($tenant);

        expect($this->resolver->current())->toBe($tenant);
    });

    it('resolveCurrentTenant returns null when not configured', function () {
        $reflection = new ReflectionMethod($this->resolver, 'resolveCurrentTenant');

        $result = $reflection->invoke($this->resolver);

        expect($result)->toBeNull();
    });

    it('wrapTenant returns model as-is when already TenantInterface', function () {
        $reflection = new ReflectionMethod($this->resolver, 'wrapTenant');

        $tenant = Mockery::mock(TenantInterface::class);

        $result = $reflection->invoke($this->resolver, $tenant);

        expect($result)->toBe($tenant);
    });

    it('wrapTenant wraps non-TenantInterface model into Tenant', function () {
        $reflection = new ReflectionMethod($this->resolver, 'wrapTenant');

        $model = new stdClass;
        $model->id = 'tenant-1';
        $model->name = 'Test Tenant';

        $result = $reflection->invoke($this->resolver, $model);

        expect($result)->toBeInstanceOf(Tenant::class);
    });

    it('getTenantModel returns null when no config model and not configured', function () {
        $reflection = new ReflectionMethod($this->resolver, 'getTenantModel');

        $result = $reflection->invoke($this->resolver);

        expect($result)->toBeNull();
    });

    it('getTenantModel returns config model when set via constructor', function () {
        $resolver = new FilamentResolver(app(), ['model' => 'App\\Models\\Tenant']);

        $reflection = new ReflectionMethod($resolver, 'getTenantModel');

        $result = $reflection->invoke($resolver);

        expect($result)->toBe('App\\Models\\Tenant');
    });

    it('current returns null when no tenant set and not configured', function () {
        expect($this->resolver->current())->toBeNull();
    });

    it('runAs executes callback in tenant context', function () {
        $tenant = Mockery::mock(TenantInterface::class);
        $callbackExecuted = false;

        $result = $this->resolver->runAs($tenant, function () use (&$callbackExecuted) {
            $callbackExecuted = true;

            return 'result';
        });

        expect($callbackExecuted)->toBeTrue()
            ->and($result)->toBe('result');
    });
});
