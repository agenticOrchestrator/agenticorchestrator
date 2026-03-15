<?php

declare(strict_types=1);

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\MultiTenancy\Contracts\TenantResolverInterface;
use AgenticOrchestrator\MultiTenancy\Resolvers\GenericResolver;
use AgenticOrchestrator\MultiTenancy\Resolvers\NullResolver;
use AgenticOrchestrator\MultiTenancy\Tenant;
use AgenticOrchestrator\MultiTenancy\TenantManager;
use Illuminate\Container\Container;

// Custom resolver for testing
class TestResolver implements TenantResolverInterface
{
    public ?TenantInterface $current = null;

    public function current(): ?TenantInterface
    {
        return $this->current;
    }

    public function find(int|string $id): ?TenantInterface
    {
        return null;
    }

    public function forUser(object $user): iterable
    {
        return [];
    }

    public function setCurrent(?TenantInterface $tenant): void
    {
        $this->current = $tenant;
    }

    public function runAs(TenantInterface $tenant, callable $callback): mixed
    {
        $previous = $this->current;
        $this->current = $tenant;

        try {
            return $callback();
        } finally {
            $this->current = $previous;
        }
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function getDriverName(): string
    {
        return 'test';
    }
}

beforeEach(function () {
    $this->container = new Container;
    $this->config = [
        'enabled' => true,
        'driver' => 'null',
    ];
    $this->manager = new TenantManager($this->container, $this->config);
});

test('creates null resolver when disabled', function () {
    $manager = new TenantManager($this->container, ['enabled' => false]);

    expect($manager->resolver())->toBeInstanceOf(NullResolver::class);
    expect($manager->isEnabled())->toBeFalse();
});

test('creates null resolver for null driver', function () {
    $manager = new TenantManager($this->container, [
        'enabled' => true,
        'driver' => 'null',
    ]);

    expect($manager->resolver())->toBeInstanceOf(NullResolver::class);
});

test('returns null current tenant when disabled', function () {
    $manager = new TenantManager($this->container, ['enabled' => false]);

    expect($manager->current())->toBeNull();
});

test('extends with custom resolver', function () {
    $this->manager->extend('custom', function ($container, $config) {
        return new TestResolver;
    });

    $manager = new TenantManager($this->container, [
        'enabled' => true,
        'driver' => 'custom',
    ]);
    $manager->extend('custom', function ($container, $config) {
        return new TestResolver;
    });

    expect($manager->resolver())->toBeInstanceOf(TestResolver::class);
});

test('sets resolver directly', function () {
    $resolver = new TestResolver;
    $this->manager->setResolver($resolver);

    expect($this->manager->resolver())->toBe($resolver);
});

test('runs callback in tenant context', function () {
    $resolver = new TestResolver;
    $this->manager->setResolver($resolver);

    $model = new stdClass;
    $model->id = 1;
    $model->name = 'Test';

    $tenant = Tenant::fromModel($model);

    $result = $this->manager->runAs($tenant, function () use ($resolver) {
        return $resolver->current()?->getTenantKey();
    });

    expect($result)->toBe(1);
    expect($resolver->current())->toBeNull(); // Restored after callback
});

test('reports driver name', function () {
    expect($this->manager->getDriver())->toBe('null');
});

test('is configured returns true for null resolver', function () {
    expect($this->manager->isConfigured())->toBeTrue();
});

test('throws for unknown driver', function () {
    $manager = new TenantManager($this->container, [
        'enabled' => true,
        'driver' => 'unknown_driver',
    ]);

    $manager->resolver();
})->throws(InvalidArgumentException::class);

test('auto driver falls back to null when no packages installed', function () {
    $manager = new TenantManager($this->container, [
        'enabled' => true,
        'driver' => 'auto',
    ]);

    // In test environment without Jetstream/Stancl/Spatie, should fall back to null
    expect($manager->resolver())->toBeInstanceOf(NullResolver::class);
});

test('creates null resolver for none driver', function () {
    $manager = new TenantManager($this->container, [
        'enabled' => true,
        'driver' => 'none',
    ]);

    expect($manager->resolver())->toBeInstanceOf(NullResolver::class);
});

test('delegates find to resolver', function () {
    $resolver = Mockery::mock(TenantResolverInterface::class);
    $resolver->shouldReceive('find')
        ->once()
        ->with(42)
        ->andReturn(null);

    $this->manager->setResolver($resolver);

    expect($this->manager->find(42))->toBeNull();
});

test('delegates forUser to resolver', function () {
    $user = new stdClass;
    $resolver = Mockery::mock(TenantResolverInterface::class);
    $resolver->shouldReceive('forUser')
        ->once()
        ->with($user)
        ->andReturn([]);

    $this->manager->setResolver($resolver);

    expect($this->manager->forUser($user))->toBe([]);
});

test('delegates setCurrent to resolver', function () {
    $tenant = Mockery::mock(TenantInterface::class);
    $resolver = Mockery::mock(TenantResolverInterface::class);
    $resolver->shouldReceive('setCurrent')
        ->once()
        ->with($tenant);

    $this->manager->setResolver($resolver);

    $this->manager->setCurrent($tenant);
});

test('runAsId finds tenant and runs callback', function () {
    $model = new stdClass;
    $model->id = 5;
    $model->name = 'Found Tenant';
    $tenant = Tenant::fromModel($model);

    $resolver = new TestResolver;
    // Make find return a tenant by overriding temporarily with a mock
    $mockResolver = Mockery::mock(TenantResolverInterface::class);
    $mockResolver->shouldReceive('find')
        ->with(5)
        ->andReturn($tenant);
    $mockResolver->shouldReceive('runAs')
        ->once()
        ->andReturnUsing(function ($t, $cb) {
            return $cb();
        });

    $this->manager->setResolver($mockResolver);

    $result = $this->manager->runAsId(5, function () {
        return 'callback_result';
    });

    expect($result)->toBe('callback_result');
});

test('runAsId throws when tenant not found', function () {
    $resolver = Mockery::mock(TenantResolverInterface::class);
    $resolver->shouldReceive('find')
        ->with(999)
        ->andReturn(null);

    $this->manager->setResolver($resolver);

    $this->manager->runAsId(999, function () {
        return 'should not run';
    });
})->throws(InvalidArgumentException::class, 'Tenant [999] not found');

test('caches resolver after first creation', function () {
    $resolver1 = $this->manager->resolver();
    $resolver2 = $this->manager->resolver();

    expect($resolver1)->toBe($resolver2);
});

test('isEnabled defaults to true when not set', function () {
    $manager = new TenantManager($this->container, []);

    expect($manager->isEnabled())->toBeTrue();
});

test('getDriver defaults to auto when not set', function () {
    $manager = new TenantManager($this->container, []);

    expect($manager->getDriver())->toBe('auto');
});

test('auto driver uses generic resolver when model config present', function () {
    $manager = new TenantManager($this->container, [
        'enabled' => true,
        'driver' => 'auto',
        'model' => 'App\\Models\\Team',
    ]);

    // In test env without Jetstream/Stancl/Spatie/Filament, but with model config
    $resolver = $manager->resolver();
    expect($resolver)->toBeInstanceOf(GenericResolver::class);
});
