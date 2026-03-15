# Test Suites

A test suite is a collection of test cases that evaluate a specific agent. Test suites provide a structured way to organize and run evaluations.

## Creating a Test Suite

Create a test suite by extending the `TestSuite` base class:

```php
<?php

namespace App\Evaluation;

use AgenticOrchestrator\Evaluation\TestSuite;
use AgenticOrchestrator\Evaluation\TestCase;
use App\Agents\CustomerSupportAgent;

class CustomerSupportAgentTestSuite extends TestSuite
{
    /**
     * The agent class to test.
     */
    protected string $agent = CustomerSupportAgent::class;

    /**
     * Define test cases for the suite.
     */
    public function testCases(): array
    {
        return [
            // Test cases go here
        ];
    }
}
```

## Test Suite Properties

| Property | Type | Description |
|----------|------|-------------|
| `$agent` | `string` | The fully-qualified class name of the agent to test |
| `$team` | `int\|string\|object\|null` | Optional team scope for multi-tenant agents |
| `$judge` | `LlmJudge\|null` | Custom LLM judge instance |
| `$evaluateMetrics` | `bool` | Whether to run LLM-judged metrics (default: true) |

## Defining Test Cases

The `testCases()` method returns an array of `TestCase` objects. Each test case defines a single evaluation scenario.

### Basic Test Case

```php
public function testCases(): array
{
    return [
        new TestCase(
            name: 'basic_greeting',
            input: 'Hello!',
            assertions: [
                'contains' => ['hello', 'help'],
            ],
        ),
    ];
}
```

### Test Case with Metrics

```php
new TestCase(
    name: 'product_recommendation',
    input: 'I need a laptop for programming',
    assertions: [
        'contains' => ['laptop', 'recommend'],
    ],
    metrics: [
        'relevance' => ['threshold' => 0.8],
        'helpfulness' => ['threshold' => 0.7],
    ],
),
```

### Test Case with Expected Output

When you provide an expected output, the LLM judge uses it as a reference for scoring:

```php
new TestCase(
    name: 'factual_response',
    input: 'What are your business hours?',
    expectedOutput: 'Our business hours are Monday through Friday, 9 AM to 5 PM EST.',
    metrics: [
        'accuracy' => ['threshold' => 0.9],
    ],
),
```

### Test Case with Context

Context provides additional information to the agent or affects how metrics are evaluated:

```php
new TestCase(
    name: 'vip_customer_handling',
    input: 'I want to return this item',
    context: [
        'customer_tier' => 'vip',
        'purchase_date' => '2024-01-15',
        'return_policy' => 'extended_30_days',
    ],
    metrics: [
        'helpfulness' => ['threshold' => 0.9],
        'tone' => ['threshold' => 0.85],
    ],
),
```

### Test Case with Metadata

Metadata provides additional configuration for assertions and metrics:

```php
new TestCase(
    name: 'formal_response',
    input: 'I need to speak with a manager',
    assertions: [
        'not_contains' => ['sorry', 'unfortunately'],
    ],
    metadata: [
        'case_sensitive' => true,       // For contains/not_contains
        'expected_tone' => 'formal',    // For tone metric
        'required_elements' => [        // For completeness metric
            'acknowledgment',
            'escalation_option',
            'timeline',
        ],
    ],
),
```

## Test Case Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | `string` | Yes | Unique identifier for the test case |
| `input` | `string` | Yes | The message to send to the agent |
| `assertions` | `array` | No | Deterministic checks to run |
| `metrics` | `array` | No | LLM-judged metrics to evaluate |
| `expectedOutput` | `string\|null` | No | Reference output for comparison |
| `context` | `array` | No | Additional context for agent/evaluation |
| `metadata` | `array` | No | Configuration for assertions/metrics |
| `timeout` | `int` | No | Timeout in seconds (default: 30) |

## Multi-Tenant Test Suites

For multi-tenant applications, scope the test suite to a specific team:

```php
$result = CustomerSupportAgentTestSuite::make()
    ->forTeam($team)
    ->run();
```

Or set a default team in the test suite:

```php
class TenantAgentTestSuite extends TestSuite
{
    protected string $agent = TenantAgent::class;
    protected int|string|object|null $team = 1;

    public function testCases(): array
    {
        return [
            // Test cases run in the context of team 1
        ];
    }
}
```

## Customizing the LLM Judge

Provide a custom LLM judge for metric evaluation:

```php
use AgenticOrchestrator\Evaluation\LlmJudge;
use Prism\Prism\Enums\Provider;

$result = CustomerSupportAgentTestSuite::make()
    ->withJudge(
        LlmJudge::make()
            ->withProvider(Provider::Anthropic)
            ->withModel('claude-3-5-haiku-20241022')
    )
    ->run();
```

## Disabling Metrics

For faster test runs (useful in CI), disable metric evaluation:

```php
$result = CustomerSupportAgentTestSuite::make()
    ->withoutMetrics()
    ->run();
```

This runs only the assertions, skipping LLM judge API calls.

## Registering Custom Assertions

Add custom assertions to the test suite:

```php
use AgenticOrchestrator\Evaluation\Contracts\AssertionInterface;
use AgenticOrchestrator\Evaluation\AssertionResult;
use AgenticOrchestrator\Evaluation\TestCase;

class SentimentAssertion implements AssertionInterface
{
    public function name(): string
    {
        return 'sentiment';
    }

    public function evaluate(string $actualOutput, mixed $config, TestCase $testCase): AssertionResult
    {
        // Custom sentiment analysis logic
        $sentiment = $this->analyzeSentiment($actualOutput);
        $expected = $config['expected'] ?? 'positive';

        if ($sentiment === $expected) {
            return AssertionResult::pass(
                name: $this->name(),
                message: "Sentiment matches expected: {$expected}",
                expected: $expected,
                actual: $sentiment,
            );
        }

        return AssertionResult::fail(
            name: $this->name(),
            message: "Expected {$expected} sentiment, got {$sentiment}",
            expected: $expected,
            actual: $sentiment,
        );
    }

    private function analyzeSentiment(string $text): string
    {
        // Your sentiment analysis implementation
    }
}

// Register and use
$result = CustomerSupportAgentTestSuite::make()
    ->registerAssertion(new SentimentAssertion())
    ->run();
```

Then use in test cases:

```php
new TestCase(
    name: 'positive_response',
    input: 'Great product!',
    assertions: [
        'sentiment' => ['expected' => 'positive'],
    ],
),
```

## Setup and Teardown

The `setUp()` and `tearDown()` methods are available for overriding, but note that they are not automatically called by the test runner. If you need setup and teardown logic, you should call these methods manually or use the constructor and destructor:

```php
class DatabaseAgentTestSuite extends TestSuite
{
    protected string $agent = DatabaseAgent::class;

    public function __construct()
    {
        parent::__construct();

        // Seed test data
        Product::factory()->count(10)->create();
        Customer::factory()->count(5)->create();
    }

    public function __destruct()
    {
        // Clean up test data
        Product::query()->delete();
        Customer::query()->delete();
    }

    public function testCases(): array
    {
        return [
            new TestCase(
                name: 'product_query',
                input: 'How many products do we have?',
                assertions: [
                    'contains' => ['10'],
                ],
            ),
        ];
    }
}
```

Alternatively, you can extend the `run()` method to call setup and teardown:

```php
use AgenticOrchestrator\Evaluation\EvaluationResult;

// In your TestSuite subclass:
public function run(): EvaluationResult
{
    $this->setUp();

    try {
        return parent::run();
    } finally {
        $this->tearDown();
    }
}
```

## Running Individual Test Cases

Run a specific test case by name. Note that `runCase()` returns `null` if the test case is not found:

```php
$result = CustomerSupportAgentTestSuite::make()
    ->runCase('greeting_response');

if ($result === null) {
    echo "Test case not found!\n";
} elseif ($result->hasPassed()) {
    echo "Test passed!\n";
} else {
    echo "Test failed: " . $result->testCase->name . "\n";
    foreach ($result->getFailedAssertions() as $assertion) {
        echo "  - {$assertion->message}\n";
    }
}
```

## Organizing Test Suites

### By Agent

Create one test suite per agent:

```
app/Evaluation/
    CustomerSupportAgentTestSuite.php
    ProductSearchAgentTestSuite.php
    OrderManagementAgentTestSuite.php
```

### By Functionality

Create test suites for specific functionality across agents:

```
app/Evaluation/
    GreetingTestSuite.php
    ErrorHandlingTestSuite.php
    SecurityTestSuite.php
```

### By Environment

Create different test suites for different contexts:

```php
class ProductionReadinessTestSuite extends TestSuite
{
    protected string $agent = CustomerSupportAgent::class;

    public function testCases(): array
    {
        return [
            // Critical production scenarios
        ];
    }
}

class RegressionTestSuite extends TestSuite
{
    protected string $agent = CustomerSupportAgent::class;

    public function testCases(): array
    {
        return [
            // Cases that previously failed
        ];
    }
}
```

## Best Practices

### 1. Use Descriptive Test Names

```php
// Good
new TestCase(name: 'handles_refund_request_within_policy'),
new TestCase(name: 'rejects_expired_warranty_claim'),

// Bad
new TestCase(name: 'test1'),
new TestCase(name: 'refund'),
```

### 2. Test Edge Cases

```php
new TestCase(
    name: 'handles_empty_input',
    input: '',
    assertions: ['not_contains' => ['error', 'exception']],
),

new TestCase(
    name: 'handles_very_long_input',
    input: str_repeat('word ', 1000),
    timeout: 60,
),
```

### 3. Combine Assertions and Metrics

Use assertions for structural requirements and metrics for quality:

```php
new TestCase(
    name: 'product_recommendation',
    input: 'Recommend a laptop for gaming',
    assertions: [
        'json' => ['has_keys' => ['recommendations']],  // Structure check
        'not_contains' => ['I cannot', 'I am unable'],  // Safety check
    ],
    metrics: [
        'relevance' => ['threshold' => 0.8],    // Quality check
        'helpfulness' => ['threshold' => 0.75], // Quality check
    ],
),
```

### 4. Set Appropriate Thresholds

Start with lower thresholds and increase as you refine your agent:

```php
// Initial development
'relevance' => ['threshold' => 0.6],

// After refinement
'relevance' => ['threshold' => 0.8],

// Production requirement
'relevance' => ['threshold' => 0.9],
```

## Next Steps

- **[Assertions](./assertions.md)**: Learn about all available assertions
- **[Metrics](./metrics.md)**: Understand LLM-judged quality metrics
