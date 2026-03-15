<?php

declare(strict_types=1);

namespace AgenticOrchestrator\MultiTenancy\Contracts;

/**
 * Tenant Interface - Abstract representation of a tenant.
 *
 * This interface provides a common contract for tenant entities,
 * regardless of the underlying multi-tenancy implementation
 * (Jetstream, Spatie, Stancl, Filament, or custom).
 */
interface TenantInterface
{
    /**
     * Get the tenant's unique identifier.
     */
    public function getTenantKey(): int|string;

    /**
     * Get the tenant's display name.
     */
    public function getTenantName(): string;

    /**
     * Get the tenant's owner (if applicable).
     */
    public function getTenantOwner(): ?object;

    /**
     * Check if a user belongs to this tenant.
     */
    public function hasMember(object $user): bool;

    /**
     * Get the tenant's configuration/settings.
     *
     * @return array<string, mixed>
     */
    public function getTenantConfig(): array;

    /**
     * Get the underlying model instance.
     */
    public function getModel(): object;
}
