<?php

declare(strict_types=1);

namespace AgenticOrchestrator\MultiTenancy\Resolvers;

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\MultiTenancy\Tenant;
use Illuminate\Support\Facades\Auth;

/**
 * Generic Resolver - Resolves tenants from a custom Eloquent model.
 *
 * Works with any Eloquent model that represents a tenant:
 * - Configurable model class
 * - Configurable field mappings
 * - Supports custom user-tenant relationships
 */
class GenericResolver extends AbstractResolver
{
    /**
     * Get the driver name.
     */
    public function getDriverName(): string
    {
        return 'generic';
    }

    /**
     * Check if the resolver is properly configured.
     */
    public function isConfigured(): bool
    {
        $model = $this->config['model'] ?? null;

        return $model !== null && class_exists($model);
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

        // Get configured relationship name
        $relationship = $this->config['user_relationship'] ?? null;

        if ($relationship && method_exists($user, $relationship)) {
            foreach ($user->{$relationship} as $tenant) {
                yield $this->wrapTenant($tenant);
            }

            return;
        }

        // Try common relationship names
        foreach (['teams', 'tenants', 'organizations', 'companies', 'workspaces'] as $relation) {
            if (method_exists($user, $relation)) {
                foreach ($user->{$relation} as $tenant) {
                    yield $this->wrapTenant($tenant);
                }

                return;
            }
        }

        // Check if tenant has a user_id (owned tenancy)
        $tenantModel = $this->getTenantModel();
        $userKey = $this->config['owner_key'] ?? 'user_id';

        if (method_exists($tenantModel, 'where')) {
            foreach ($tenantModel::where($userKey, $user->getKey())->get() as $tenant) {
                yield $this->wrapTenant($tenant);
            }
        }
    }

    /**
     * Resolve the current tenant.
     */
    protected function resolveCurrentTenant(): ?TenantInterface
    {
        // Try configured resolver callback
        $resolver = $this->config['resolver'] ?? null;

        if ($resolver instanceof \Closure) {
            $tenant = $resolver($this->container);

            return $tenant ? $this->wrapTenant($tenant) : null;
        }

        // Try session-based current tenant
        $sessionKey = $this->config['session_key'] ?? 'current_tenant_id';
        $tenantId = session($sessionKey);

        if ($tenantId) {
            return $this->find($tenantId);
        }

        // Try to get from authenticated user
        $user = Auth::user();

        if ($user) {
            // Check for currentTeam/currentTenant method
            foreach (['currentTeam', 'currentTenant', 'current_team', 'current_tenant'] as $method) {
                if (method_exists($user, $method)) {
                    $tenant = $user->{$method}();

                    return $tenant ? $this->wrapTenant($tenant) : null;
                }
            }

            // Check for current_team_id attribute
            foreach (['current_team_id', 'current_tenant_id', 'team_id', 'tenant_id'] as $attr) {
                $tenantId = $user->{$attr} ?? null;

                if ($tenantId) {
                    return $this->find($tenantId);
                }
            }
        }

        return null;
    }

    /**
     * Wrap a tenant model.
     */
    protected function wrapTenant(object $model): TenantInterface
    {
        if ($model instanceof TenantInterface) {
            return $model;
        }

        // Use configured field mappings
        $mapping = [
            'key' => $this->config['key_field'] ?? 'id',
            'name' => $this->config['name_field'] ?? 'name',
            'owner' => $this->config['owner_field'] ?? 'owner',
        ];

        // Add custom has_member check if relationship is configured
        $memberRelation = $this->config['members_relationship'] ?? null;

        if ($memberRelation) {
            $mapping['has_member'] = fn ($m, $u) => method_exists($m, $memberRelation)
                && $m->{$memberRelation}->contains($u);
        }

        return new Tenant($model, $mapping);
    }

    /**
     * Get the Tenant model class.
     */
    protected function getTenantModel(): string
    {
        return $this->config['model'] ?? 'App\\Models\\Team';
    }
}
