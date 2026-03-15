<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Facades;

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\MultiTenancy\TenantManager;
use Illuminate\Support\Facades\Facade;

/**
 * Tenant Facade.
 *
 * @method static TenantInterface|null current()
 * @method static TenantInterface|null find(int|string $id)
 * @method static iterable forUser(object $user)
 * @method static void setCurrent(TenantInterface|null $tenant)
 * @method static mixed runAs(TenantInterface $tenant, callable $callback)
 * @method static mixed runAsId(int|string $id, callable $callback)
 * @method static bool isEnabled()
 * @method static bool isConfigured()
 * @method static string getDriver()
 * @method static void extend(string $driver, callable $factory)
 *
 * @see TenantManager
 */
class Tenant extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'agent-tenancy';
    }
}
