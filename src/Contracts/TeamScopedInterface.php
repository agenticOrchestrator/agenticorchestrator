<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Contracts;

/**
 * Interface for team-scoped resources.
 *
 * This is the key differentiator for agent-orchestrator.
 * All agents, memories, and conversations can be scoped
 * to a specific team for proper multi-tenancy isolation.
 */
interface TeamScopedInterface
{
    /**
     * Scope the resource to a specific team.
     *
     * This enables team isolation for all operations.
     * Memory, tools, and queries will be automatically
     * scoped to the team context.
     *
     * @param  object  $team  The team model instance
     * @return static For fluent chaining
     */
    public function forTeam(object $team): static;

    /**
     * Get the current team, if any.
     *
     * Returns null if no team scope has been set.
     */
    public function getTeam(): ?object;

    /**
     * Scope the resource to a specific user within the team.
     *
     * Provides user-level context within the team scope.
     * Useful for personalization and per-user tracking.
     *
     * @param  object  $user  The user model instance
     * @return static For fluent chaining
     */
    public function forUser(object $user): static;

    /**
     * Get the current user, if any.
     *
     * Returns null if no user scope has been set.
     */
    public function getUser(): ?object;

    /**
     * Check if this is a system resource.
     *
     * System resources (like system agents) are platform-wide
     * and accessible by all teams in read-only mode.
     */
    public function isSystemAgent(): bool;

    /**
     * Check if this resource is accessible by the given team.
     *
     * Returns true if:
     * - This is a system resource (accessible by all teams)
     * - This resource belongs to the given team
     *
     * @param  object  $team  The team to check access for
     */
    public function isAccessibleBy(object $team): bool;
}
