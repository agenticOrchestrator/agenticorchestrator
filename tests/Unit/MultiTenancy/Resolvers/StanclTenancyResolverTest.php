<?php

declare(strict_types=1);

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\MultiTenancy\Resolvers\StanclTenancyResolver;
use AgenticOrchestrator\MultiTenancy\Tenant;

describe('StanclTenancyResolver', function () {
    beforeEach(function () {
        $this->resolver = new StanclTenancyResolver(app(), []);
    });

    it('getDriverName returns stancl', function () {
        expect($this->resolver->getDriverName())->toBe('stancl');
    });

    it('isConfigured returns false when Stancl Tenancy is not installed', function () {
        expect($this->resolver->isConfigured())->toBeFalse();
    });

    it('find returns null when not configured', function () {
        expect($this->resolver->find('tenant-1'))->toBeNull();
    });

    it('forUser with user that has tenants method', function () {
        $tenantModel = new stdClass;
        $tenantModel->id = 'tenant-3';
        $tenantModel->name = 'Stancl Tenant';

        $user = new class($tenantModel)
        {
            public array $tenants;

            public function __construct($tenant)
            {
                $this->tenants = [$tenant];
            }

            public function tenants(): void {} // method_exists check
        };

        $result = iterator_to_array($this->resolver->forUser($user));

        expect($result)->toHaveCount(1)
            ->and($result[0])->toBeInstanceOf(Tenant::class);
    });

    it('forUser with user that has no tenants method', function () {
        $user = new stdClass;

        $result = iterator_to_array($this->resolver->forUser($user));

        expect($result)->toBe([]);
    });

    it('setCurrent stores tenant locally when not configured', function () {
        $tenant = Mockery::mock(TenantInterface::class);

        $this->resolver->setCurrent($tenant);

        expect($this->resolver->current())->toBe($tenant);
    });

    it('setCurrent with null clears tenant', function () {
        $this->resolver->setCurrent(null);

        expect($this->resolver->current())->toBeNull();
    });

    it('runAs when not configured calls callback directly', function () {
        $tenant = Mockery::mock(TenantInterface::class);
        $callbackExecuted = false;

        $result = $this->resolver->runAs($tenant, function () use (&$callbackExecuted) {
            $callbackExecuted = true;

            return 'stancl-callback-result';
        });

        expect($callbackExecuted)->toBeTrue()
            ->and($result)->toBe('stancl-callback-result');
    });

    it('resolveCurrentTenant returns null when not configured', function () {
        $reflection = new ReflectionMethod($this->resolver, 'resolveCurrentTenant');

        $result = $reflection->invoke($this->resolver);

        expect($result)->toBeNull();
    });

    it('wrapTenant wraps model into Tenant when not TenantInterface', function () {
        $reflection = new ReflectionMethod($this->resolver, 'wrapTenant');

        $model = new stdClass;
        $model->id = 'tenant-4';
        $model->name = 'Wrapped Stancl';

        $result = $reflection->invoke($this->resolver, $model);

        expect($result)->toBeInstanceOf(Tenant::class);
    });

    it('wrapTenant returns model as-is when already TenantInterface', function () {
        $reflection = new ReflectionMethod($this->resolver, 'wrapTenant');

        $tenant = Mockery::mock(TenantInterface::class);

        $result = $reflection->invoke($this->resolver, $tenant);

        expect($result)->toBe($tenant);
    });

    it('getTenantModel returns config model when set via constructor', function () {
        $resolver = new StanclTenancyResolver(app(), ['model' => 'App\\Models\\StanclTenant']);

        $reflection = new ReflectionMethod($resolver, 'getTenantModel');

        $result = $reflection->invoke($resolver);

        expect($result)->toBe('App\\Models\\StanclTenant');
    });

    it('getTenantModel returns default when config model not set', function () {
        $reflection = new ReflectionMethod($this->resolver, 'getTenantModel');

        $result = $reflection->invoke($this->resolver);

        expect($result)->toBeString();
    });

    it('current returns null when no tenant set and not configured', function () {
        expect($this->resolver->current())->toBeNull();
    });
});
