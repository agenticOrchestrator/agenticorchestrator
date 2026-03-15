<?php

declare(strict_types=1);

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\MultiTenancy\Resolvers\GenericResolver;
use Illuminate\Contracts\Container\Container;

function createGenericResolver(array $config = []): GenericResolver
{
    $container = Mockery::mock(Container::class);

    return new GenericResolver($container, $config);
}

function createMockTenant(int $id = 1, string $name = 'Test Tenant'): TenantInterface
{
    $tenant = Mockery::mock(TenantInterface::class);
    $tenant->shouldReceive('getTenantKey')->andReturn($id);
    $tenant->shouldReceive('getTenantName')->andReturn($name);

    return $tenant;
}

describe('GenericResolver', function () {
    it('returns generic as driver name', function () {
        $resolver = createGenericResolver();

        expect($resolver->getDriverName())->toBe('generic');
    });

    it('is not configured without model class', function () {
        $resolver = createGenericResolver([]);

        expect($resolver->isConfigured())->toBeFalse();
    });

    it('is not configured with non-existent model class', function () {
        $resolver = createGenericResolver(['model' => 'App\\Models\\NonExistentModel']);

        expect($resolver->isConfigured())->toBeFalse();
    });

    it('is configured with valid model class', function () {
        $resolver = createGenericResolver(['model' => stdClass::class]);

        expect($resolver->isConfigured())->toBeTrue();
    });

    it('sets and gets current tenant', function () {
        $resolver = createGenericResolver();
        $tenant = createMockTenant(1, 'My Tenant');

        $resolver->setCurrent($tenant);

        expect($resolver->current())->toBe($tenant);
        expect($resolver->current()->getTenantKey())->toBe(1);
    });

    it('clears current tenant when set to null', function () {
        $resolver = createGenericResolver();
        $tenant = createMockTenant(1, 'My Tenant');

        $resolver->setCurrent($tenant);
        $resolver->setCurrent(null);

        $reflection = new ReflectionClass($resolver);
        $prop = $reflection->getProperty('currentTenant');
        $prop->setAccessible(true);

        expect($prop->getValue($resolver))->toBeNull();
    });

    it('runs callback within tenant context', function () {
        $resolver = createGenericResolver();
        $tenantA = createMockTenant(1, 'Tenant A');
        $tenantB = createMockTenant(2, 'Tenant B');

        $resolver->setCurrent($tenantA);

        $result = $resolver->runAs($tenantB, function () use ($resolver) {
            return $resolver->current()->getTenantKey();
        });

        expect($result)->toBe(2);
        expect($resolver->current()->getTenantKey())->toBe(1);
    });

    it('restores previous tenant after runAs even on exception', function () {
        $resolver = createGenericResolver();
        $tenantA = createMockTenant(1, 'Tenant A');
        $tenantB = createMockTenant(2, 'Tenant B');

        $resolver->setCurrent($tenantA);

        try {
            $resolver->runAs($tenantB, function () {
                throw new RuntimeException('Test error');
            });
        } catch (RuntimeException) {
            // Expected
        }

        expect($resolver->current()->getTenantKey())->toBe(1);
    });

    it('returns null from find when not configured', function () {
        $resolver = createGenericResolver([]);

        expect($resolver->find(1))->toBeNull();
    });

    it('returns empty iterable from forUser when not configured', function () {
        $resolver = createGenericResolver([]);
        $user = new stdClass;

        $results = iterator_to_array($resolver->forUser($user));

        expect($results)->toBeEmpty();
    });

    it('resolves current tenant via closure resolver config', function () {
        $mockTenant = createMockTenant(42, 'Resolved Tenant');
        $container = Mockery::mock(Container::class);

        $resolver = new GenericResolver($container, [
            'resolver' => function ($c) use ($mockTenant) {
                return $mockTenant;
            },
        ]);

        expect($resolver->current()->getTenantKey())->toBe(42);
    });

    it('resolves null when closure resolver returns null', function () {
        $container = Mockery::mock(Container::class);

        $resolver = new GenericResolver($container, [
            'resolver' => function ($c) {
                return null;
            },
        ]);

        expect($resolver->current())->toBeNull();
    });

    it('defaults to App Models Team when model not configured', function () {
        $resolver = createGenericResolver([]);

        $reflection = new ReflectionClass($resolver);
        $method = $reflection->getMethod('getTenantModel');
        $method->setAccessible(true);

        expect($method->invoke($resolver))->toBe('App\\Models\\Team');
    });

    it('uses configured model class', function () {
        $resolver = createGenericResolver(['model' => 'App\\Models\\Organization']);

        $reflection = new ReflectionClass($resolver);
        $method = $reflection->getMethod('getTenantModel');
        $method->setAccessible(true);

        expect($method->invoke($resolver))->toBe('App\\Models\\Organization');
    });

    it('wraps TenantInterface objects without re-wrapping', function () {
        $resolver = createGenericResolver(['model' => stdClass::class]);
        $tenant = createMockTenant(10, 'Direct Tenant');

        $resolver->setCurrent($tenant);

        expect($resolver->current())->toBe($tenant);
    });

    it('returns callback result from runAs', function () {
        $resolver = createGenericResolver();
        $tenant = createMockTenant(1, 'Tenant');

        $result = $resolver->runAs($tenant, function () {
            return 'callback-result';
        });

        expect($result)->toBe('callback-result');
    });
});
