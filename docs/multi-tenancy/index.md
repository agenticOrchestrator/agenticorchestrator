# Multi-Tenancy

Agent Orchestrator provides first-class multi-tenancy support, enabling team-based isolation for agents, memories, and conversations. This is the key differentiator from other Laravel agent packages.

## Why Multi-Tenancy Matters

In SaaS applications, you need to ensure that:

- Each team's agents are isolated from other teams
- Custom agents created by one team are not visible to others
- Memory and conversation history remain private to each team
- Usage tracking and billing can be attributed to specific teams
- System-wide agents can be shared across all teams in read-only mode

Agent Orchestrator solves these challenges through a flexible multi-tenancy architecture that works with popular Laravel tenancy packages.

## Core Concepts

### Team Scoping

All resources in Agent Orchestrator can be scoped to a specific team:

```php
use App\Agents\CustomerSupportAgent;

// Scope an agent to the current team
$agent = CustomerSupportAgent::make()
    ->forTeam($team)
    ->forUser($user);

$response = $agent->respond('Help me with my order');
```

The `forTeam()` method ensures that:

- Memory operations are isolated to this team
- Tool executions have team context available
- Usage is tracked against this team
- The agent can only access team-scoped resources

### Tenant Interface

Agent Orchestrator uses a `TenantInterface` to abstract away the differences between various multi-tenancy implementations:

```php
interface TenantInterface
{
    public function getTenantKey(): int|string;
    public function getTenantName(): string;
    public function getTenantOwner(): ?object;
    public function hasMember(object $user): bool;
    public function getTenantConfig(): array;
    public function getModel(): object;
}
```

This interface allows Agent Orchestrator to work with any team/tenant model, whether it comes from Jetstream, Spatie, Stancl, Filament, or your own implementation.

### System vs Custom Agents

Agent Orchestrator distinguishes between two types of agents:

| Type | Visibility | Editable | Use Case |
|------|------------|----------|----------|
| System Agents | All teams | No (read-only) | Platform-wide functionality |
| Custom Agents | Single team | Yes | Team-specific workflows |

System agents are registered globally and available to all teams. Custom agents are registered per-team and only visible to their owning team.

## Supported Tenancy Drivers

Agent Orchestrator supports multiple multi-tenancy implementations:

| Driver | Package | Auto-Detection |
|--------|---------|----------------|
| `jetstream` | Laravel Jetstream Teams | Yes |
| `stancl` | stancl/tenancy | Yes |
| `spatie` | spatie/laravel-multitenancy | Yes |
| `filament` | Filament Panels | Yes |
| `generic` | Custom Eloquent model | No |
| `null` | Disabled (single-tenant) | - |

The default driver is `auto`, which automatically detects which tenancy package is installed.

## Quick Start

### Basic Configuration

Configure multi-tenancy in your `config/agent-orchestrator.php`:

```php
'multi_tenancy' => [
    'enabled' => true,
    'driver' => 'auto', // Auto-detect installed package
],
```

### Using Team Scope

```php
use App\Agents\AssistantAgent;
use Illuminate\Support\Facades\Auth;

// Get the current team (Jetstream example)
$team = Auth::user()->currentTeam;

// Create a team-scoped agent
$agent = AssistantAgent::make()
    ->forTeam($team)
    ->forUser(Auth::user());

$response = $agent->respond('What can you help me with?');
```

### Registering System Agents

```php
use AgenticOrchestrator\Facades\AgentManager;
use App\Agents\HelpAgent;
use App\Agents\AnalyticsAgent;

// In your AppServiceProvider or AgentServiceProvider
AgentManager::registerSystemAgent(HelpAgent::class);
AgentManager::registerSystemAgent(AnalyticsAgent::class);
```

### Registering Team-Specific Agents

```php
use AgenticOrchestrator\Facades\AgentManager;
use App\Agents\TeamSpecificAgent;

// Register an agent for a specific team
AgentManager::registerForTeam($teamId, TeamSpecificAgent::class);
```

## Architecture Overview

```
+-------------------+
|   TenantManager   |  <- Central multi-tenancy hub
+-------------------+
         |
         v
+-------------------+
| TenantResolver    |  <- Package-specific implementation
| (Jetstream/Spatie)|
+-------------------+
         |
         v
+-------------------+
|  TenantInterface  |  <- Unified tenant representation
+-------------------+
         |
    +----+----+
    |         |
    v         v
+--------+ +--------+
| Agents | | Memory |  <- Team-scoped resources
+--------+ +--------+
```

## Key Benefits

### Data Isolation

Each team's data is automatically isolated:

- Agents can only access their team's memory
- Conversation history is team-scoped
- Tool results are stored per-team

### Cost Attribution

Usage tracking is automatically attributed to teams:

```php
// Usage is tracked per-team
$usage = UsageLog::forTeam($team)
    ->whereBetween('created_at', [$startDate, $endDate])
    ->sum('total_tokens');
```

### Flexible Integration

Works with your existing authentication and authorization:

```php
// Use Laravel policies for access control
Gate::define('use-agent', function (User $user, Agent $agent) {
    return $agent->isSystemAgent()
        || $agent->isAccessibleBy($user->currentTeam);
});
```

## Documentation

- [Team Scoping](team-scoping.md) - TeamScopedInterface and HasTeamScope trait
- [Team Resolvers](team-resolvers.md) - Driver configuration and custom resolvers
- [Agent Visibility](agent-visibility.md) - System agents vs custom agents
- [Memory Isolation](memory-isolation.md) - Per-team memory namespacing
- [Jetstream Integration](jetstream-integration.md) - Laravel Jetstream setup

## Best Practices

1. **Always scope agents to teams**: Even if you have a single-tenant app today, scoping prepares you for future multi-tenancy needs.

2. **Use system agents for shared functionality**: Help agents, analytics, and platform features should be system agents.

3. **Store team context in agents**: Pass team information to tools that need it:

   ```php
   #[Tool('Get team statistics')]
   public function getStats(): array
   {
       return $this->team->statistics()->toArray();
   }
   ```

4. **Test tenant isolation**: Write tests that verify data isolation between teams.

5. **Consider subscription tiers**: Use agent limits per subscription tier:

   ```php
   'agent_limits' => [
       'free' => 3,
       'pro' => 10,
       'enterprise' => PHP_INT_MAX,
   ],
   ```
