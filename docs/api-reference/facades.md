# Facades

Convenient static interfaces to core Agent Orchestrator services.

## Available Facades

| Facade | Service | Purpose |
|--------|---------|---------|
| `Agent` | AgentRegistry | Agent registration and retrieval |
| `Tenant` | TenantManager | Multi-tenancy management |
| `Memory` | MemoryManager | Memory driver management |

## Agent Facade

Access the agent registry and create agents.

```php
use AgenticOrchestrator\Facades\Agent;

// Create an agent instance from a registered class
$agent = Agent::make(CustomerSupportAgent::class);

// Register an agent class
Agent::register('support', CustomerSupportAgent::class);

// Register a system agent (available to all teams)
Agent::registerSystemAgent(HelpAgent::class);

// Get all registered agents
$agents = Agent::getRegistered();

// Get all system agents
$systemAgents = Agent::getSystemAgents();
```

### Method Reference

| Method | Description |
|--------|-------------|
| `make(string $class)` | Create an agent instance |
| `register(string $name, string $class)` | Register an agent by name |
| `registerSystemAgent(string $class)` | Register a system agent |
| `getRegistered()` | Get all registered agents |
| `getSystemAgents()` | Get all system agents |

## Tenant Facade

Manage multi-tenancy operations.

```php
use AgenticOrchestrator\Facades\Tenant;

// Get the current tenant
$tenant = Tenant::current();

// Find a tenant by ID
$tenant = Tenant::find(123);

// Get tenant for a specific user
$tenant = Tenant::forUser($user);

// Set the current tenant
Tenant::setCurrent($tenant);

// Run code as a specific tenant
Tenant::runAs($tenant, function () {
    // Operations run in tenant context
});

// Run code as a tenant by ID
Tenant::runAsId(123, function () {
    // Operations run in tenant context
});

// Check if multi-tenancy is enabled
if (Tenant::isEnabled()) {
    // ...
}

// Check if tenancy is configured
if (Tenant::isConfigured()) {
    // ...
}

// Get the current driver name
$driver = Tenant::getDriver();

// Extend with a custom resolver
Tenant::extend('custom', function ($app) {
    return new CustomResolver($app);
});
```

### Method Reference

| Method | Description |
|--------|-------------|
| `current()` | Get the current tenant |
| `find(int\|string $id)` | Find a tenant by ID |
| `forUser(object $user)` | Get tenant for a user |
| `setCurrent(TenantInterface $tenant)` | Set current tenant |
| `runAs(TenantInterface $tenant, callable $callback)` | Run as tenant |
| `runAsId(int\|string $id, callable $callback)` | Run as tenant by ID |
| `isEnabled()` | Check if multi-tenancy is enabled |
| `isConfigured()` | Check if tenancy is configured |
| `getDriver()` | Get current driver name |
| `extend(string $name, callable $resolver)` | Register custom resolver |

## Memory Facade

Manage memory drivers.

```php
use AgenticOrchestrator\Facades\Memory;

// Get memory driver instance
$memory = Memory::driver('cache');

// Get the default driver
$driver = Memory::getDefaultDriver();

// Set the default driver
Memory::setDefaultDriver('redis');

// Get list of supported drivers
$drivers = Memory::getSupportedDrivers();
```

### Method Reference

| Method | Description |
|--------|-------------|
| `driver(string $name)` | Get a specific memory driver |
| `getDefaultDriver()` | Get default driver name |
| `setDefaultDriver(string $name)` | Set default driver |
| `getSupportedDrivers()` | Get list of supported drivers |

## Creating Custom Facades

### Define the Facade

```php
namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class CustomService extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'custom-service';
    }
}
```

### Register the Service

```php
// In a service provider
public function register()
{
    $this->app->singleton('custom-service', function ($app) {
        return new CustomServiceImplementation();
    });
}
```

### Use the Facade

```php
use App\Facades\CustomService;

CustomService::doSomething();
```
