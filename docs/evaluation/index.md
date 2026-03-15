# Agent Evaluation

Agent Orchestrator provides a comprehensive evaluation framework for testing AI agents. This framework helps you validate agent behavior, measure response quality, and catch regressions before they reach production.

## Why Evaluate Agents?

Traditional unit testing falls short when testing AI agents because:

- **Non-Deterministic Outputs**: LLM responses vary between calls, even with identical inputs
- **Quality is Subjective**: "Correct" responses are often a matter of degree, not binary pass/fail
- **Context Matters**: The same answer might be excellent in one context and poor in another
- **Regressions are Subtle**: Model updates or prompt changes can degrade quality in hard-to-detect ways

The evaluation framework addresses these challenges by combining:

1. **Deterministic Assertions**: Traditional pass/fail checks for structural requirements
2. **LLM-as-Judge Metrics**: Quality scoring using another LLM to evaluate responses

## Core Concepts

### Test Suites

A **TestSuite** groups related test cases for an agent. Each suite targets a specific agent class and contains test cases that verify different aspects of agent behavior.

```php
use AgenticOrchestrator\Evaluation\TestSuite;
use AgenticOrchestrator\Evaluation\TestCase;

class CustomerSupportAgentTestSuite extends TestSuite
{
    protected string $agent = CustomerSupportAgent::class;

    public function testCases(): array
    {
        return [
            new TestCase(
                name: 'greeting_response',
                input: 'Hello, I need help with my order',
                assertions: [
                    'contains' => ['help', 'order'],
                ],
                metrics: [
                    'helpfulness' => ['threshold' => 0.8],
                ],
            ),
        ];
    }
}
```

### Test Cases

A **TestCase** defines a single evaluation scenario with:

| Property | Description |
|----------|-------------|
| `name` | Unique identifier for the test case |
| `input` | The message sent to the agent |
| `assertions` | Deterministic checks (contains, matches, JSON structure) |
| `metrics` | LLM-judged quality scores (relevance, accuracy, helpfulness) |
| `expectedOutput` | Optional reference output for comparison |
| `context` | Additional context for the agent or evaluation |
| `metadata` | Additional configuration for assertions and metrics |
| `timeout` | Maximum seconds to wait for agent response (default: 30) |

### Assertions

**Assertions** are deterministic checks that either pass or fail:

- **contains**: Output contains specific strings
- **not_contains**: Output does not contain forbidden strings
- **matches**: Output matches regex patterns
- **json**: Output is valid JSON with optional structure validation
- **length**: Output length is within bounds

### Metrics

**Metrics** use an LLM judge to score response quality on a 0.0 to 1.0 scale:

| Metric | Description | Default Threshold |
|--------|-------------|-------------------|
| `relevance` | How on-topic is the response? | 0.7 |
| `accuracy` | Are the facts correct? | 0.8 |
| `helpfulness` | Is it useful to the user? | 0.7 |
| `completeness` | Does it address all aspects? | 0.7 |
| `tone` | Is the tone appropriate? | 0.7 |
| `safety` | Is the response safe and appropriate? | 0.9 |

### LLM-as-Judge

The **LlmJudge** component uses a separate LLM (typically GPT-4o-mini) to evaluate agent responses. This approach:

- Enables nuanced quality assessment beyond string matching
- Provides reasoning for scores to aid debugging
- Scales to complex evaluation criteria
- Handles the inherent variability in LLM outputs

## Evaluation Flow

```
TestSuite
    |
    v
+---------------------------+
|   For each TestCase:      |
|   1. Run agent            |
|   2. Run assertions       |
|   3. Run metric evals     |
+---------------------------+
    |
    v
EvaluationResult
    |
    v
+---------------------------+
|   - Pass/fail status      |
|   - Assertion results     |
|   - Metric scores         |
|   - Duration & metadata   |
+---------------------------+
```

## Quick Example

Here is a complete example of creating and running an evaluation:

```php
use AgenticOrchestrator\Evaluation\TestSuite;
use AgenticOrchestrator\Evaluation\TestCase;

class ProductAgentTestSuite extends TestSuite
{
    protected string $agent = ProductAgent::class;

    public function testCases(): array
    {
        return [
            new TestCase(
                name: 'product_search',
                input: 'Find laptops under $1000',
                assertions: [
                    'contains' => ['laptop'],
                    'json' => ['has_keys' => ['products', 'count']],
                ],
                metrics: [
                    'relevance' => ['threshold' => 0.8],
                    'helpfulness' => ['threshold' => 0.7],
                ],
            ),

            new TestCase(
                name: 'out_of_stock_handling',
                input: 'I want to buy the SuperWidget 3000',
                assertions: [
                    'not_contains' => ['error', 'exception'],
                ],
                metrics: [
                    'helpfulness' => ['threshold' => 0.8],
                ],
                context: [
                    'product_status' => 'out_of_stock',
                ],
            ),
        ];
    }
}

// Run the evaluation
$result = ProductAgentTestSuite::make()->run();

// Check results
echo "Pass rate: {$result->passRate()}%\n";
echo "Average metric score: {$result->averageMetricScore()}\n";

if ($result->hasFailed()) {
    foreach ($result->failed() as $failure) {
        echo "Failed: {$failure->testCase->name}\n";
    }
}
```

## When to Use Evaluations

| Scenario | Recommended Approach |
|----------|---------------------|
| Pre-deployment validation | Full test suite with all metrics |
| CI/CD pipeline | Assertions only (faster, no API calls) |
| Prompt engineering | Run specific test cases with detailed metrics |
| Model comparison | Same test suite against different providers |
| Regression testing | Track metric scores over time |

## Documentation Structure

This section covers:

1. **[Test Suites](./test-suites.md)**: Creating test suite classes and organizing test cases
2. **[Assertions](./assertions.md)**: Available assertions and how to use them
3. **[Metrics](./metrics.md)**: Built-in quality metrics and scoring
4. **[LLM Judge](./llm-judge.md)**: Configuring and customizing the LLM judge
5. **[Running Evaluations](./running-evaluations.md)**: Executing evaluations and interpreting results
6. **[CI Integration](./ci-integration.md)**: Integrating evaluations into CI/CD pipelines

## Next Steps

- **[Test Suites](./test-suites.md)**: Learn how to create comprehensive test suites
- **[Running Evaluations](./running-evaluations.md)**: Execute your first evaluation
