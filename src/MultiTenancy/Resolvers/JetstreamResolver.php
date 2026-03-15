<?php

declare(strict_types=1);

namespace AgenticOrchestrator\MultiTenancy\Resolvers;

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\MultiTenancy\Tenant;
use Illuminate\Support\Facades\Auth;
use Laravel\Jetstream\Jetstream;

/**
 * Jetstream Resolver - Resolves tenants from Laravel Jetstream Teams.
 *
 * Works with Jetstream's built-in team management:
 * - Uses current user's currentTeam as the active tenant
 * - Supports team switching
 * - Integrates with Jetstream's team membership
 */
class JetstreamResolver extends AbstractResolver
{
    /**
     * Get the driver name.
     */
    public function getDriverName(): string
    {
        return 'jetstream';
    }

    /**
     * Check if the resolver is properly configured.
     */
    public function isConfigured(): bool
    {
        return class_exists(Jetstream::class)
            && in_array('teams', config('jetstream.features', []));
    }

    /**
     * Find a tenant by its identifier.
     */
    public function find(int|string $id): ?TenantInterface
    {
        $teamModel = $this->getTeamModel();

        $team = $teamModel::find($id);

        return $team ? $this->wrapTenant($team) : null;
    }

    /**
     * Get all tenants accessible by a user.
     *
     * @return iterable<TenantInterface>
     */
    public function forUser(object $user): iterable
    {
        // Jetstream users have allTeams() method
        if (! method_exists($user, 'allTeams')) {
            return [];
        }

        $teams = $user->allTeams();

        foreach ($teams as $team) {
            yield $this->wrapTenant($team);
        }
    }

    /**
     * Set the current tenant context.
     */
    public function setCurrent(?TenantInterface $tenant): void
    {
        parent::setCurrent($tenant);

        // Also update the user's current team if authenticated
        $user = Auth::user();

        if ($user && $tenant && method_exists($user, 'switchTeam')) {
            $user->switchTeam($tenant->getModel());
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
        $user = Auth::user();
        $previousTeam = $user?->currentTeam;

        try {
            $this->setCurrent($tenant);

            return $callback();
        } finally {
            // Restore previous team
            if ($user && $previousTeam) {
                $user->switchTeam($previousTeam);
            }

            $this->currentTenant = $previousTeam
                ? $this->wrapTenant($previousTeam)
                : null;
        }
    }

    /**
     * Resolve the current tenant from Jetstream.
     */
    protected function resolveCurrentTenant(): ?TenantInterface
    {
        $user = Auth::user();

        if (! $user || ! method_exists($user, 'currentTeam')) {
            return null;
        }

        $team = $user->currentTeam;

        return $team ? $this->wrapTenant($team) : null;
    }

    /**
     * Wrap a Jetstream Team model.
     */
    protected function wrapTenant(object $model): TenantInterface
    {
        if ($model instanceof TenantInterface) {
            return $model;
        }

        return new Tenant($model, [
            'key' => 'id',
            'name' => 'name',
            'owner' => fn ($m) => $m->owner,
            'has_member' => fn ($m, $u) => $m->hasUser($u),
            'config' => fn ($m) => [
                'personal_team' => $m->personal_team ?? false,
            ],
        ]);
    }

    /**
     * Get the Team model class.
     */
    protected function getTeamModel(): string
    {
        // Allow custom team model in config
        if (! empty($this->config['model'])) {
            return $this->config['model'];
        }

        // Use Jetstream's configured model
        if (class_exists(Jetstream::class)) {
            return Jetstream::teamModel();
        }

        return 'App\\Models\\Team';
    }
}
