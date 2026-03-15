<?php

declare(strict_types=1);

namespace AgenticOrchestrator\MultiTenancy\Resolvers;

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\MultiTenancy\Contracts\TenantResolverInterface;

/**
 * Null Resolver - Used when multi-tenancy is disabled.
 *
 * Provides a no-op implementation for single-tenant applications.
 * All operations succeed but return null/empty values.
 */
class NullResolver implements TenantResolverInterface
{
    /**
     * Get the currently active tenant.
     */
    public function current(): ?TenantInterface
    {
        return null;
    }

    /**
     * Find a tenant by its identifier.
     */
    public function find(int|string $id): ?TenantInterface
    {
        return null;
    }

    /**
     * Get all tenants accessible by a user.
     *
     * @return iterable<TenantInterface>
     */
    public function forUser(object $user): iterable
    {
        return [];
    }

    /**
     * Set the current tenant context.
     */
    public function setCurrent(?TenantInterface $tenant): void
    {
        // No-op
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
        // Just execute the callback without any context switch
        return $callback();
    }

    /**
     * Check if the resolver is properly configured.
     */
    public function isConfigured(): bool
    {
        return true; // Always "configured" as it requires nothing
    }

    /**
     * Get the driver name.
     */
    public function getDriverName(): string
    {
        return 'null';
    }
}
