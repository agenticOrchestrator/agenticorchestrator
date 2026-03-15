# Team Scoping

Team scoping ensures agents, memory, and data are isolated per team in multi-tenant applications.

## Overview

Team scoping provides:

- Data isolation between teams
- Team-specific agent configurations
- Scoped memory namespaces
- Isolated vector store collections

## Configuration

### Enable Multi-Tenancy

```php
// config/agent-orchestrator.php
'multi_tenancy' => [
    'enabled' => true,
    'driver' => 'auto', // or 'jetstream', 'stancl', 'spatie', 'filament', 'generic', 'null'
],
```

Supported drivers:
- `auto` - Auto-detect installed tenancy package
- `jetstream` - Laravel Jetstream Teams
- `stancl` - stancl/tenancy
- `spatie` - spatie/laravel-multitenancy
- `filament` - Filament Panels
- `generic` - Custom Eloquent model
- `null` - Disabled (single-tenant)

### Team Model Requirements

```php
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    // Required: unique identifier for scoping
    public function getTeamIdentifier(): string|int
    {
        return $this->id;
    }

    // Optional: team name for logging
    public function getTeamName(): string
    {
        return $this->name;
    }
}
```

## Scoping Agents

### Automatic Scoping

```php
use App\Agents\CustomerAgent;

// In a controller with team context
$agent = CustomerAgent::make()
    ->forTeam($request->user()->currentTeam);

$response = $agent->respond('Help me with my order');
```

### Middleware Approach

```php
// app/Http/Middleware/ScopeToTeam.php
class ScopeToTeam
{
    public function handle($request, $next)
    {
        if ($team = $request->user()?->currentTeam) {
            app()->instance('current_team', $team);
        }

        return $next($request);
    }
}
```

```php
// Agent automatically uses scoped team
class MyAgent extends Agent
{
    public function __construct()
    {
        parent::__construct();

        if ($team = app('current_team')) {
            $this->forTeam($team);
        }
    }
}
```

## Memory Isolation

### Scoped Memory

```php
use AgenticOrchestrator\Memory\CacheMemory;

$memory = CacheMemory::make()
    ->forTeam($team);

// Keys are automatically namespaced
$memory->store('preference', 'dark_mode');
// Actually stores: "team:{team_id}:preference"
```

### Conversation Isolation

```php
$agent = MyAgent::make()
    ->forTeam($team)
    ->withMemory($memory);

// Conversations are isolated per team
$response = $agent->respond('What did we discuss yesterday?');
// Only retrieves team's conversation history
```

## Vector Store Isolation

### Collection Per Team

```php
use AgenticOrchestrator\Facades\VectorStore;

$store = VectorStore::store();

// Store documents in team-specific collection
$collection = "documents-{$team->id}";
$store->store($document, $collection);

// Search within team's collection
$results = $store->search($query, $collection);
```

### Shared Knowledge Base

```php
// Some data can be shared across teams
$sharedCollection = 'global-knowledge';
$teamCollection = "team-{$team->id}-knowledge";

// Search both
$sharedResults = $store->search($query, $sharedCollection);
$teamResults = $store->search($query, $teamCollection);

$results = collect($sharedResults)
    ->merge($teamResults)
    ->sortByDesc('score')
    ->take(5);
```

## Scoped Tools

### Team-Aware Tools

```php
use AgenticOrchestrator\Tools\Attributes\Tool;
use AgenticOrchestrator\Tools\Attributes\ToolParameter;
use AgenticOrchestrator\Contracts\TeamScopedInterface;

class OrderLookupTool implements TeamScopedInterface
{
    protected ?object $team = null;

    public function forTeam(int|string|object $team): static
    {
        $this->team = $team;
        return $this;
    }

    #[Tool('Look up order by ID')]
    public function lookupOrder(
        #[ToolParameter('The order ID')]
        string $orderId
    ): array {
        // Only find orders for current team
        return Order::where('team_id', $this->team->id)
            ->where('id', $orderId)
            ->first()
            ?->toArray() ?? ['error' => 'Order not found'];
    }
}
```

### Scoping Built-in Tools

```php
class TeamScopedAgent extends Agent
{
    protected function getTools(): array
    {
        return collect(parent::getTools())
            ->map(function ($tool) {
                if ($tool instanceof TeamScopedInterface) {
                    return $tool->forTeam($this->team);
                }
                return $tool;
            })
            ->all();
    }
}
```

## Scoped Workflows

### Team Context in Workflows

```php
use AgenticOrchestrator\Workflows\Workflow;

class OrderWorkflow extends Workflow
{
    public function run(array $input): WorkflowResult
    {
        $context = new WorkflowContext($input);
        $context->setTeam($this->team);

        return $this->execute($context);
    }
}
```

### Using Team in Steps

```php
$step = new ToolStep(
    name: 'get-team-settings',
    tool: 'get_settings',
    arguments: fn ($ctx) => [
        'team_id' => $ctx->getTeam()->id,
    ],
);
```

## Authorization

### Verify Team Access

```php
class TeamScopedAgent extends Agent
{
    public function respond(string $message): AgentResponse
    {
        // Verify user has access to team
        if (!$this->userCanAccessTeam()) {
            throw new UnauthorizedException('No access to team');
        }

        return parent::respond($message);
    }

    protected function userCanAccessTeam(): bool
    {
        $user = auth()->user();

        return $user && $user->belongsToTeam($this->team);
    }
}
```

### Role-Based Scoping

```php
class AdminAgent extends Agent
{
    public function respond(string $message): AgentResponse
    {
        // Only team admins can use this agent
        if (!$this->isTeamAdmin()) {
            throw new UnauthorizedException('Admin access required');
        }

        return parent::respond($message);
    }
}
```

## Testing

### Testing Scoped Agents

```php
use AgenticOrchestrator\Testing\FakeAgent;

it('isolates data between teams', function () {
    $team1 = Team::factory()->create();
    $team2 = Team::factory()->create();

    $agent1 = FakeAgent::make()->forTeam($team1);
    $agent2 = FakeAgent::make()->forTeam($team2);

    // Each agent has isolated context
    expect($agent1->getTeam()->id)->not->toBe($agent2->getTeam()->id);
});
```

### Testing Memory Isolation

```php
it('isolates memory per team', function () {
    $memory1 = FakeMemory::make()->forTeam($team1);
    $memory2 = FakeMemory::make()->forTeam($team2);

    $memory1->store('key', 'value1');
    $memory2->store('key', 'value2');

    expect($memory1->recall('key'))->toBe('value1');
    expect($memory2->recall('key'))->toBe('value2');
});
```

## Best Practices

1. **Always scope in production** - Never allow cross-team data access
2. **Use middleware** - Apply scoping consistently via middleware
3. **Verify at boundaries** - Check team access at entry points
4. **Namespace everything** - Keys, collections, caches should include team ID
5. **Test isolation** - Write tests that verify team data isolation
6. **Audit access** - Log team context for security auditing
