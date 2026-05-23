# Workflow Patterns

Common patterns for building effective AI workflows using the available step types and pattern classes.

## Sequential Pipeline

Process data through a series of steps using `WorkflowDefinition`:

```php
use AgenticOrchestrator\Workflows\WorkflowDefinition;

$definition = WorkflowDefinition::create()
    ->name('Document Processing Pipeline')
    ->agent('extract', 'extractor-agent', fn($ctx) => "Extract data from: {$ctx->get('document')}")
    ->agent('validate', 'validator-agent', fn($ctx) => "Validate: " . json_encode($ctx->get('extract')))
    ->agent('transform', 'transformer-agent', fn($ctx) => "Transform: " . json_encode($ctx->get('validate')));
```

Or using `SequentialPattern` directly:

```php
use AgenticOrchestrator\Workflows\Patterns\SequentialPattern;
use AgenticOrchestrator\Workflows\Steps\AgentStep;

$pattern = SequentialPattern::make([
    AgentStep::make('extractor-agent', fn($ctx) => "Extract: {$ctx->get('document')}")->as('extract'),
    AgentStep::make('validator-agent', fn($ctx) => "Validate: " . json_encode($ctx->get('extract')))->as('validate'),
    AgentStep::make('transformer-agent', fn($ctx) => "Transform: " . json_encode($ctx->get('validate')))->as('transform'),
]);
```

## Parallel Execution (Fan-Out / Fan-In)

> **Execution model.** `parallel()` uses the **synchronous driver** by default:
> branches run in-process, one after another, but with parallel semantics
> (independent results, race mode, failure thresholds, result merging). For real
> concurrency across queue workers, use
> [`parallelQueued()`](#queued-execution-true-concurrency).

Process multiple items in parallel, then aggregate:

```php
use AgenticOrchestrator\Workflows\WorkflowDefinition;
use AgenticOrchestrator\Workflows\Steps\AgentStep;

$definition = WorkflowDefinition::create()
    ->name('Batch Analysis')
    // Fan-out: process items in parallel
    ->parallel('analyze-all', [
        AgentStep::make('analyzer-agent', fn($ctx) => "Analyze document 1: {$ctx->get('doc1')}")->as('analysis-1'),
        AgentStep::make('analyzer-agent', fn($ctx) => "Analyze document 2: {$ctx->get('doc2')}")->as('analysis-2'),
        AgentStep::make('analyzer-agent', fn($ctx) => "Analyze document 3: {$ctx->get('doc3')}")->as('analysis-3'),
    ])
    // Fan-in: aggregate results
    ->agent('aggregate', 'aggregator-agent', fn($ctx) =>
        "Summarize analyses: " . json_encode($ctx->get('analyze-all'))
    );
```

Using `ParallelPattern` directly:

```php
use AgenticOrchestrator\Workflows\Patterns\ParallelPattern;
use AgenticOrchestrator\Workflows\Steps\AgentStep;

$pattern = ParallelPattern::make([
    AgentStep::make('api-agent', 'Fetch from API 1')->as('api-1'),
    AgentStep::make('api-agent', 'Fetch from API 2')->as('api-2'),
    AgentStep::make('api-agent', 'Fetch from API 3')->as('api-3'),
])
    ->as('gather-data')
    ->allowFailures(1)   // Continue if up to 1 branch fails
    ->maxConcurrency(3); // Concurrency hint (not enforced by the sync driver yet)
```

### Race Mode

Execute steps in parallel but return as soon as the first one succeeds:

```php
$pattern = ParallelPattern::make([
    AgentStep::make('fast-agent', 'Try fast approach')->as('fast'),
    AgentStep::make('reliable-agent', 'Try reliable approach')->as('reliable'),
])
    ->race();  // Return first successful result
```

### Queued Execution (True Concurrency)

`parallelQueued()` dispatches each branch as a queued job inside a
`Bus::batch()`. The branches run concurrently on your queue workers; the driver
waits for the batch to finish and then merges the results. The pattern API is
identical to `parallel()` — only the execution driver behind it changes.

```php
use AgenticOrchestrator\Workflows\WorkflowDefinition;
use AgenticOrchestrator\Workflows\Steps\AgentStep;

$definition = WorkflowDefinition::create()
    ->name('Batch Analysis')
    ->parallelQueued('analyze-all', [
        AgentStep::make('analyzer-agent', 'Analyze segment A')->as('a'),
        AgentStep::make('analyzer-agent', 'Analyze segment B')->as('b'),
        AgentStep::make('analyzer-agent', 'Analyze segment C')->as('c'),
    ])
    ->agent('aggregate', 'aggregator-agent', fn ($ctx) =>
        'Summarize: ' . json_encode($ctx->get('analyze-all'))
    );
```

Connection, queue, timeout, and poll interval default to the
`agent-orchestrator.workflows.parallel.*` config and can be overridden per call:

```php
->parallelQueued('analyze-all', $branches, connection: 'redis', queue: 'agents')
```

Because agent work is mostly spent waiting on LLM responses, fanning branches
out across workers turns a sequence of API calls into roughly the cost of the
slowest one.

**Limitations of this first version:**

- **Serializable branches only.** Branch steps are serialized onto the queue, so
  they cannot hold closures. Use invokable `Step` subclasses or `AgentStep` with
  a *registered agent name* (a string), not a closure message/agent instance.
- **The orchestrating process blocks** while the batch runs. If the workflow
  itself is queued, run branch jobs on a *different* worker pool so they don't
  starve the queue. A non-blocking pause/resume variant is planned.
- **Tenant/user scope is not re-applied** inside branch jobs yet.
- Requires Laravel's batch table (`job_batches`) and a configured cache store.

## Human-in-the-Loop

Pause for human approval:

```php
use AgenticOrchestrator\Workflows\WorkflowDefinition;
use AgenticOrchestrator\Workflows\Steps\AgentStep;
use AgenticOrchestrator\Workflows\Steps\HumanApprovalStep;

$definition = WorkflowDefinition::create()
    ->name('Expense Approval')
    ->agent('categorize', 'expense-categorizer', fn($ctx) =>
        "Categorize expense: " . json_encode($ctx->get('expense'))
    )
    ->when(
        'check-threshold',
        fn($ctx) => $ctx->get('expense')['amount'] > 1000,
        HumanApprovalStep::make(fn($ctx) =>
            "Please review expense of \${$ctx->get('expense')['amount']}"
        )
            ->as('require-approval')
            ->allowActions(['approve', 'reject', 'request-info'])
            ->timeoutAfter(hours: 24),
        AgentStep::make('auto-approver', 'Auto-approve this small expense')->as('auto-approve')
    )
    ->callback('process-result', function ($ctx) {
        return [
            'expense_id' => $ctx->get('expense')['id'],
            'status' => $ctx->has('require-approval') ? $ctx->get('approval_decision') : 'approved',
        ];
    });
```

## Conditional Branching

Execute different paths based on conditions:

```php
use AgenticOrchestrator\Workflows\WorkflowDefinition;
use AgenticOrchestrator\Workflows\Steps\AgentStep;
use AgenticOrchestrator\Workflows\Steps\ConditionalStep;

$definition = WorkflowDefinition::create()
    ->name('Customer Service Router')
    ->agent('classify-intent', 'intent-classifier', fn($ctx) =>
        "Classify: {$ctx->get('message')}"
    )
    ->when(
        'route-billing',
        fn($ctx) => ($ctx->get('classify-intent')['intent'] ?? '') === 'billing',
        AgentStep::make('billing-agent', fn($ctx) =>
            "Handle billing inquiry: {$ctx->get('message')}"
        )->as('handle-billing')
    )
    ->when(
        'route-technical',
        fn($ctx) => ($ctx->get('classify-intent')['intent'] ?? '') === 'technical',
        AgentStep::make('technical-agent', fn($ctx) =>
            "Handle technical issue: {$ctx->get('message')}"
        )->as('handle-technical')
    )
    ->when(
        'route-general',
        fn($ctx) => !in_array($ctx->get('classify-intent')['intent'] ?? '', ['billing', 'technical']),
        AgentStep::make('general-agent', fn($ctx) =>
            "Handle general inquiry: {$ctx->get('message')}"
        )->as('handle-general')
    );
```

## Supervisor Pattern

A supervisor agent dynamically orchestrates worker agents:

```php
use AgenticOrchestrator\Workflows\WorkflowDefinition;

$definition = WorkflowDefinition::create()
    ->name('Research Orchestrator')
    ->supervisor('orchestrator', 'supervisor-agent', [
        'researcher' => 'research-agent',
        'writer' => 'writer-agent',
        'editor' => 'editor-agent',
        'fact-checker' => 'fact-check-agent',
    ]);
```

Using `SupervisorPattern` directly with more control:

```php
use AgenticOrchestrator\Workflows\Patterns\SupervisorPattern;

$pattern = SupervisorPattern::make('supervisor-agent', [
    'researcher' => 'research-agent',
    'writer' => 'writer-agent',
    'editor' => 'editor-agent',
])
    ->as('research-coordinator')
    ->maxRounds(5)  // Maximum delegation rounds
    ->allowParallel();  // Allow supervisor to delegate to multiple workers at once
```

The supervisor agent receives descriptions of available workers and decides which to invoke based on the current task state.

## Multi-Agent Collaboration

Coordinate multiple specialized agents:

```php
use AgenticOrchestrator\Workflows\WorkflowDefinition;
use AgenticOrchestrator\Workflows\Steps\AgentStep;

$definition = WorkflowDefinition::create()
    ->name('Research Workflow')
    // Gather information from multiple sources in parallel
    ->parallel('gather-info', [
        AgentStep::make('search-agent', fn($ctx) =>
            "Search web for: {$ctx->get('topic')}"
        )->as('web-search'),
        AgentStep::make('document-agent', fn($ctx) =>
            "Search documents for: {$ctx->get('topic')}"
        )->as('doc-search'),
        AgentStep::make('data-agent', fn($ctx) =>
            "Query data sources for: {$ctx->get('topic')}"
        )->as('data-search'),
    ])
    // Synthesize findings
    ->agent('synthesize', 'synthesizer-agent', fn($ctx) =>
        "Synthesize: " . json_encode($ctx->get('gather-info'))
    )
    // Critical review
    ->agent('review', 'critic-agent', fn($ctx) =>
        "Review: " . json_encode($ctx->get('synthesize'))
    )
    // Final report
    ->agent('report', 'reporter-agent', fn($ctx) =>
        "Create report from: " . json_encode([
            'synthesis' => $ctx->get('synthesize'),
            'review' => $ctx->get('review'),
        ])
    );
```

## Retry with Fallback

Handle failures with conditional fallback:

```php
use AgenticOrchestrator\Workflows\WorkflowDefinition;
use AgenticOrchestrator\Workflows\Steps\AgentStep;

$definition = WorkflowDefinition::create()
    ->name('Resilient API Workflow')
    ->callback('call-primary', function ($ctx) {
        try {
            $response = Http::timeout(10)->get('https://primary-api.example.com', $ctx->get('request'));
            return ['source' => 'primary', 'data' => $response->json()];
        } catch (\Exception $e) {
            $ctx->set('primary_failed', true);
            return ['error' => $e->getMessage()];
        }
    })
    ->when(
        'fallback-check',
        fn($ctx) => $ctx->get('primary_failed', false),
        AgentStep::make('fallback-agent', fn($ctx) =>
            "Generate fallback response for: " . json_encode($ctx->get('request'))
        )->as('fallback-response')
    );
```

## Iterative Refinement

Loop through improvement cycles using callbacks:

```php
use AgenticOrchestrator\Workflows\WorkflowDefinition;
use AgenticOrchestrator\Workflows\Steps\AgentStep;

$definition = WorkflowDefinition::create()
    ->name('Content Refinement')
    ->agent('generate-draft', 'writer-agent', fn($ctx) =>
        "Write about: {$ctx->get('topic')}"
    )
    ->callback('refine-loop', function ($ctx) {
        $draft = $ctx->get('generate-draft');
        $maxIterations = 3;

        for ($i = 0; $i < $maxIterations; $i++) {
            // Evaluate quality (simplified - you might use an agent)
            $qualityScore = strlen($draft['content'] ?? '') > 500 ? 0.9 : 0.5;

            if ($qualityScore >= 0.8) {
                return ['final_content' => $draft, 'iterations' => $i + 1, 'score' => $qualityScore];
            }

            // In practice, you would call an improvement agent here
            $draft['content'] .= "\n[Iteration {$i}: Content improved]";
        }

        return ['final_content' => $draft, 'iterations' => $maxIterations, 'score' => 'needs_review'];
    });
```

## Best Practices

1. **Keep steps atomic** - Each step should complete independently
2. **Use meaningful names** - Names appear in logs and debugging
3. **Handle all branches** - Do not leave conditional paths undefined
4. **Plan for failures** - Configure retries and provide fallback steps
5. **Test in isolation** - Each pattern should be testable independently
6. **Monitor execution** - Use events to track workflow progress
7. **Document decisions** - Add comments for complex conditional logic
8. **Use appropriate patterns** - Choose `ParallelPattern` for independent work, `SequentialPattern` for dependent steps, `SupervisorPattern` for dynamic orchestration
