# Creating Agents

This guide walks you through creating custom agents for your Laravel application. You will learn how to define agent behavior, add tools, configure memory, and integrate with multi-tenancy.

## Basic Agent Structure

Every agent extends the base `Agent` class and implements the `instructions()` method:

```php
<?php

namespace App\Agents;

use AgenticOrchestrator\Agents\Agent;

class MyAgent extends Agent
{
    protected string $name = 'My Agent';
    protected string $description = 'A helpful assistant';
    protected string $model = 'gpt-4o';
    protected string $provider = 'openai';

    public function instructions(): string
    {
        return 'You are a helpful assistant.';
    }
}
```

## Using the Artisan Command

Generate a new agent using the Artisan command:

```bash
php artisan make:agent CustomerSupport
```

This creates a new agent class in `app/Agents/CustomerSupportAgent.php`:

```php
<?php

namespace App\Agents;

use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Tools\Attributes\Tool;

class CustomerSupportAgent extends Agent
{
    protected string $name = 'Customer Support';

    protected string $description = 'Customer support assistant';

    protected string $model = 'gpt-4o';

    protected string $provider = 'openai';

    public function instructions(): string
    {
        return <<<'PROMPT'
            You are a helpful customer support agent.
            PROMPT;
    }
}
```

### Command Options

```bash
# Generate with a specific model
php artisan make:agent CustomerSupport --model=claude-3-5-sonnet-20241022 --provider=anthropic

# Generate as a system agent
php artisan make:agent ComplianceChecker --system
```

## Configuring Properties

### Basic Properties

Configure your agent through class properties:

```php
class CustomerSupportAgent extends Agent
{
    /**
     * The agent's display name.
     */
    protected string $name = 'Customer Support';

    /**
     * Description of what the agent does.
     */
    protected string $description = 'Handles customer inquiries, order lookups, and support tickets';

    /**
     * The LLM model to use.
     */
    protected string $model = 'gpt-4o';

    /**
     * The LLM provider.
     */
    protected string $provider = 'openai';

    /**
     * Temperature controls response creativity (0-2).
     * Lower values produce more focused, deterministic responses.
     * Higher values produce more creative, varied responses.
     */
    protected float $temperature = 0.7;

    /**
     * Maximum tokens for the response.
     * Set to null to use model defaults.
     */
    protected ?int $maxTokens = 1000;
}
```

### Memory Configuration

Configure how the agent stores and retrieves conversation history:

```php
protected array $memory = [
    'driver' => 'cache',        // cache, database, vector, session, rag
    'namespace' => null,        // Custom namespace (optional)
    'ttl' => 3600,              // TTL in seconds (for cache driver)
    'vector_store' => null,     // Vector store name (for vector driver)
];
```

Available memory drivers:

| Driver | Description | Use Case |
|--------|-------------|----------|
| `session` | In-memory, request only | Testing, stateless interactions |
| `cache` | Redis/file cache with TTL | Short-term conversations |
| `database` | Persistent database storage | Long-term history |
| `vector` | Semantic vector storage | Context-aware retrieval |
| `rag` | External knowledge retrieval | Knowledge base integration |

### Capabilities Configuration

Define what your agent can do:

```php
protected array $capabilities = [
    // Can this agent delegate tasks to other agents?
    // Default: false - must be explicitly enabled
    'can_delegate' => false,

    // Can this agent receive delegated tasks from others?
    'can_be_delegate' => true,

    // Can this agent perform RAG retrieval?
    'can_use_rag' => false,

    // Does this agent support streaming responses?
    'can_stream' => true,

    // Maximum tool call iterations before stopping
    'max_iterations' => 10,
];
```

## Writing Instructions

The `instructions()` method returns the system prompt that defines your agent's behavior:

```php
public function instructions(): string
{
    return <<<'PROMPT'
        You are a knowledgeable customer support agent for Acme Corp.

        ## Your Role
        - Help customers with product inquiries
        - Look up order status and shipping information
        - Process returns and refunds
        - Escalate complex issues to human agents

        ## Guidelines
        - Always be friendly and professional
        - Verify customer identity before sharing sensitive information
        - If you cannot resolve an issue, offer to escalate
        - Never make promises about timelines you cannot guarantee

        ## Available Information
        - You have access to the order database
        - You can check inventory levels
        - You can view customer support ticket history
        PROMPT;
}
```

### Dynamic Instructions

You can generate instructions dynamically based on context:

```php
public function instructions(): string
{
    $companyName = $this->team?->name ?? 'our company';
    $supportEmail = config('support.email');

    return <<<PROMPT
        You are the customer support agent for {$companyName}.

        For issues you cannot resolve, direct customers to {$supportEmail}.

        Current date: {$this->getCurrentDate()}
        PROMPT;
}

private function getCurrentDate(): string
{
    return now()->format('F j, Y');
}
```

## Adding Tools

Tools allow your agent to perform actions. Define them using the `#[Tool]` attribute:

```php
use AgenticOrchestrator\Tools\Attributes\Tool;
use AgenticOrchestrator\Tools\Attributes\ToolParameter;

class CustomerSupportAgent extends Agent
{
    // ... properties and instructions ...

    #[Tool('Look up an order by its ID')]
    public function lookupOrder(string $orderId): array
    {
        $order = Order::where('team_id', $this->team->id)
            ->findOrFail($orderId);

        return [
            'id' => $order->id,
            'status' => $order->status,
            'total' => $order->total,
            'created_at' => $order->created_at->toDateTimeString(),
            'items' => $order->items->map(fn($item) => [
                'product' => $item->product->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
            ])->toArray(),
        ];
    }

    #[Tool('Search for products in the catalog')]
    public function searchProducts(
        #[ToolParameter('Search query')]
        string $query,
        #[ToolParameter('Maximum results to return', enum: ['5', '10', '20'])]
        string $limit = '10',
    ): array {
        return Product::search($query)
            ->where('team_id', $this->team->id)
            ->take((int) $limit)
            ->get()
            ->toArray();
    }

    #[Tool('Check inventory levels for a product')]
    public function checkInventory(string $productId): array
    {
        $product = Product::findOrFail($productId);

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'in_stock' => $product->inventory_count > 0,
            'quantity' => $product->inventory_count,
            'restock_date' => $product->next_restock_date?->toDateString(),
        ];
    }

    #[Tool('Create a support ticket')]
    public function createTicket(
        string $subject,
        string $description,
        #[ToolParameter('Priority level', enum: ['low', 'medium', 'high', 'urgent'])]
        string $priority = 'medium',
    ): array {
        $ticket = SupportTicket::create([
            'team_id' => $this->team->id,
            'user_id' => $this->user?->id,
            'subject' => $subject,
            'description' => $description,
            'priority' => $priority,
            'status' => 'open',
        ]);

        return [
            'ticket_id' => $ticket->id,
            'message' => "Support ticket #{$ticket->id} created successfully",
        ];
    }
}
```

### Tool Attribute Options

The `#[Tool]` attribute supports several options:

```php
#[Tool(
    description: 'Description shown to the LLM',
    name: 'custom_name',    // Override method name
    parallel: true,         // Can run in parallel with other tools
    cacheable: true,        // Results can be cached
    cacheTtl: 300,          // Cache TTL in seconds
    hidden: false,          // Hide from certain contexts
)]
public function myTool(): array
{
    // ...
}
```

### External Tool Classes

For reusable tools, define them as separate classes implementing `ToolInterface`:

```php
use AgenticOrchestrator\Contracts\ToolInterface;

class WeatherTool implements ToolInterface
{
    public function getName(): string
    {
        return 'get_weather';
    }

    public function getDescription(): string
    {
        return 'Get current weather for a location';
    }

    public function getParameters(): array
    {
        return [
            'location' => [
                'type' => 'string',
                'description' => 'City and state or country',
                'required' => true,
            ],
        ];
    }

    public function execute(array $arguments): mixed
    {
        $location = $arguments['location'];
        // Fetch weather data...
        return ['temperature' => 72, 'condition' => 'sunny'];
    }

    public function toSchema(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => $this->getDescription(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'location' => [
                            'type' => 'string',
                            'description' => 'City and state or country',
                        ],
                    ],
                    'required' => ['location'],
                ],
            ],
        ];
    }

    public function isParallel(): bool
    {
        return true; // Can run in parallel with other tools
    }

    public function isCacheable(): bool
    {
        return true; // Weather results can be cached
    }

    public function getCacheTtl(): int
    {
        return 300; // Cache for 5 minutes
    }

    public function validate(array $arguments): bool
    {
        return isset($arguments['location']) && is_string($arguments['location']);
    }
}
```

Register external tools in your agent:

```php
class MyAgent extends Agent
{
    protected array $tools = [
        WeatherTool::class,
        CalculatorTool::class,
    ];
}
```

## Multi-Tenancy Integration

Agents automatically support multi-tenancy through the `HasTeamScope` trait:

```php
class CustomerSupportAgent extends Agent
{
    #[Tool('Get team-specific configuration')]
    public function getTeamConfig(): array
    {
        // Access the current team
        $team = $this->getTeam();

        return [
            'team_name' => $team->name,
            'plan' => $team->subscription_plan,
            'features' => $team->enabled_features,
        ];
    }

    #[Tool('Look up customer orders')]
    public function getCustomerOrders(string $customerId): array
    {
        // Queries are automatically scoped to the team
        return Order::where('team_id', $this->getTeam()->id)
            ->where('customer_id', $customerId)
            ->get()
            ->toArray();
    }
}
```

### Using the Agent with Team Context

```php
// Scope agent to a team
$agent = CustomerSupportAgent::make()
    ->forTeam($team)
    ->forUser($user);

$response = $agent->respond('Show me recent orders');
```

## System Agents

Mark an agent as a system agent to make it available to all teams:

```php
class ComplianceCheckerAgent extends Agent
{
    protected string $name = 'Compliance Checker';
    protected bool $isSystem = true;

    public function instructions(): string
    {
        return 'You verify content compliance with regulations.';
    }
}
```

Register as a system agent:

```php
// In a service provider
use AgenticOrchestrator\Agents\AgentManager;

public function boot(AgentManager $manager): void
{
    $manager->registerSystemAgent(ComplianceCheckerAgent::class);
}
```

## Complete Example

Here is a complete example of a well-structured agent:

```php
<?php

namespace App\Agents;

use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Tools\Attributes\Tool;
use AgenticOrchestrator\Tools\Attributes\ToolParameter;
use App\Models\Order;
use App\Models\Product;
use App\Models\SupportTicket;
use App\Services\ShippingService;
use Illuminate\Support\Facades\Log;

class CustomerSupportAgent extends Agent
{
    protected string $name = 'Customer Support';

    protected string $description = 'AI-powered customer support assistant';

    protected string $model = 'gpt-4o';

    protected string $provider = 'openai';

    protected float $temperature = 0.7;

    protected ?int $maxTokens = 1500;

    protected array $memory = [
        'driver' => 'cache',
        'ttl' => 7200, // 2 hours
    ];

    protected array $capabilities = [
        'can_delegate' => true,     // Override default (false) to enable delegation
        'can_be_delegate' => true,
        'can_stream' => true,       // Enable streaming responses
        'max_iterations' => 15,
    ];

    public function __construct(
        private readonly ShippingService $shippingService,
    ) {}

    public function instructions(): string
    {
        $teamName = $this->team?->name ?? 'our company';

        return <<<PROMPT
            You are the customer support assistant for {$teamName}.

            ## Your Capabilities
            - Look up order status and details
            - Track shipments and delivery estimates
            - Search the product catalog
            - Create support tickets for complex issues

            ## Guidelines
            - Be friendly, helpful, and professional
            - Verify order IDs before sharing details
            - If you cannot resolve an issue, create a support ticket
            - Never share sensitive payment information

            ## Response Format
            - Keep responses concise but informative
            - Use bullet points for lists
            - Include relevant order/product IDs when referencing them
            PROMPT;
    }

    #[Tool('Look up an order by its ID and return its details')]
    public function lookupOrder(string $orderId): array
    {
        $order = Order::where('team_id', $this->getTeam()->id)
            ->with(['items.product', 'customer'])
            ->find($orderId);

        if (!$order) {
            return ['error' => 'Order not found'];
        }

        return [
            'order_id' => $order->id,
            'status' => $order->status,
            'created_at' => $order->created_at->toDateTimeString(),
            'customer_name' => $order->customer->name,
            'total' => number_format($order->total, 2),
            'items' => $order->items->map(fn($item) => [
                'product' => $item->product->name,
                'quantity' => $item->quantity,
                'price' => number_format($item->price, 2),
            ])->toArray(),
        ];
    }

    #[Tool('Get tracking information for an order shipment')]
    public function trackShipment(string $orderId): array
    {
        $order = Order::where('team_id', $this->getTeam()->id)
            ->find($orderId);

        if (!$order || !$order->tracking_number) {
            return ['error' => 'No tracking information available'];
        }

        try {
            $tracking = $this->shippingService->track($order->tracking_number);

            return [
                'tracking_number' => $order->tracking_number,
                'carrier' => $tracking->carrier,
                'status' => $tracking->status,
                'estimated_delivery' => $tracking->estimated_delivery,
                'events' => $tracking->events,
            ];
        } catch (\Exception $e) {
            Log::warning('Shipping tracking failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return ['error' => 'Unable to retrieve tracking information'];
        }
    }

    #[Tool('Search for products in the catalog')]
    public function searchProducts(
        #[ToolParameter('Search query for product name or description')]
        string $query,
        #[ToolParameter('Maximum number of results', enum: ['5', '10', '20'])]
        string $limit = '10',
    ): array {
        $products = Product::where('team_id', $this->getTeam()->id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->take((int) $limit)
            ->get();

        return $products->map(fn($product) => [
            'id' => $product->id,
            'name' => $product->name,
            'price' => number_format($product->price, 2),
            'in_stock' => $product->inventory_count > 0,
        ])->toArray();
    }

    #[Tool('Create a support ticket for issues requiring human attention')]
    public function createSupportTicket(
        #[ToolParameter('Brief subject line for the ticket')]
        string $subject,
        #[ToolParameter('Detailed description of the issue')]
        string $description,
        #[ToolParameter('Priority level', enum: ['low', 'medium', 'high', 'urgent'])]
        string $priority = 'medium',
    ): array {
        $ticket = SupportTicket::create([
            'team_id' => $this->getTeam()->id,
            'user_id' => $this->getUser()?->id,
            'subject' => $subject,
            'description' => $description,
            'priority' => $priority,
            'status' => 'open',
            'source' => 'ai_agent',
        ]);

        return [
            'success' => true,
            'ticket_id' => $ticket->id,
            'message' => "Support ticket #{$ticket->id} has been created. A human agent will review it shortly.",
        ];
    }
}
```

## Best Practices

### 1. Keep Instructions Focused

Write clear, specific instructions that define the agent's role:

```php
// Good: Specific and actionable
public function instructions(): string
{
    return <<<'PROMPT'
        You are a product recommendation assistant.

        Your job is to:
        1. Understand customer preferences
        2. Search the product catalog
        3. Recommend 3-5 relevant products

        Do NOT:
        - Make up product information
        - Discuss pricing negotiations
        - Handle order issues (direct to support)
        PROMPT;
}

// Avoid: Vague or overly broad
public function instructions(): string
{
    return 'Help customers with anything they need.';
}
```

### 2. Validate Tool Inputs

Always validate inputs in your tool methods:

```php
#[Tool('Update order status')]
public function updateOrderStatus(string $orderId, string $status): array
{
    // Validate status
    $validStatuses = ['pending', 'processing', 'shipped', 'delivered'];
    if (!in_array($status, $validStatuses)) {
        return ['error' => 'Invalid status. Must be one of: ' . implode(', ', $validStatuses)];
    }

    // Validate order exists and belongs to team
    $order = Order::where('team_id', $this->getTeam()->id)->find($orderId);
    if (!$order) {
        return ['error' => 'Order not found'];
    }

    $order->update(['status' => $status]);

    return ['success' => true, 'message' => "Order {$orderId} updated to {$status}"];
}
```

### 3. Handle Errors Gracefully

Return meaningful error messages instead of throwing exceptions:

```php
#[Tool('Get customer details')]
public function getCustomer(string $customerId): array
{
    try {
        $customer = Customer::findOrFail($customerId);
        return $customer->toArray();
    } catch (ModelNotFoundException $e) {
        return ['error' => 'Customer not found'];
    } catch (\Exception $e) {
        Log::error('Failed to get customer', ['error' => $e->getMessage()]);
        return ['error' => 'Unable to retrieve customer information'];
    }
}
```

### 4. Use Dependency Injection

Inject services through the constructor for testability:

```php
class OrderAgent extends Agent
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly InventoryService $inventory,
        private readonly NotificationService $notifications,
    ) {}

    #[Tool('Process a refund')]
    public function processRefund(string $orderId, float $amount): array
    {
        return $this->orderService->refund($orderId, $amount);
    }
}
```

## Next Steps

- Learn about the [AgentResponse](./agent-response.md) object
- Register agents with the [Agent Registry](./agent-registry.md)
- Explore [available traits](./traits.md) for extended capabilities
- Set up [event listeners](./events.md) for monitoring
