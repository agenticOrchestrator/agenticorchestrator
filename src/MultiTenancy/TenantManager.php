<?php

declare(strict_types=1);

namespace AgenticOrchestrator\MultiTenancy;

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\MultiTenancy\Contracts\TenantResolverInterface;
use AgenticOrchestrator\MultiTenancy\Resolvers\FilamentResolver;
use AgenticOrchestrator\MultiTenancy\Resolvers\GenericResolver;
use AgenticOrchestrator\MultiTenancy\Resolvers\JetstreamResolver;
use AgenticOrchestrator\MultiTenancy\Resolvers\NullResolver;
use AgenticOrchestrator\MultiTenancy\Resolvers\SpatieTenancyResolver;
use AgenticOrchestrator\MultiTenancy\Resolvers\StanclTenancyResolver;
use Filament\Panel;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Laravel\Jetstream\Jetstream;
use Spatie\Multitenancy\Multitenancy;
use Stancl\Tenancy\Tenancy;

/**
 * Tenant Manager - Central hub for multi-tenancy operations.
 *
 * Supports multiple multi-tenancy packages:
 * - jetstream: Laravel Jetstream Teams
 * - stancl: Stancl Tenancy for Laravel
 * - spatie: Spatie Laravel Multitenancy
 * - filament: Filament Multi-tenancy
 * - generic: Custom Eloquent model
 * - null: Disabled (single-tenant mode)
 */
class TenantManager
{
    /**
     * The active tenant resolver.
     */
    protected ?TenantResolverInterface $resolver = null;

    /**
     * Custom resolver factories.
     *
     * @var array<string, callable>
     */
    protected array $customResolvers = [];

    /**
     * Create a new tenant manager.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected Container $container,
        protected array $config,
    ) {}

    /**
     * Get the active tenant resolver.
     */
    public function resolver(): TenantResolverInterface
    {
        if ($this->resolver === null) {
            $this->resolver = $this->createResolver();
        }

        return $this->resolver;
    }

    /**
     * Get the current tenant.
     */
    public function current(): ?TenantInterface
    {
        return $this->resolver()->current();
    }

    /**
     * Find a tenant by ID.
     */
    public function find(int|string $id): ?TenantInterface
    {
        return $this->resolver()->find($id);
    }

    /**
     * Get all tenants for a user.
     *
     * @return iterable<TenantInterface>
     */
    public function forUser(object $user): iterable
    {
        return $this->resolver()->forUser($user);
    }

    /**
     * Set the current tenant.
     */
    public function setCurrent(?TenantInterface $tenant): void
    {
        $this->resolver()->setCurrent($tenant);
    }

    /**
     * Run a callback within a tenant context.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(TenantInterface $tenant, callable $callback): mixed
    {
        return $this->resolver()->runAs($tenant, $callback);
    }

    /**
     * Run a callback with a tenant by ID.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runAsId(int|string $id, callable $callback): mixed
    {
        $tenant = $this->find($id);

        if ($tenant === null) {
            throw new InvalidArgumentException("Tenant [{$id}] not found.");
        }

        return $this->runAs($tenant, $callback);
    }

    /**
     * Check if multi-tenancy is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Check if the resolver is properly configured.
     */
    public function isConfigured(): bool
    {
        return $this->resolver()->isConfigured();
    }

    /**
     * Get the active driver name.
     */
    public function getDriver(): string
    {
        return $this->config['driver'] ?? 'auto';
    }

    /**
     * Extend with a custom resolver.
     */
    public function extend(string $driver, callable $factory): void
    {
        $this->customResolvers[$driver] = $factory;
    }

    /**
     * Set the resolver instance directly.
     */
    public function setResolver(TenantResolverInterface $resolver): void
    {
        $this->resolver = $resolver;
    }

    /**
     * Create the tenant resolver based on configuration.
     */
    protected function createResolver(): TenantResolverInterface
    {
        if (! $this->isEnabled()) {
            return new NullResolver;
        }

        $driver = $this->getDriver();

        // Check for custom resolver
        if (isset($this->customResolvers[$driver])) {
            return ($this->customResolvers[$driver])($this->container, $this->config);
        }

        // Auto-detect driver
        if ($driver === 'auto') {
            return $this->autoDetectResolver();
        }

        return match ($driver) {
            'jetstream' => $this->createJetstreamResolver(),
            'stancl' => $this->createStanclResolver(),
            'spatie' => $this->createSpatieResolver(),
            'filament' => $this->createFilamentResolver(),
            'generic' => $this->createGenericResolver(),
            'null', 'none' => new NullResolver,
            default => throw new InvalidArgumentException("Unknown tenancy driver: {$driver}"),
        };
    }

    /**
     * Auto-detect the best resolver based on installed packages.
     */
    protected function autoDetectResolver(): TenantResolverInterface
    {
        // Check for Jetstream with Teams
        if ($this->isJetstreamInstalled()) {
            return $this->createJetstreamResolver();
        }

        // Check for Stancl Tenancy
        if ($this->isStanclInstalled()) {
            return $this->createStanclResolver();
        }

        // Check for Spatie Multitenancy
        if ($this->isSpatieInstalled()) {
            return $this->createSpatieResolver();
        }

        // Check for Filament
        if ($this->isFilamentInstalled()) {
            return $this->createFilamentResolver();
        }

        // Check for generic model configuration
        if (! empty($this->config['model'])) {
            return $this->createGenericResolver();
        }

        // Default to null (disabled)
        return new NullResolver;
    }

    /**
     * Check if Jetstream with Teams is installed.
     */
    protected function isJetstreamInstalled(): bool
    {
        return class_exists(Jetstream::class)
            && config('jetstream.features', [])
            && in_array('teams', config('jetstream.features', []));
    }

    /**
     * Check if Stancl Tenancy is installed.
     */
    protected function isStanclInstalled(): bool
    {
        return class_exists(Tenancy::class);
    }

    /**
     * Check if Spatie Multitenancy is installed.
     */
    protected function isSpatieInstalled(): bool
    {
        return class_exists(Multitenancy::class);
    }

    /**
     * Check if Filament is installed.
     */
    protected function isFilamentInstalled(): bool
    {
        return class_exists(Panel::class);
    }

    /**
     * Create Jetstream Teams resolver.
     */
    protected function createJetstreamResolver(): TenantResolverInterface
    {
        return new JetstreamResolver($this->container, $this->config);
    }

    /**
     * Create Stancl Tenancy resolver.
     */
    protected function createStanclResolver(): TenantResolverInterface
    {
        return new StanclTenancyResolver($this->container, $this->config);
    }

    /**
     * Create Spatie Multitenancy resolver.
     */
    protected function createSpatieResolver(): TenantResolverInterface
    {
        return new SpatieTenancyResolver($this->container, $this->config);
    }

    /**
     * Create Filament resolver.
     */
    protected function createFilamentResolver(): TenantResolverInterface
    {
        return new FilamentResolver($this->container, $this->config);
    }

    /**
     * Create generic model resolver.
     */
    protected function createGenericResolver(): TenantResolverInterface
    {
        return new GenericResolver($this->container, $this->config);
    }
}
