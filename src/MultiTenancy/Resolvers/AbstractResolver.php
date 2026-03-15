<?php

declare(strict_types=1);

namespace AgenticOrchestrator\MultiTenancy\Resolvers;

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\MultiTenancy\Contracts\TenantResolverInterface;
use AgenticOrchestrator\MultiTenancy\Tenant;
use Illuminate\Contracts\Container\Container;

/**
 * Abstract Resolver - Base class for tenant resolvers.
 */
abstract class AbstractResolver implements TenantResolverInterface
{
    /**
     * The currently active tenant.
     */
    protected ?TenantInterface $currentTenant = null;

    /**
     * Create a new resolver instance.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected Container $container,
        protected array $config,
    ) {}

    /**
     * Get the currently active tenant.
     */
    public function current(): ?TenantInterface
    {
        return $this->currentTenant ?? $this->resolveCurrentTenant();
    }

    /**
     * Set the current tenant context.
     */
    public function setCurrent(?TenantInterface $tenant): void
    {
        $this->currentTenant = $tenant;
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
        $previous = $this->current();

        try {
            $this->setCurrent($tenant);

            return $callback();
        } finally {
            $this->setCurrent($previous);
        }
    }

    /**
     * Wrap a model in a Tenant instance.
     */
    protected function wrapTenant(object $model): TenantInterface
    {
        if ($model instanceof TenantInterface) {
            return $model;
        }

        return Tenant::fromModel($model);
    }

    /**
     * Resolve the current tenant from the underlying package.
     */
    abstract protected function resolveCurrentTenant(): ?TenantInterface;
}
