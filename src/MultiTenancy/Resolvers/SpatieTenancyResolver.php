<?php

declare(strict_types=1);

namespace AgenticOrchestrator\MultiTenancy\Resolvers;

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\MultiTenancy\Tenant;
use Spatie\Multitenancy\Multitenancy;

/**
 * Spatie Tenancy Resolver - Resolves tenants from spatie/laravel-multitenancy.
 *
 * Works with Spatie's tenant management:
 * - Uses Multitenancy::current() for current tenant
 * - Supports Spatie's tenant tasks system
 * - Integrates with database switching
 */
class SpatieTenancyResolver extends AbstractResolver
{
    /**
     * Get the driver name.
     */
    public function getDriverName(): string
    {
        return 'spatie';
    }

    /**
     * Check if the resolver is properly configured.
     */
    public function isConfigured(): bool
    {
        return class_exists(Multitenancy::class);
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
        // Check if user model has tenants relationship
        if (method_exists($user, 'tenants')) {
            foreach ($user->tenants as $tenant) {
                yield $this->wrapTenant($tenant);
            }

            return;
        }

        // Check if tenant model has users and we need to filter
        $tenantModel = $this->getTenantModel();

        if (method_exists($tenantModel, 'forUser')) {
            foreach ($tenantModel::forUser($user)->get() as $tenant) {
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
            Multitenancy::forgetCurrentTenant();
        } else {
            $model = $tenant->getModel();
            $model->makeCurrent();
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

        // Spatie tenants have execute() method for scoped execution
        if (method_exists($model, 'execute')) {
            return $model->execute($callback);
        }

        return parent::runAs($tenant, $callback);
    }

    /**
     * Resolve the current tenant from Spatie Multitenancy.
     */
    protected function resolveCurrentTenant(): ?TenantInterface
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $tenant = Multitenancy::current();

        return $tenant ? $this->wrapTenant($tenant) : null;
    }

    /**
     * Wrap a Spatie Tenant model.
     */
    protected function wrapTenant(object $model): TenantInterface
    {
        if ($model instanceof TenantInterface) {
            return $model;
        }

        return new Tenant($model, [
            'key' => 'id',
            'name' => 'name',
            'config' => fn ($m) => [
                'database' => $m->database ?? null,
                'domain' => $m->domain ?? null,
            ],
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

        return config('multitenancy.tenant_model', 'App\\Models\\Tenant');
    }
}
