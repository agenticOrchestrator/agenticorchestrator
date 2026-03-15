<?php

declare(strict_types=1);

namespace AgenticOrchestrator\MultiTenancy\Resolvers;

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\MultiTenancy\Tenant;
use Stancl\Tenancy\Tenancy;

/**
 * Stancl Tenancy Resolver - Resolves tenants from stancl/tenancy.
 *
 * Works with Stancl Tenancy's tenant management:
 * - Uses tenancy()->tenant for current tenant
 * - Supports tenant database separation
 * - Integrates with Stancl's tenant identification
 */
class StanclTenancyResolver extends AbstractResolver
{
    /**
     * Get the driver name.
     */
    public function getDriverName(): string
    {
        return 'stancl';
    }

    /**
     * Check if the resolver is properly configured.
     */
    public function isConfigured(): bool
    {
        return class_exists(Tenancy::class);
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
        // Stancl doesn't have built-in user-tenant relationships
        // Check if the user model has a tenants relationship
        if (method_exists($user, 'tenants')) {
            foreach ($user->tenants as $tenant) {
                yield $this->wrapTenant($tenant);
            }

            return;
        }

        // Check for a pivot table relationship
        if (method_exists($user, 'belongsToManyTenants')) {
            foreach ($user->belongsToManyTenants as $tenant) {
                yield $this->wrapTenant($tenant);
            }
        }
    }

    /**
     * Set the current tenant context.
     */
    public function setCurrent(?TenantInterface $tenant): void
    {
        parent::setCurrent($tenant);

        if (! $this->isConfigured()) {
            return;
        }

        if ($tenant === null) {
            tenancy()->end();
        } else {
            $model = $tenant->getModel();

            if (method_exists($model, 'makeCurrent')) {
                $model->makeCurrent();
            } else {
                tenancy()->initialize($model);
            }
        }
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
        if (! $this->isConfigured()) {
            return $callback();
        }

        $model = $tenant->getModel();

        // Stancl tenants have a run() method for scoped execution
        if (method_exists($model, 'run')) {
            return $model->run($callback);
        }

        return parent::runAs($tenant, $callback);
    }

    /**
     * Resolve the current tenant from Stancl Tenancy.
     */
    protected function resolveCurrentTenant(): ?TenantInterface
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $tenant = tenancy()->tenant;

        return $tenant ? $this->wrapTenant($tenant) : null;
    }

    /**
     * Wrap a Stancl Tenant model.
     */
    protected function wrapTenant(object $model): TenantInterface
    {
        if ($model instanceof TenantInterface) {
            return $model;
        }

        return new Tenant($model, [
            'key' => fn ($m) => $m->getTenantKey(),
            'name' => fn ($m) => $m->getAttribute('name')
                ?? $m->getAttribute('id')
                ?? $m->getTenantKey(),
            'config' => fn ($m) => $m->getAttribute('data') ?? [],
        ]);
    }

    /**
     * Get the Tenant model class.
     */
    protected function getTenantModel(): string
    {
        if (! empty($this->config['model'])) {
            return $this->config['model'];
        }

        return config('tenancy.tenant_model', 'App\\Models\\Tenant');
    }
}
