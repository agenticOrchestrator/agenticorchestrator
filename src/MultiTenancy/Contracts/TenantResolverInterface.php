<?php

declare(strict_types=1);

namespace AgenticOrchestrator\MultiTenancy\Contracts;

/**
 * Tenant Resolver Interface - Resolves the current tenant context.
 *
 * Implementations handle tenant resolution for different multi-tenancy packages:
 * - Jetstream Teams
 * - Stancl Tenancy
 * - Spatie Laravel Multitenancy
 * - Filament Multi-tenancy
 * - Custom implementations
 */
interface TenantResolverInterface
{
    /**
     * Get the currently active tenant.
     */
    public function current(): ?TenantInterface;

    /**
     * Get a tenant by its identifier.
     */
    public function find(int|string $id): ?TenantInterface;

    /**
     * Get all tenants accessible by a user.
     *
     * @return iterable<TenantInterface>
     */
    public function forUser(object $user): iterable;

    /**
     * Set the current tenant context.
     */
    public function setCurrent(?TenantInterface $tenant): void;

    /**
     * Run a callback within a tenant context.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(TenantInterface $tenant, callable $callback): mixed;

    /**
     * Check if the resolver is properly configured.
     */
    public function isConfigured(): bool;

    /**
     * Get the driver/package name this resolver handles.
     */
    public function getDriverName(): string;
}
