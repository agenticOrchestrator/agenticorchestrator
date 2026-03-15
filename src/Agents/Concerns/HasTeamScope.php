<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Agents\Concerns;

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\MultiTenancy\Tenant;
use AgenticOrchestrator\MultiTenancy\TenantManager;

/**
 * Provides team and user scoping for agents.
 *
 * This trait implements the TeamScopedInterface and provides
 * the first-class multi-tenancy support that differentiates
 * agent-orchestrator from other packages.
 *
 * Supports multiple tenancy drivers:
 * - Laravel Jetstream Teams
 * - Stancl Tenancy for Laravel
 * - Spatie Laravel Multitenancy
 * - Filament Multi-tenancy
 * - Custom implementations
 */
trait HasTeamScope
{
    /**
     * The current team/tenant scope.
     */
    protected ?object $team = null;

    /**
     * The current user scope.
     */
    protected ?object $user = null;

    /**
     * The tenant interface wrapper.
     */
    protected ?TenantInterface $tenant = null;

    /**
     * Scope the agent to a specific team/tenant.
     *
     * Accepts any team-like object and wraps it in TenantInterface.
     *
     * @param  object|TenantInterface  $team  The team/tenant model instance
     * @return static For fluent chaining
     */
    public function forTeam(object $team): static
    {
        $this->team = $team;

        // Wrap in TenantInterface if not already
        if ($team instanceof TenantInterface) {
            $this->tenant = $team;
        } else {
            $this->tenant = Tenant::fromModel($team);
        }

        return $this;
    }

    /**
     * Scope the agent to the current tenant from TenantManager.
     *
     * @return static For fluent chaining
     */
    public function forCurrentTenant(): static
    {
        $tenantManager = app(TenantManager::class);
        $currentTenant = $tenantManager->current();

        if ($currentTenant) {
            $this->tenant = $currentTenant;
            $this->team = $currentTenant->getModel();
        }

        return $this;
    }

    /**
     * Get the current team.
     */
    public function getTeam(): ?object
    {
        return $this->team;
    }

    /**
     * Get the current tenant interface.
     */
    public function getTenant(): ?TenantInterface
    {
        return $this->tenant;
    }

    /**
     * Scope the agent to a specific user within the team.
     *
     * @param  object  $user  The user model instance
     * @return static For fluent chaining
     */
    public function forUser(object $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get the current user.
     */
    public function getUser(): ?object
    {
        return $this->user;
    }

    /**
     * Check if this is a system agent.
     *
     * System agents are platform-wide and accessible by all teams.
     */
    public function isSystemAgent(): bool
    {
        return $this->isSystem ?? false;
    }

    /**
     * Check if this agent is accessible by the given team.
     *
     * @param  object  $team  The team to check access for
     */
    public function isAccessibleBy(object $team): bool
    {
        // System agents are accessible by all teams
        if ($this->isSystemAgent()) {
            return true;
        }

        // Custom agents only accessible by their owning team
        if ($this->team !== null) {
            // Compare by ID if available
            $teamId = $team->id ?? spl_object_id($team);
            $ownerId = $this->team->id ?? spl_object_id($this->team);

            return $teamId === $ownerId;
        }

        // No team set, accessible by any team
        return true;
    }

    /**
     * Get the team/tenant ID for scoping operations.
     */
    protected function getTeamId(): int|string|null
    {
        if ($this->tenant) {
            return $this->tenant->getTenantKey();
        }

        return $this->team?->id ?? null;
    }

    /**
     * Get the user ID for scoping operations.
     */
    protected function getUserId(): int|string|null
    {
        if ($this->user === null) {
            return null;
        }

        if (method_exists($this->user, 'getKey')) {
            return $this->user->getKey();
        }

        return $this->user->id ?? null;
    }

    /**
     * Build a unique namespace for team-scoped resources.
     */
    protected function buildTeamNamespace(string $prefix = ''): string
    {
        $parts = [];

        $teamId = $this->getTeamId();
        if ($teamId !== null) {
            $parts[] = 'team_'.$teamId;
        }

        $parts[] = 'agent_'.$this->getId();

        if ($prefix !== '') {
            array_unshift($parts, $prefix);
        }

        return implode('_', $parts);
    }

    /**
     * Get tenant-aware context for memory and tracking.
     *
     * @return array<string, mixed>
     */
    protected function getTenantContext(): array
    {
        $context = [];

        if ($this->tenant) {
            $context['tenant_id'] = $this->tenant->getTenantKey();
            $context['tenant_name'] = $this->tenant->getTenantName();
            $context['tenant_type'] = get_class($this->tenant->getModel());
        } elseif ($this->team) {
            $context['team_id'] = $this->getTeamId();
            $context['team_type'] = get_class($this->team);
        }

        if ($this->user) {
            $context['user_id'] = $this->getUserId();
        }

        return $context;
    }
}
