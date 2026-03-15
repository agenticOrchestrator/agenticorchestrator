# Built-in Tools

Agent Orchestrator provides the core infrastructure for defining and executing tools, but does not include pre-built tool implementations. This design allows you to create tools tailored to your specific use cases.

## Creating Your Own Tools

You have two options for creating tools:

### Option 1: Attribute-Based Tools (Recommended)

Define tools directly on your agent classes using PHP attributes:

```php
use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Tools\Attributes\Tool;
use AgenticOrchestrator\Tools\Attributes\ToolParameter;

class ResearchAgent extends Agent
{
    protected string $name = 'Research Assistant';

    public function instructions(): string
    {
        return 'You help users research topics and find information.';
    }

    #[Tool('Search the web for information')]
    public function webSearch(
        #[ToolParameter('The search query')]
        string $query,
        #[ToolParameter('Maximum results to return', minimum: 1, maximum: 20)]
        int $limit = 5,
    ): array {
        // Implement your search logic using your preferred search API
        // For example: Serper, Google Custom Search, Bing, etc.
        return $this->searchService->search($query, $limit);
    }

    #[Tool('Fetch and extract content from a URL')]
    public function fetchUrl(
        #[ToolParameter('The URL to fetch', format: 'uri')]
        string $url,
    ): array {
        // Implement your URL fetching logic
        $response = Http::get($url);
        return [
            'content' => $response->body(),
            'status' => $response->status(),
        ];
    }
}
```

### Option 2: Class-Based Tools

Create reusable tool classes implementing `ToolInterface`:

```php
use AgenticOrchestrator\Contracts\ToolInterface;

class WebSearchTool implements ToolInterface
{
    public function __construct(
        protected SearchServiceInterface $searchService,
    ) {}

    public function getName(): string
    {
        return 'web_search';
    }

    public function getDescription(): string
    {
        return 'Search the web for information on any topic';
    }

    public function getParameters(): array
    {
        return [
            'query' => [
                'type' => 'string',
                'description' => 'The search query',
                'required' => true,
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum results to return',
                'required' => false,
                'default' => 5,
            ],
        ];
    }

    public function execute(array $arguments): mixed
    {
        return $this->searchService->search(
            $arguments['query'],
            $arguments['limit'] ?? 5
        );
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
        return 300; // 5 minutes
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
class MyAgent extends Agent
{
    protected array $tools = [
        WebSearchTool::class,
        DatabaseQueryTool::class,
    ];
}
```

## Common Tool Patterns

Here are example implementations for common tool types you might want to create:

### Database Query Tool

```php
#[Tool('Query the database for information')]
public function queryDatabase(
    #[ToolParameter('The SQL query to execute')]
    string $query,
): array {
    // Use read-only connection for safety
    return DB::connection('readonly')
        ->select($query);
}
```

### File Operations

```php
#[Tool('Read contents of a file')]
public function readFile(
    #[ToolParameter('The file path to read')]
    string $path,
): string {
    $basePath = storage_path('documents');
    $fullPath = realpath($basePath . '/' . $path);

    // Security: ensure path is within allowed directory
    if (!str_starts_with($fullPath, $basePath)) {
        throw new \RuntimeException('Access denied');
    }

    return file_get_contents($fullPath);
}
```

### HTTP Requests

```php
#[Tool('Make an HTTP request to an API')]
public function httpRequest(
    #[ToolParameter('HTTP method', enum: ['GET', 'POST', 'PUT', 'DELETE'])]
    string $method,
    #[ToolParameter('Request URL', format: 'uri')]
    string $url,
    #[ToolParameter('Request body (for POST/PUT)')]
    ?array $body = null,
): array {
    $response = Http::send($method, $url, [
        'json' => $body,
    ]);

    return [
        'status' => $response->status(),
        'body' => $response->json(),
    ];
}
```

### Calculator

```php
#[Tool('Perform mathematical calculations')]
public function calculate(
    #[ToolParameter('Mathematical expression to evaluate')]
    string $expression,
): array {
    // Use a safe math expression evaluator
    $result = $this->mathEvaluator->evaluate($expression);

    return ['result' => $result];
}
```

## Best Practices

### Security Considerations

1. **Validate all inputs** before executing operations
2. **Use read-only database connections** for query tools
3. **Restrict file access** to specific directories
4. **Whitelist allowed hosts** for HTTP request tools
5. **Set timeouts** on external API calls
6. **Sanitize output** before returning to the LLM

### Performance Considerations

1. **Enable caching** for idempotent tools with stable results
2. **Set appropriate cache TTLs** based on data freshness needs
3. **Mark tools as parallel** when they have no side effects
4. **Limit result sizes** to avoid token bloat in LLM responses

### Error Handling

```php
#[Tool('Get order details')]
public function getOrder(
    #[ToolParameter('Order ID')]
    string $orderId,
): array {
    try {
        return Order::findOrFail($orderId)->toArray();
    } catch (ModelNotFoundException $e) {
        throw new ToolExecutionException(
            toolName: 'getOrder',
            message: "Order not found: {$orderId}",
            arguments: ['orderId' => $orderId]
        );
    }
}
```

## Tool Discovery

The `ToolRegistry` automatically discovers tools:

```php
use AgenticOrchestrator\Tools\ToolRegistry;

$registry = app(ToolRegistry::class);

// Discover attribute-based tools from a class
$tools = $registry->discoverFromClass(MyAgent::class);

// Register a class-based tool
$registry->register(WebSearchTool::class);

// Get all tool schemas for the LLM
$schemas = $registry->getAllSchemas();
```
