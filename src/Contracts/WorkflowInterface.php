<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Contracts;

use AgenticOrchestrator\Workflows\WorkflowDefinition;

/**
 * Interface for workflow orchestration.
 *
 * Workflows coordinate multiple agents and steps to accomplish
 * complex, multi-step tasks with support for parallel execution,
 * conditional branching, and human-in-the-loop patterns.
 */
interface WorkflowInterface
{
    /**
     * Define the workflow structure.
     *
     * Returns a WorkflowDefinition that describes the steps,
     * their dependencies, and execution patterns.
     */
    public function definition(): WorkflowDefinition;

    /**
     * Scope the workflow to a team.
     *
     * Ensures all agents and resources within the workflow
     * are properly scoped to the team.
     *
     * @param  int|string|object  $team  The team model instance or ID
     * @return static For fluent chaining
     */
    public function forTeam(int|string|object $team): static;
}
