# Quick Start Guide

Get your first AI agent running in under 5 minutes.

## Prerequisites

- Laravel 11.x or 12.x installed
- PHP 8.3+
- An OpenAI API key (or other supported LLM provider)

## Step 1: Install the Package

```bash
composer require agenticorchestrator/agenticorchestrator
```

## Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=agent-orchestrator-config
```

## Step 3: Set Your API Key

Add to your `.env` file:

```env
OPENAI_API_KEY=your-api-key-here
```

## Step 4: Create Your First Agent

```bash
php artisan agent:make CustomerSupportAgent
```

This creates `app/Agents/CustomerSupportAgent.php`:

```php
<?php

namespace App\Agents;

use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Tools\Attributes\Tool;
use AgenticOrchestrator\Tools\Attributes\ToolParameter;

class CustomerSupportAgent extends Agent
{
    protected string $name = 'customer_support';

    protected string $description = 'Handles customer support inquiries';

    protected string $model = 'gpt-4o';

    public function instructions(): string
    {
        return <<<PROMPT
You are a helpful customer support agent. You help customers with:
- Order status inquiries
- Product questions
- Returns and refunds

Always be polite and professional.
PROMPT;
    }

    #[Tool('Look up an order by its ID')]
    public function lookupOrder(
        #[ToolParameter('The order ID to look up')]
        string $orderId
    ): array {
        // Replace with your actual order lookup logic
        return [
            'id' => $orderId,
            'status' => 'shipped',
            'tracking' => 'ABC123456',
        ];
    }
}
```

## Step 5: Use Your Agent

```php
use App\Agents\CustomerSupportAgent;

// Simple response
$response = CustomerSupportAgent::make()
    ->respond('Where is my order #12345?');

echo $response->content;
// "I found your order #12345. It has been shipped and is on its way!
//  Your tracking number is ABC123456."
```

## Step 6: Add Memory (Optional)

Enable conversation memory to maintain context:

```php
class CustomerSupportAgent extends Agent
{
    protected array $memory = [
        'driver' => 'cache',
        'ttl' => 3600, // 1 hour
    ];

    // ... rest of agent
}
```

Now your agent remembers the conversation:

```php
$agent = CustomerSupportAgent::make();

$agent->respond('Where is my order #12345?');
$agent->respond('When will it arrive?'); // Agent remembers the order context
```

## Step 7: Try Interactive Chat

Test your agent interactively:

```bash
php artisan agent:chat customer_support
```

## Next Steps

- **[Creating Agents](../agents/creating-agents.md)** - Learn about agent configuration
- **[Defining Tools](../tools/defining-tools.md)** - Add more capabilities
- **[Memory System](../memory/index.md)** - Persistent conversations
- **[Multi-Tenancy](../multi-tenancy/index.md)** - Team-scoped agents
- **[Workflows](../workflows/index.md)** - Coordinate multiple agents

## Example: Complete Agent

Here's a more complete example with multiple tools:

```php
<?php

namespace App\Agents;

use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Tools\Attributes\Tool;
use AgenticOrchestrator\Tools\Attributes\ToolParameter;
use App\Models\Order;
use App\Models\Product;

class CustomerSupportAgent extends Agent
{
    protected string $name = 'customer_support';
    protected string $description = 'Full-featured customer support agent';
    protected string $model = 'gpt-4o';

    protected array $memory = [
        'driver' => 'cache',
        'ttl' => 3600,
    ];

    public function instructions(): string
    {
        return <<<PROMPT
You are a helpful customer support agent for our e-commerce store.

Your capabilities:
- Look up order status and tracking information
- Check product availability
- Process return requests
- Answer product questions

Guidelines:
- Always verify the customer's order before providing information
- Be empathetic when handling complaints
- Escalate complex issues to human support
PROMPT;
    }

    #[Tool('Look up order details and status')]
    public function lookupOrder(
        #[ToolParameter('The order ID')]
        string $orderId
    ): array {
        $order = Order::find($orderId);

        if (!$order) {
            return ['error' => 'Order not found'];
        }

        return $order->toArray();
    }

    #[Tool('Check product availability')]
    public function checkAvailability(
        #[ToolParameter('The product SKU')]
        string $sku
    ): array {
        $product = Product::where('sku', $sku)->first();

        return [
            'sku' => $sku,
            'available' => $product?->in_stock ?? false,
            'quantity' => $product?->quantity ?? 0,
        ];
    }

    #[Tool('Initiate a return request')]
    public function initiateReturn(
        #[ToolParameter('The order ID')]
        string $orderId,
        #[ToolParameter('Reason for return')]
        string $reason
    ): array {
        // Your return logic here
        return [
            'return_id' => 'RET-' . uniqid(),
            'status' => 'initiated',
            'instructions' => 'Please ship the item to our returns center.',
        ];
    }
}
```

## Troubleshooting

### API Key Issues

If you get authentication errors:

```php
// Check your configuration
dd(config('agent-orchestrator.providers.openai.api_key'));
```

### Memory Not Working

Ensure your cache driver is configured:

```bash
php artisan cache:clear
```

### Agent Not Found

Register your agents in the config or use auto-discovery:

```php
// config/agent-orchestrator.php
'agents' => [
    'auto_discover' => true,
    'paths' => [
        app_path('Agents'),
    ],
],
```
