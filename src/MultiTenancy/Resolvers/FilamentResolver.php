<?php

declare(strict_types=1);

namespace AgenticOrchestrator\MultiTenancy\Resolvers;

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\MultiTenancy\Tenant;
use Filament\Facades\Filament;

/**
 * Filament Resolver - Resolves tenants from Filament's multi-tenancy.
 *
 * Works with Filament's panel-based tenancy:
 * - Uses Filament::getTenant() for current tenant
 * - Supports owned tenancy (teams, organizations)
 * - Integrates with Filament's panel system
 */
class FilamentResolver extends AbstractResolver
{
    /**
     * Get the driver name.
     */
    public function getDriverName(): string
    {
        return 'filament';
    }

    /**
     * Check if the resolver is properly configured.
     */
    public function isConfigured(): bool
    {
        return class_exists(Filament::class);
    }

    /**
     * Find a tenant by its identifier.
     */
    public function find(int|string $id): ?TenantInterface
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $tenantModel = $this->getTenantModel();

        if ($tenantModel === null) {
            return null;
        }

        $tenant = $tenantModel::find($id);

        return $tenant ? $this->wrapTenant($tenant) : null;
    }

    /**
     * Get all tenants accessible by a user.
     *
     * @return iterable<TenantInterface>
     */
    public function forUser(object $user): iterable
    {
        if (! $this->isConfigured()) {
            return [];
        }

        // Get tenants from Filament's panel configuration
        $panel = Filament::getCurrentPanel();

        if ($panel && $panel->hasTenancy()) {
            $tenantRelationship = $panel->getTenantOwnershipRelationshipName();

            if ($tenantRelationship && method_exists($user, $tenantRelationship)) {
                foreach ($user->{$tenantRelationship} as $tenant) {
                    yield $this->wrapTenant($tenant);
                }

                return;
            }
        }

        // Fallback: check common relationship names
        foreach (['teams', 'organizations', 'companies', 'tenants'] as $relation) {
            if (method_exists($user, $relation)) {
                foreach ($user->{$relation} as $tenant) {
                    yield $this->wrapTenant($tenant);
                }

                return;
            }
        }
    }

    /**
     * Set the current tenant context.
     */
    public function setCurrent(?TenantInterface $tenant): void
    {
        parent::setCurrent($tenant);

        // Filament manages tenant context internally through panels
        // We store it locally but can't force Filament to switch
    }

    /**
     * Resolve the current tenant from Filament.
     */
    protected function resolveCurrentTenant(): ?TenantInterface
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $tenant = Filament::getTenant();

            return $tenant ? $this->wrapTenant($tenant) : null;
        } catch (\Exception) {
            // Filament might not have a current panel/tenant
            return null;
        }
    }

    /**
     * Wrap a Filament tenant model.
     */
    protected function wrapTenant(object $model): TenantInterface
    {
        if ($model instanceof TenantInterface) {
            return $model;
        }

        return new Tenant($model, [
            'key' => 'id',
            'name' => fn ($m) => $m->name
                ?? (method_exists($m, 'getFilamentName') ? $m->getFilamentName() : class_basename($m)),
            'owner' => fn ($m) => property_exists($m, 'owner') ? $m->owner : null,
            'has_member' => function ($m, $u) {
                // Check various relationship patterns
                if (method_exists($m, 'users') && $m->users->contains($u)) {
                    return true;
                }
                if (method_exists($m, 'members') && $m->members->contains($u)) {
                    return true;
                }
                // Check if user owns this tenant
                if (property_exists($m, 'user_id') && $m->user_id === $u->id) {
                    return true;
                }

                return false;
            },
        ]);
    }

    /**
     * Get the Tenant model class.
     */
    protected function getTenantModel(): ?string
    {
        if (! empty($this->config['model'])) {
            return $this->config['model'];
        }

        // Try to get from Filament panel
        if ($this->isConfigured()) {
            try {
                $panel = Filament::getCurrentPanel();

                if ($panel && $panel->hasTenancy()) {
                    return $panel->getTenantModel();
                }
            } catch (\Exception) {
                // Panel might not be available
            }
        }

        return null;
    }
}
