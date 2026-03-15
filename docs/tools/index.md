# Tools

Tools are the primary mechanism for agents to interact with external systems. They enable agents to perform actions like database queries, API calls, file operations, and any other functionality your application needs.

## What Are Tools?

In the context of AI agents, tools are functions that the LLM can invoke to perform specific actions. When an agent encounters a task that requires external data or actions, it identifies the appropriate tool, provides the necessary arguments, and receives the results.

Agent Orchestrator provides two ways to define tools:

1. **Attribute-Based Tools**: Methods decorated with the `#[Tool]` attribute directly on your agent classes
2. **Class-Based Tools**: Standalone classes implementing the `ToolInterface`

## How Agents Use Tools

When you send a message to an agent, the following sequence occurs:

```
User Message → Agent → LLM
                        ↓
              "I need to call tool X with arguments Y"
                        ↓
              Agent executes tool
                        ↓
              Results returned to LLM
                        ↓
              LLM generates response
```

The agent loop continues until the LLM provides a final response without requesting additional tool calls, or until the maximum iteration limit is reached.

## Attribute-Based Tools

The simplest way to add tools to an agent is using PHP attributes:

```php
use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Tools\Attributes\Tool;
use AgenticOrchestrator\Tools\Attributes\ToolParameter;

class CustomerSupportAgent extends Agent
{
    protected string $name = 'Customer Support';

    public function instructions(): string
    {
        return 'You are a helpful customer support agent.';
    }

    #[Tool('Look up a customer order by ID')]
    public function lookupOrder(
        #[ToolParameter('The order ID to look up')]
        string $orderId,
    ): array {
        return Order::findOrFail($orderId)->toArray();
    }

    #[Tool('Get customer information')]
    public function getCustomer(
        #[ToolParameter('The customer email address', format: 'email')]
        string $email,
    ): array {
        return Customer::where('email', $email)->firstOrFail()->toArray();
    }
}
```

## Class-Based Tools

For reusable tools or complex logic, create standalone tool classes:

```php
use AgenticOrchestrator\Contracts\ToolInterface;

class SearchProductsTool implements ToolInterface
{
    public function getName(): string
    {
        return 'search_products';
    }

    public function getDescription(): string
    {
        return 'Search for products in the catalog';
    }

    public function getParameters(): array
    {
        return [
            'query' => [
                'type' => 'string',
                'description' => 'Search query',
                'required' => true,
            ],
            'category' => [
                'type' => 'string',
                'description' => 'Product category to filter by',
                'required' => false,
            ],
        ];
    }

    public function execute(array $arguments): mixed
    {
        $query = Product::query()
            ->where('name', 'like', "%{$arguments['query']}%");

        if (isset($arguments['category'])) {
            $query->where('category', $arguments['category']);
        }

        return $query->limit(10)->get()->toArray();
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
                    'properties' => $this->getParameters(),
                    'required' => ['query'],
                ],
            ],
        ];
    }

    public function isParallel(): bool
    {
        return true;
    }

    public function isCacheable(): bool
    {
        return true;
    }

    public function getCacheTtl(): int
    {
        return 300;
    }

    public function validate(array $arguments): bool
    {
        if (empty($arguments['query'])) {
            throw new \AgenticOrchestrator\Exceptions\ValidationException([
                'query' => 'The query field is required.',
            ]);
        }

        return true;
    }
}
```

Register class-based tools in your agent:

```php
class CatalogAgent extends Agent
{
    protected array $tools = [
        SearchProductsTool::class,
        GetProductDetailsTool::class,
    ];
}
```

## Tool Discovery

Agent Orchestrator automatically discovers tools through:

1. **Reflection**: Scans agent classes for methods with `#[Tool]` attributes
2. **Registration**: Reads the `$tools` property for external tool classes
3. **Registry**: Global tool registry for shared tools

```php
use AgenticOrchestrator\Tools\ToolRegistry;

// Discover tools from any class
$registry = app(ToolRegistry::class);
$tools = $registry->discoverFromClass(MyToolProvider::class);

// Get all discovered schemas
$schemas = $registry->getAllSchemas();
```

## Tool Schemas

Tools are converted to OpenAI-compatible function schemas that tell the LLM:

- The tool name and what it does
- What parameters it accepts
- Which parameters are required
- Type information and validation constraints

Example schema output:

```json
{
    "type": "function",
    "function": {
        "name": "lookupOrder",
        "description": "Look up a customer order by ID",
        "parameters": {
            "type": "object",
            "properties": {
                "orderId": {
                    "type": "string",
                    "description": "The order ID to look up"
                }
            },
            "required": ["orderId"]
        }
    }
}
```

## Tool Results

Every tool execution returns a `ToolResult` object containing:

- **toolCallId**: Unique identifier for the tool call
- **name**: The tool that was executed
- **arguments**: The arguments provided
- **result**: The execution result (null on failure)
- **success**: Whether execution succeeded
- **error**: Error message (null on success)
- **duration**: Execution time in milliseconds (optional)
- **cached**: Whether the result came from cache

```php
// Check result status
if ($result->isSuccess()) {
    $data = $result->result;
} else {
    Log::error("Tool failed: {$result->error}");
}

// Get content formatted for LLM
$content = $result->getContentForLlm();
```

## Documentation Structure

This section covers:

- **[Defining Tools](./defining-tools.md)**: Using `#[Tool]` and `#[ToolParameter]` attributes
- **[Built-in Tools](./built-in-tools.md)**: Common tool patterns and implementation guidance

## Best Practices

### Keep Tools Focused

Each tool should do one thing well. Instead of a generic "database" tool, create specific tools like `lookupOrder`, `getCustomer`, `searchProducts`.

### Write Clear Descriptions

The LLM relies on descriptions to understand when and how to use tools. Be specific:

```php
// Good
#[Tool('Search for products by name, returning up to 10 results')]

// Less helpful
#[Tool('Product search')]
```

### Handle Errors Gracefully

Tools should catch exceptions and return meaningful error messages:

```php
#[Tool('Get order details')]
public function getOrder(string $orderId): array
{
    try {
        return Order::findOrFail($orderId)->toArray();
    } catch (ModelNotFoundException $e) {
        throw new ToolExecutionException(
            'getOrder',
            "Order not found: {$orderId}",
            ['orderId' => $orderId]
        );
    }
}
```

### Consider Caching

For tools that perform expensive operations or return stable data, enable caching:

```php
#[Tool('Get exchange rates', cacheable: true, cacheTtl: 3600)]
public function getExchangeRates(): array
{
    return $this->exchangeService->getRates();
}
```

### Validate Input

Use `#[ToolParameter]` constraints or implement custom validation to catch invalid input early:

```php
#[Tool('Send email to customer')]
public function sendEmail(
    #[ToolParameter('Email address', format: 'email')]
    string $email,
    #[ToolParameter('Email subject', minLength: 1, maxLength: 200)]
    string $subject,
): bool {
    // Input is pre-validated
}
```
