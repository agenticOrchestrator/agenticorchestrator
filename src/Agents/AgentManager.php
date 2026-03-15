<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Agents;

use AgenticOrchestrator\Contracts\AgentInterface;
use AgenticOrchestrator\Contracts\TeamScopedInterface;
use AgenticOrchestrator\Exceptions\AgentAccessDeniedException;
use AgenticOrchestrator\Exceptions\AgentNotFoundException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Agent Manager - Registry and factory for agents.
 *
 * Handles agent registration, resolution, and team-scoped access control.
 */
class AgentManager
{
    /**
     * Registered agent classes indexed by name.
     *
     * @var array<string, class-string<AgentInterface>>
     */
    protected array $agents = [];

    /**
     * System agent classes (available to all teams).
     *
     * @var array<string, class-string<AgentInterface>>
     */
    protected array $systemAgents = [];

    /**
     * Custom agent classes indexed by team ID.
     *
     * @var array<int|string, array<string, class-string<AgentInterface>>>
     */
    protected array $teamAgents = [];

    /**
     * Resolved agent instances cache.
     *
     * @var array<string, AgentInterface>
     */
    protected array $resolved = [];

    /**
     * Create a new agent manager instance.
     */
    public function __construct(
        protected Container $container,
    ) {}

    /**
     * Register an agent class.
     *
     * @param  class-string<AgentInterface>  $agentClass
     */
    public function register(string $agentClass, ?string $name = null): static
    {
        $this->validateAgentClass($agentClass);

        $name = $name ?? $this->resolveAgentName($agentClass);
        $this->agents[$name] = $agentClass;

        return $this;
    }

    /**
     * Register a system agent (available to all teams).
     *
     * @param  class-string<AgentInterface>  $agentClass
     */
    public function registerSystemAgent(string $agentClass, ?string $name = null): static
    {
        $this->validateAgentClass($agentClass);

        $name = $name ?? $this->resolveAgentName($agentClass);
        $this->systemAgents[$name] = $agentClass;
        $this->agents[$name] = $agentClass;

        return $this;
    }

    /**
     * Register a custom agent for a specific team.
     *
     * @param  class-string<AgentInterface>  $agentClass
     */
    public function registerForTeam(int|string $teamId, string $agentClass, ?string $name = null): static
    {
        $this->validateAgentClass($agentClass);

        $name = $name ?? $this->resolveAgentName($agentClass);

        if (! isset($this->teamAgents[$teamId])) {
            $this->teamAgents[$teamId] = [];
        }

        $this->teamAgents[$teamId][$name] = $agentClass;

        return $this;
    }

    /**
     * Resolve an agent instance by name.
     *
     * @throws AgentNotFoundException
     */
    public function make(string $name, array $parameters = []): AgentInterface
    {
        if (! $this->has($name)) {
            throw new AgentNotFoundException("Agent [{$name}] not found.");
        }

        $agentClass = $this->agents[$name];

        return $this->container->make($agentClass, $parameters);
    }

    /**
     * Resolve an agent with team scope.
     *
     * @param  int|string|object  $team  Team ID or team model
     *
     * @throws AgentNotFoundException
     * @throws AgentAccessDeniedException
     */
    public function makeForTeam(string $name, int|string|object $team, array $parameters = []): AgentInterface
    {
        $teamId = $this->resolveTeamId($team);

        // Check if agent is accessible by this team
        if (! $this->isAccessibleByTeam($name, $teamId)) {
            throw new AgentAccessDeniedException(
                "Agent [{$name}] is not accessible by team [{$teamId}]."
            );
        }

        // Resolve the agent class
        $agentClass = $this->resolveAgentClassForTeam($name, $teamId);

        $agent = $this->container->make($agentClass, $parameters);

        // Apply team scope if the agent supports it
        if ($agent instanceof TeamScopedInterface) {
            $agent = $agent->forTeam($team);
        }

        return $agent;
    }

    /**
     * Check if an agent is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->agents[$name]);
    }

    /**
     * Check if an agent is a system agent.
     */
    public function isSystemAgent(string $name): bool
    {
        return isset($this->systemAgents[$name]);
    }

    /**
     * Check if an agent is accessible by a specific team.
     */
    public function isAccessibleByTeam(string $name, int|string $teamId): bool
    {
        // System agents are accessible by all teams
        if ($this->isSystemAgent($name)) {
            return true;
        }

        // Check if team has this agent registered
        return isset($this->teamAgents[$teamId][$name]);
    }

    /**
     * Get all registered agents.
     *
     * @return Collection<string, class-string<AgentInterface>>
     */
    public function all(): Collection
    {
        return collect($this->agents);
    }

    /**
     * Get all system agents.
     *
     * @return Collection<string, class-string<AgentInterface>>
     */
    public function systemAgents(): Collection
    {
        return collect($this->systemAgents);
    }

    /**
     * Get all agents accessible by a specific team.
     *
     * @return Collection<string, class-string<AgentInterface>>
     */
    public function forTeam(int|string|object $team): Collection
    {
        $teamId = $this->resolveTeamId($team);

        $teamAgents = $this->teamAgents[$teamId] ?? [];

        return collect(array_merge($this->systemAgents, $teamAgents));
    }

    /**
     * Get agents registered for a specific team (not including system agents).
     *
     * @return Collection<string, class-string<AgentInterface>>
     */
    public function customAgentsForTeam(int|string|object $team): Collection
    {
        $teamId = $this->resolveTeamId($team);

        return collect($this->teamAgents[$teamId] ?? []);
    }

    /**
     * Unregister an agent.
     */
    public function forget(string $name): static
    {
        unset($this->agents[$name]);
        unset($this->systemAgents[$name]);
        unset($this->resolved[$name]);

        // Remove from all team registrations
        foreach ($this->teamAgents as $teamId => $agents) {
            unset($this->teamAgents[$teamId][$name]);
        }

        return $this;
    }

    /**
     * Unregister a team's custom agent.
     */
    public function forgetForTeam(int|string $teamId, string $name): static
    {
        unset($this->teamAgents[$teamId][$name]);

        return $this;
    }

    /**
     * Clear all team agents for a specific team.
     */
    public function clearTeam(int|string $teamId): static
    {
        unset($this->teamAgents[$teamId]);

        return $this;
    }

    /**
     * Clear all registrations.
     */
    public function flush(): static
    {
        $this->agents = [];
        $this->systemAgents = [];
        $this->teamAgents = [];
        $this->resolved = [];

        return $this;
    }

    /**
     * Extend the manager with a custom driver/resolver.
     */
    public function extend(string $name, callable $callback): static
    {
        $this->agents[$name] = $callback;

        return $this;
    }

    /**
     * Get agent metadata for listing purposes.
     *
     * @return array<string, array{name: string, class: string, system: bool, description: string|null}>
     */
    public function getAgentMetadata(): array
    {
        $metadata = [];

        foreach ($this->agents as $name => $class) {
            $metadata[$name] = [
                'name' => $name,
                'class' => $class,
                'system' => $this->isSystemAgent($name),
                'description' => $this->getAgentDescription($class),
            ];
        }

        return $metadata;
    }

    /**
     * Validate that a class implements AgentInterface.
     *
     * @param  class-string  $agentClass
     *
     * @throws InvalidArgumentException
     */
    protected function validateAgentClass(string $agentClass): void
    {
        if (! class_exists($agentClass)) {
            throw new InvalidArgumentException(
                "Agent class [{$agentClass}] does not exist."
            );
        }

        if (! is_subclass_of($agentClass, AgentInterface::class)) {
            throw new InvalidArgumentException(
                "Agent class [{$agentClass}] must implement AgentInterface."
            );
        }
    }

    /**
     * Resolve agent name from class.
     *
     * @param  class-string<AgentInterface>  $agentClass
     */
    protected function resolveAgentName(string $agentClass): string
    {
        // Try to get the name from the class's protected $name property using reflection
        try {
            $reflection = new \ReflectionClass($agentClass);
            $defaultProperties = $reflection->getDefaultProperties();

            if (isset($defaultProperties['name']) && ! empty($defaultProperties['name'])) {
                return $defaultProperties['name'];
            }
        } catch (\ReflectionException) {
            // Fall through to class name extraction
        }

        // Extract name from class name (e.g., CustomerServiceAgent -> customer-service)
        $baseName = class_basename($agentClass);

        // Remove 'Agent' suffix if present
        $name = preg_replace('/Agent$/', '', $baseName);

        // Convert to kebab-case
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $name));
    }

    /**
     * Resolve agent class for a specific team.
     *
     * @return class-string<AgentInterface>
     */
    protected function resolveAgentClassForTeam(string $name, int|string $teamId): string
    {
        // Team-specific agent takes precedence
        if (isset($this->teamAgents[$teamId][$name])) {
            return $this->teamAgents[$teamId][$name];
        }

        // Fall back to system agent
        if (isset($this->systemAgents[$name])) {
            return $this->systemAgents[$name];
        }

        // Fall back to general registration
        return $this->agents[$name];
    }

    /**
     * Resolve team ID from various input types.
     */
    protected function resolveTeamId(int|string|object $team): int|string
    {
        if (is_object($team)) {
            // Try common model ID accessors
            if (method_exists($team, 'getKey')) {
                return $team->getKey();
            }
            if (property_exists($team, 'id')) {
                return $team->id;
            }
        }

        return $team;
    }

    /**
     * Get agent description from class.
     *
     * @param  class-string<AgentInterface>  $agentClass
     */
    protected function getAgentDescription(string $agentClass): ?string
    {
        // Try to get the description from the class's protected $description property
        try {
            $reflection = new \ReflectionClass($agentClass);
            $defaultProperties = $reflection->getDefaultProperties();

            if (isset($defaultProperties['description']) && ! empty($defaultProperties['description'])) {
                return $defaultProperties['description'];
            }
        } catch (\ReflectionException) {
            // Fall through to docblock extraction
        }

        // Try to get description from class docblock
        try {
            $reflection = new \ReflectionClass($agentClass);
            $docComment = $reflection->getDocComment();

            if ($docComment) {
                // Extract first line of doc comment
                preg_match('/@description\s+(.+)$/m', $docComment, $matches);
                if (isset($matches[1])) {
                    return trim($matches[1]);
                }

                // Fall back to first non-tag line
                $lines = explode("\n", $docComment);
                foreach ($lines as $line) {
                    $line = trim($line, " \t\n\r\0\x0B/*");
                    if ($line && ! str_starts_with($line, '@')) {
                        return $line;
                    }
                }
            }
        } catch (\ReflectionException) {
            // Ignore reflection errors
        }

        return null;
    }
}
