# Creating Workflows

This guide covers how to create workflows by implementing the `WorkflowInterface` and using the `WorkflowDefinition` class to define steps.

## The WorkflowInterface

All workflows implement the `WorkflowInterface`:

```php
<?php

namespace AgenticOrchestrator\Contracts;

use AgenticOrchestrator\Workflows\WorkflowDefinition;

interface WorkflowInterface
{
    /**
     * Define the workflow structure.
     */
    public function definition(): WorkflowDefinition;

    /**
     * Scope the workflow to a team.
     */
    public function forTeam(int|string|object $team): static;
}
```

## Creating a Basic Workflow

Create a new workflow class that implements `WorkflowInterface`:

```php
<?php

namespace App\Workflows;

use AgenticOrchestrator\Contracts\WorkflowInterface;
use AgenticOrchestrator\Workflows\WorkflowDefinition;

class CustomerSupportWorkflow implements WorkflowInterface
{
    protected ?object $team = null;

    public function definition(): WorkflowDefinition
    {
        return WorkflowDefinition::create()
            ->name('Customer Support')
            ->description('Handles customer support tickets using AI agents')

            // Classify the ticket
            ->agent('classify', 'classifier-agent', function ($ctx) {
                return "Classify this support ticket:\n\n{$ctx->get('ticket_content')}";
            })

            // Route to appropriate handler
            ->agent('handle', 'support-agent', function ($ctx) {
                $category = $ctx->get('classify')['content'];
                return "Handle this {$category} support request: {$ctx->get('ticket_content')}";
            })

            // Generate response
            ->agent('respond', 'response-agent', function ($ctx) {
                return "Generate a customer-friendly response based on: {$ctx->get('handle')['content']}";
            });
    }

    public function forTeam(int|string|object $team): static
    {
        $this->team = is_object($team) ? $team : null;
        return $this;
    }
}
```

## Using WorkflowDefinition

The `WorkflowDefinition` class provides a fluent API for building workflows.

### Basic Structure

```php
use AgenticOrchestrator\Workflows\WorkflowDefinition;

$definition = WorkflowDefinition::create()
    ->name('Workflow Name')
    ->description('Workflow description')
    ->metadata(['version' => '1.0', 'author' => 'Team A']);
```

### Adding Agent Steps

Use the `agent()` method to add steps that invoke AI agents:

```php
// With a static message
$definition->agent('step-name', 'agent-name', 'Your prompt here');

// With a dynamic message using closure
$definition->agent('step-name', 'agent-name', function ($context) {
    return "Process: " . $context->get('previous_output');
});

// With an agent instance
$definition->agent('step-name', $agentInstance, 'Your prompt');
```

### Adding Callback Steps

Use the `callback()` method for custom logic:

```php
$definition->callback('transform-data', function ($context) {
    $data = $context->get('raw_data');

    // Transform the data
    $processed = array_map(fn($item) => strtoupper($item), $data);

    return ['processed_data' => $processed];
});
```

### Adding Approval Steps

Use the `approval()` method for human-in-the-loop steps:

```php
// Static prompt
$definition->approval('review', 'Please review this content before publishing');

// Dynamic prompt
$definition->approval('review', function ($context) {
    $content = $context->get('draft')['content'];
    return "Review the following content:\n\n{$content}";
});
```

### Adding Conditional Steps

Use the `when()` method for conditional branching:

```php
use AgenticOrchestrator\Workflows\Steps\AgentStep;

$definition->when(
    'quality-check',
    fn($ctx) => $ctx->get('score') >= 80,    // Condition
    AgentStep::make('approver', 'Approve'),   // Then step
    AgentStep::make('reviser', 'Revise')      // Else step (optional)
);
```

### Adding Parallel Steps

Use the `parallel()` method to execute steps concurrently:

```php
use AgenticOrchestrator\Workflows\Steps\AgentStep;

$definition->parallel('gather-insights', [
    AgentStep::make('market-agent', 'Analyze market trends'),
    AgentStep::make('competitor-agent', 'Analyze competitors'),
    AgentStep::make('customer-agent', 'Analyze customer feedback'),
]);
```

### Adding Sequential Step Groups

Use the `sequential()` method to group steps that must run in order:

```php
$definition->sequential('review-process', [
    AgentStep::make('reviewer-1', 'First review'),
    AgentStep::make('reviewer-2', 'Second review'),
    AgentStep::make('finalizer', 'Finalize'),
]);
```

### Adding Supervisor Pattern

Use the `supervisor()` method for dynamic agent orchestration:

```php
$definition->supervisor('orchestrator', 'supervisor-agent', [
    'researcher' => 'research-agent',
    'writer' => 'writer-agent',
    'editor' => 'editor-agent',
]);
```

## Step Configuration

### Setting Output Keys

By default, step results are stored using the step name. You can customize this:

```php
$definition
    ->agent('analyze', 'analyzer', 'Analyze the input')
    ->output('analyze', 'analysis_result');  // Store as 'analysis_result'
```

### Defining Dependencies

Specify that a step depends on other steps completing first:

```php
$definition
    ->agent('step-a', 'agent-a', 'First task')
    ->agent('step-b', 'agent-b', 'Second task')
    ->agent('step-c', 'agent-c', 'Depends on A and B')
    ->after('step-c', ['step-a', 'step-b']);
```

### Adding Steps Directly

You can add pre-configured step instances:

```php
use AgenticOrchestrator\Workflows\Steps\AgentStep;

$step = AgentStep::make('my-agent', 'Do something')
    ->as('custom-step')
    ->outputAs('custom_output')
    ->retry(5)
    ->timeout(120)
    ->dependsOn(['previous-step']);

$definition->addStep($step);
// or with explicit name
$definition->addStep('explicit-name', $step);
```

## Complete Workflow Example

Here is a comprehensive workflow implementation:

```php
<?php

namespace App\Workflows;

use AgenticOrchestrator\Contracts\WorkflowInterface;
use AgenticOrchestrator\Workflows\WorkflowDefinition;
use AgenticOrchestrator\Workflows\Steps\AgentStep;
use AgenticOrchestrator\Workflows\Steps\CallbackStep;
use AgenticOrchestrator\Workflows\Steps\ConditionalStep;

class DocumentProcessingWorkflow implements WorkflowInterface
{
    protected ?object $team = null;

    public function definition(): WorkflowDefinition
    {
        return WorkflowDefinition::create()
            ->name('Document Processing Pipeline')
            ->description('Extracts, processes, and summarizes documents')
            ->metadata([
                'version' => '2.0',
                'category' => 'document-processing',
            ])

            // Step 1: Extract text from document
            ->callback('extract', function ($ctx) {
                $document = $ctx->get('document');
                // Extraction logic here
                return [
                    'text' => $document['content'],
                    'metadata' => ['pages' => 10, 'type' => 'pdf'],
                ];
            })

            // Step 2: Analyze document structure
            ->agent('analyze', 'document-analyzer', function ($ctx) {
                $text = $ctx->get('extract')['text'];
                return "Analyze the structure and key topics in this document:\n\n" . substr($text, 0, 5000);
            })

            // Step 3: Parallel processing - extract different aspects
            ->parallel('extract-aspects', [
                AgentStep::make('entity-extractor', function ($ctx) {
                    return "Extract all named entities from: " . $ctx->get('extract')['text'];
                })->as('entities'),

                AgentStep::make('key-phrase-extractor', function ($ctx) {
                    return "Extract key phrases from: " . $ctx->get('extract')['text'];
                })->as('key-phrases'),

                AgentStep::make('sentiment-analyzer', function ($ctx) {
                    return "Analyze sentiment of: " . $ctx->get('extract')['text'];
                })->as('sentiment'),
            ])

            // Step 4: Conditional quality gate
            ->when(
                'quality-gate',
                fn($ctx) => $this->checkQuality($ctx),
                AgentStep::make('summarizer', function ($ctx) {
                    return $this->buildSummaryPrompt($ctx);
                })->as('summary'),
                CallbackStep::make(function ($ctx) {
                    return ['error' => 'Document quality too low for processing'];
                })->as('quality-failed')
            )

            // Step 5: Human approval for sensitive content
            ->approval('sensitivity-review', function ($ctx) {
                $entities = $ctx->get('extract-aspects')['entities'] ?? [];
                return "Review extracted entities for sensitive information:\n" . json_encode($entities);
            })

            // Step 6: Final output generation
            ->callback('generate-output', function ($ctx) {
                return [
                    'summary' => $ctx->get('summary')['content'] ?? null,
                    'entities' => $ctx->get('extract-aspects')['entities'] ?? [],
                    'key_phrases' => $ctx->get('extract-aspects')['key-phrases'] ?? [],
                    'sentiment' => $ctx->get('extract-aspects')['sentiment'] ?? null,
                    'analysis' => $ctx->get('analyze')['content'],
                    'processed_at' => now()->toISOString(),
                ];
            });
    }

    protected function checkQuality($ctx): bool
    {
        $text = $ctx->get('extract')['text'] ?? '';
        return strlen($text) > 100;
    }

    protected function buildSummaryPrompt($ctx): string
    {
        $text = $ctx->get('extract')['text'];
        $analysis = $ctx->get('analyze')['content'];

        return <<<PROMPT
Based on the following analysis:
{$analysis}

Create a comprehensive summary of this document:
{$text}
PROMPT;
    }

    public function forTeam(int|string|object $team): static
    {
        $this->team = is_object($team) ? $team : null;
        return $this;
    }
}
```

## Running Workflows

### Using WorkflowRunner

```php
use App\Workflows\DocumentProcessingWorkflow;
use AgenticOrchestrator\Workflows\WorkflowRunner;

$runner = app(WorkflowRunner::class);

// Run with workflow class
$result = $runner->run(DocumentProcessingWorkflow::class, [
    'document' => [
        'content' => 'Document text content...',
        'filename' => 'report.pdf',
    ],
]);

// Run with definition directly
$workflow = new DocumentProcessingWorkflow();
$result = $runner->run($workflow->definition(), ['document' => $doc]);

// Run with instance
$result = $runner->run($workflow, ['document' => $doc]);
```

### Checking Results

```php
if ($result->isSuccess()) {
    $output = $result->getOutput();
    $summary = $output['summary'];
} elseif ($result->isPaused()) {
    // Workflow is waiting for human approval
    $state = $result->getState();
    cache()->put("workflow:{$result->executionId}", $state, now()->addDay());
} elseif ($result->isFailed()) {
    $error = $result->error;
    $failedSteps = $result->getFailedSteps();
}
```

## Workflow Configuration

The workflow runner can be configured in `config/agent-orchestrator.php`:

```php
'workflows' => [
    'max_steps' => 50,           // Maximum steps per execution
    'step_timeout' => 300,       // Default step timeout (seconds)
    'persistence' => true,       // Enable state persistence
    'retention_days' => 30,      // Days to retain completed workflow states
],
```

## Best Practices

### 1. Keep Steps Focused

Each step should do one thing well:

```php
// Good: Single responsibility
$definition
    ->agent('extract', 'extractor', 'Extract data')
    ->callback('transform', fn($ctx) => $this->transform($ctx))
    ->agent('load', 'loader', 'Load into system');

// Avoid: Too much in one step
$definition->callback('do-everything', fn($ctx) => $this->extractTransformLoad($ctx));
```

### 2. Use Meaningful Names

Step names appear in logs and debugging:

```php
// Good: Descriptive names
->agent('classify-ticket-urgency', 'classifier', '...')
->agent('route-to-department', 'router', '...')

// Avoid: Generic names
->agent('step1', 'agent', '...')
->agent('step2', 'agent', '...')
```

### 3. Handle Failures Gracefully

Configure retries and provide fallback steps:

```php
$step = AgentStep::make('critical-agent', 'Important task')
    ->retry(3)
    ->timeout(60);

// Or use conditional for fallback
$definition->when(
    'with-fallback',
    fn($ctx) => $ctx->has('primary_result'),
    AgentStep::make('continue', 'Continue processing'),
    AgentStep::make('fallback', 'Use fallback approach')
);
```

### 4. Use Parallel Steps for Independence

When steps do not depend on each other, run them in parallel:

```php
// Good: Independent operations run concurrently
$definition->parallel('gather-data', [
    AgentStep::make('api-1', 'Fetch from API 1'),
    AgentStep::make('api-2', 'Fetch from API 2'),
    AgentStep::make('api-3', 'Fetch from API 3'),
]);

// Then combine results
$definition->callback('combine', function ($ctx) {
    $results = $ctx->get('gather-data');
    return array_merge(...array_values($results));
});
```

### 5. Document Complex Workflows

Add metadata and descriptions for maintainability:

```php
$definition
    ->name('Order Processing Pipeline v2')
    ->description('Validates, processes, and fulfills customer orders')
    ->metadata([
        'version' => '2.1.0',
        'owner' => 'fulfillment-team',
        'sla' => '5 minutes',
        'documentation' => 'https://wiki.example.com/workflows/order-processing',
    ]);
```

## Next Steps

- Learn about the different [step types](./steps.md) available
- Understand how to use [WorkflowContext](./context.md) for data sharing
- Explore [workflow patterns](./patterns.md) like Supervisor and MapReduce
