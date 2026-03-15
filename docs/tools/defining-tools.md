# Defining Tools

This guide covers how to define tools for your agents using PHP attributes. The attribute-based approach provides a clean, declarative way to expose methods as callable tools for the LLM.

## The Tool Attribute

The `#[Tool]` attribute marks a public method as a tool that agents can invoke:

```php
use AgenticOrchestrator\Tools\Attributes\Tool;

#[Tool('Description of what this tool does')]
public function myTool(): mixed
{
    // Implementation
}
```

### Tool Attribute Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `description` | string | required | Description shown to the LLM |
| `name` | string|null | null | Override the method name |
| `parallel` | bool | true | Can execute in parallel with other tools |
| `cacheable` | bool | false | Whether results can be cached |
| `cacheTtl` | int | 300 | Cache TTL in seconds |
| `hidden` | bool | false | Hide from certain contexts |

### Basic Tool Definition

```php
use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Tools\Attributes\Tool;

class WeatherAgent extends Agent
{
    #[Tool('Get the current weather for a location')]
    public function getWeather(string $location): array
    {
        $weather = $this->weatherService->current($location);

        return [
            'location' => $location,
            'temperature' => $weather->temperature,
            'conditions' => $weather->conditions,
            'humidity' => $weather->humidity,
        ];
    }
}
```

### Naming Tools

By default, the tool name is the method name. Override this with the `name` parameter:

```php
#[Tool('Search for products', name: 'search_catalog')]
public function searchProducts(string $query): array
{
    // LLM will call this as 'search_catalog'
}
```

Use descriptive names that help the LLM understand the tool's purpose:

```php
// Good names
'lookup_order'
'get_customer_info'
'search_products'
'calculate_shipping'

// Avoid generic names
'get'
'fetch'
'process'
'handle'
```

### Hidden Tools

Mark tools as hidden to exclude them from tool discovery in certain contexts:

```php
#[Tool('Internal helper method', hidden: true)]
public function internalOperation(): void
{
    // This tool won't be exposed to the LLM
}
```

## The ToolParameter Attribute

The `#[ToolParameter]` attribute provides detailed information about method parameters:

```php
use AgenticOrchestrator\Tools\Attributes\ToolParameter;

#[Tool('Search for products')]
public function search(
    #[ToolParameter('The search query')]
    string $query,
    #[ToolParameter('Maximum results to return')]
    int $limit = 10,
): array {
    // Implementation
}
```

### ToolParameter Options

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `description` | string | required | Description shown to the LLM |
| `required` | bool|null | null | Required status (auto-detected) |
| `enum` | array|null | null | List of allowed values |
| `default` | mixed | null | Default value |
| `format` | string|null | null | Format hint (email, uri, date) |
| `minLength` | int|null | null | Minimum string length |
| `maxLength` | int|null | null | Maximum string length |
| `minimum` | int|float|null | null | Minimum numeric value |
| `maximum` | int|float|null | null | Maximum numeric value |
| `pattern` | string|null | null | Regex validation pattern |

### Required Parameters

By default, parameters without a default value are marked as required. Override this behavior:

```php
#[Tool('Update user profile')]
public function updateProfile(
    #[ToolParameter('User ID', required: true)]
    string $userId,

    #[ToolParameter('New email address', required: false)]
    ?string $email = null,

    #[ToolParameter('New name', required: false)]
    ?string $name = null,
): array {
    // At least userId is required
}
```

### Enum Constraints

Restrict parameter values to a specific set:

```php
#[Tool('Get weather forecast')]
public function getForecast(
    #[ToolParameter('Location name')]
    string $location,

    #[ToolParameter('Temperature unit', enum: ['celsius', 'fahrenheit'])]
    string $unit = 'celsius',

    #[ToolParameter('Forecast period', enum: ['today', 'week', 'month'])]
    string $period = 'today',
): array {
    // unit and period are constrained to specific values
}
```

The enum values are included in the schema, helping the LLM provide valid arguments.

### String Constraints

Apply length limits to string parameters:

```php
#[Tool('Send a notification')]
public function sendNotification(
    #[ToolParameter('Notification title', minLength: 1, maxLength: 100)]
    string $title,

    #[ToolParameter('Notification message', minLength: 1, maxLength: 1000)]
    string $message,

    #[ToolParameter('Optional URL', format: 'uri')]
    ?string $url = null,
): bool {
    // Strings are constrained by length
}
```

### Numeric Constraints

Define ranges for numeric parameters:

```php
#[Tool('Search with pagination')]
public function searchPaginated(
    #[ToolParameter('Search query')]
    string $query,

    #[ToolParameter('Page number', minimum: 1)]
    int $page = 1,

    #[ToolParameter('Items per page', minimum: 1, maximum: 100)]
    int $perPage = 20,
): array {
    // page must be >= 1
    // perPage must be between 1 and 100
}
```

### Pattern Validation

Use regex patterns for custom validation:

```php
#[Tool('Lookup order')]
public function lookupOrder(
    #[ToolParameter('Order ID', pattern: '^ORD-[0-9]{6}$')]
    string $orderId,
): array {
    // orderId must match pattern like 'ORD-123456'
}
```

### Format Hints

Standard format hints help with validation and LLM understanding:

```php
#[Tool('Create user account')]
public function createUser(
    #[ToolParameter('Email address', format: 'email')]
    string $email,

    #[ToolParameter('Profile URL', format: 'uri')]
    ?string $profileUrl = null,

    #[ToolParameter('Birth date', format: 'date')]
    ?string $birthDate = null,
): array {
    // Formats provide validation hints
}
```

Common format values:
- `email`: Email address
- `uri`: URL or URI
- `date`: Date string (ISO 8601)
- `date-time`: Date and time
- `uuid`: UUID string
- `hostname`: Network hostname

## Type Mapping

PHP types are automatically mapped to JSON Schema types:

| PHP Type | JSON Schema Type |
|----------|------------------|
| `string` | string |
| `int`, `integer` | integer |
| `float`, `double` | number |
| `bool`, `boolean` | boolean |
| `array` | array |
| `object`, `stdClass` | object |
| (untyped) | string |

```php
#[Tool('Process data')]
public function processData(
    string $name,      // → type: string
    int $count,        // → type: integer
    float $price,      // → type: number
    bool $active,      // → type: boolean
    array $items,      // → type: array
): void {
    // Types are preserved in the schema
}
```

## Complete Example

Here is a comprehensive example showing various attribute options:

```php
use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Tools\Attributes\Tool;
use AgenticOrchestrator\Tools\Attributes\ToolParameter;

class ECommerceAgent extends Agent
{
    protected string $name = 'E-Commerce Assistant';

    public function instructions(): string
    {
        return 'You help customers find products and manage orders.';
    }

    #[Tool('Search the product catalog')]
    public function searchProducts(
        #[ToolParameter('Search keywords')]
        string $query,

        #[ToolParameter('Product category', enum: ['electronics', 'clothing', 'home', 'sports'])]
        ?string $category = null,

        #[ToolParameter('Minimum price', minimum: 0)]
        ?float $minPrice = null,

        #[ToolParameter('Maximum price', minimum: 0)]
        ?float $maxPrice = null,

        #[ToolParameter('Number of results', minimum: 1, maximum: 50)]
        int $limit = 10,
    ): array {
        return Product::query()
            ->where('name', 'like', "%{$query}%")
            ->when($category, fn($q) => $q->where('category', $category))
            ->when($minPrice, fn($q) => $q->where('price', '>=', $minPrice))
            ->when($maxPrice, fn($q) => $q->where('price', '<=', $maxPrice))
            ->limit($limit)
            ->get()
            ->toArray();
    }

    #[Tool('Get detailed information about a product', cacheable: true, cacheTtl: 600)]
    public function getProductDetails(
        #[ToolParameter('The product ID or SKU')]
        string $productId,
    ): array {
        return Product::with(['reviews', 'specifications'])
            ->findOrFail($productId)
            ->toArray();
    }

    #[Tool('Look up an order by order number', name: 'lookup_order')]
    public function getOrder(
        #[ToolParameter('Order number', pattern: '^[A-Z]{2}[0-9]{8}$')]
        string $orderNumber,
    ): array {
        return Order::where('number', $orderNumber)
            ->with(['items', 'shipping'])
            ->firstOrFail()
            ->toArray();
    }

    #[Tool('Check product availability', parallel: true)]
    public function checkAvailability(
        #[ToolParameter('Product ID')]
        string $productId,

        #[ToolParameter('Quantity needed', minimum: 1)]
        int $quantity = 1,

        #[ToolParameter('Postal code for shipping estimate', minLength: 5, maxLength: 10)]
        ?string $postalCode = null,
    ): array {
        $product = Product::findOrFail($productId);
        $inStock = $product->stock >= $quantity;

        $result = [
            'product_id' => $productId,
            'available' => $inStock,
            'stock' => $product->stock,
            'requested' => $quantity,
        ];

        if ($postalCode && $inStock) {
            $result['shipping_estimate'] = $this->shippingService
                ->estimate($productId, $quantity, $postalCode);
        }

        return $result;
    }

    #[Tool('Add item to shopping cart')]
    public function addToCart(
        #[ToolParameter('Customer email', format: 'email')]
        string $customerEmail,

        #[ToolParameter('Product ID')]
        string $productId,

        #[ToolParameter('Quantity to add', minimum: 1, maximum: 99)]
        int $quantity = 1,
    ): array {
        $cart = Cart::firstOrCreate(['email' => $customerEmail]);

        $cart->addItem($productId, $quantity);

        return [
            'success' => true,
            'cart_total' => $cart->total,
            'item_count' => $cart->items->count(),
        ];
    }
}
```

## Generated Schema

The above tools generate schemas like:

```json
{
    "type": "function",
    "function": {
        "name": "searchProducts",
        "description": "Search the product catalog",
        "parameters": {
            "type": "object",
            "properties": {
                "query": {
                    "type": "string",
                    "description": "Search keywords"
                },
                "category": {
                    "type": "string",
                    "description": "Product category",
                    "enum": ["electronics", "clothing", "home", "sports"]
                },
                "minPrice": {
                    "type": "number",
                    "description": "Minimum price",
                    "minimum": 0
                },
                "maxPrice": {
                    "type": "number",
                    "description": "Maximum price",
                    "minimum": 0
                },
                "limit": {
                    "type": "integer",
                    "description": "Number of results",
                    "minimum": 1,
                    "maximum": 50,
                    "default": 10
                }
            },
            "required": ["query"]
        }
    }
}
```

## Tips for Effective Tool Definitions

### Write Clear Descriptions

The LLM uses descriptions to decide when to call a tool. Be specific:

```php
// Clear and specific
#[Tool('Search for products by name or description, returns up to 10 matching items')]

// Too vague
#[Tool('Search products')]
```

### Use Sensible Defaults

Provide defaults that work for common cases:

```php
#[ToolParameter('Number of results', minimum: 1, maximum: 100)]
int $limit = 10,  // Reasonable default
```

### Constrain Values Appropriately

Use constraints to prevent invalid states:

```php
// Prevent negative prices
#[ToolParameter('Price', minimum: 0)]
float $price,

// Limit text length
#[ToolParameter('Comment', maxLength: 500)]
string $comment,

// Restrict to valid options
#[ToolParameter('Status', enum: ['pending', 'approved', 'rejected'])]
string $status,
```

### Group Related Tools

Keep related functionality together in the same agent or tool provider class:

```php
class OrderManagementAgent extends Agent
{
    // All order-related tools together
    public function createOrder() { }
    public function getOrder() { }
    public function updateOrder() { }
    public function cancelOrder() { }
}
```
