# Workflows

Workflows in Agent Orchestrator enable you to coordinate multiple agents and steps to accomplish complex, multi-step tasks. They provide a structured way to orchestrate AI agents with support for sequential and parallel execution, conditional branching, human-in-the-loop approval, and state persistence.

## Overview

A workflow is a series of steps that execute in a defined order. Each step can perform actions like invoking an AI agent, transforming data, waiting for human approval, or delegating to sub-workflows.

```
                    +------------------+
                    |  Workflow Start  |
                    +--------+---------+
                             |
                    +--------v---------+
                    |   Agent Step 1   |
                    |  (Research Task) |
                    +--------+---------+
                             |
              +--------------+--------------+
              |                             |
     +--------v---------+         +---------v--------+
     |  Parallel Step   |         |  Parallel Step   |
     | (Agent: Writer)  |         | (Agent: Analyst) |
     +--------+---------+         +---------+--------+
              |                             |
              +--------------+--------------+
                             |
                    +--------v---------+
                    |  Conditional Step |
                    | (Quality Check)   |
                    +--------+---------+
                             |
                   +---------+---------+
                   |                   |
          +--------v-------+  +--------v--------+
          |  Pass: Publish |  |  Fail: Revise   |
          +----------------+  +-----------------+
```

## Key Concepts

### WorkflowDefinition

The `WorkflowDefinition` class provides a fluent API for defining workflow steps. It supports various step types and execution patterns.

```php
use AgenticOrchestrator\Workflows\WorkflowDefinition;

$definition = WorkflowDefinition::create()
    ->name('Content Creation Pipeline')
    ->description('Creates and reviews content using multiple agents')
    ->agent('research', 'research-agent', 'Research the topic: {topic}')
    ->agent('write', 'writer-agent', fn($ctx) => "Write about: " . $ctx->get('research')['content'])
    ->approval('review', 'Review the generated content before publishing');
```

### WorkflowRunner

The `WorkflowRunner` executes workflow definitions. It handles step execution, state management, event dispatching, and workflow resumption.

```php
use AgenticOrchestrator\Workflows\WorkflowRunner;

$runner = app(WorkflowRunner::class);

$result = $runner->run($definition, [
    'topic' => 'Introduction to Machine Learning',
]);
```

### WorkflowContext

The `WorkflowContext` is a shared state container that holds input data, step outputs, and execution metadata. Steps read from and write to the context to share data.

### StepResult

Each step returns a `StepResult` that indicates the execution outcome. Results can be success, failure, skipped, pending, or waiting (for human approval).

## When to Use Workflows

Workflows are ideal for scenarios requiring:

| Scenario | Description |
|----------|-------------|
| **Multi-Agent Coordination** | When multiple specialized agents need to collaborate on a task |
| **Complex Processing Pipelines** | When data must flow through multiple processing stages |
| **Human Oversight** | When critical decisions require human review and approval |
| **Long-Running Tasks** | When tasks may be interrupted and need to resume later |
| **Conditional Logic** | When different paths should execute based on intermediate results |
| **Parallel Processing** | When independent subtasks can execute concurrently |

## Workflow Types

### Simple Sequential Workflow

Execute steps one after another in order.

```php
$definition = WorkflowDefinition::create()
    ->agent('step1', 'agent-a', 'First task')
    ->agent('step2', 'agent-b', 'Second task')
    ->agent('step3', 'agent-c', 'Third task');
```

### Parallel Workflow

Execute multiple steps concurrently and collect results.

```php
$definition = WorkflowDefinition::create()
    ->parallel('gather-data', [
        AgentStep::make('data-agent', 'Fetch from source A'),
        AgentStep::make('data-agent', 'Fetch from source B'),
        AgentStep::make('data-agent', 'Fetch from source C'),
    ])
    ->agent('combine', 'combine-agent', 'Merge all data sources');
```

### Conditional Workflow

Branch execution based on conditions.

```php
$definition = WorkflowDefinition::create()
    ->agent('analyze', 'analyzer', 'Analyze the input')
    ->when(
        'quality-gate',
        fn($ctx) => $ctx->get('analyze')['score'] >= 0.8,
        AgentStep::make('publisher', 'Publish the content'),
        AgentStep::make('reviser', 'Improve the content')
    );
```

### Human-in-the-Loop Workflow

Pause for human review and approval.

```php
$definition = WorkflowDefinition::create()
    ->agent('draft', 'writer', 'Write the initial draft')
    ->approval('human-review', 'Please review the draft for accuracy')
    ->agent('finalize', 'editor', 'Apply final edits and publish');
```

### Supervisor Pattern

A supervisor agent orchestrates worker agents dynamically.

```php
$definition = WorkflowDefinition::create()
    ->supervisor('orchestrator', 'supervisor-agent', [
        'researcher' => 'research-agent',
        'writer' => 'writer-agent',
        'reviewer' => 'reviewer-agent',
    ]);
```

## Quick Example

Here is a complete example of a content creation workflow:

```php
<?php

namespace App\Workflows;

use AgenticOrchestrator\Contracts\WorkflowInterface;
use AgenticOrchestrator\Workflows\WorkflowDefinition;
use AgenticOrchestrator\Workflows\Steps\AgentStep;
use AgenticOrchestrator\Workflows\Steps\CallbackStep;
use AgenticOrchestrator\Workflows\Steps\HumanApprovalStep;

class ContentCreationWorkflow implements WorkflowInterface
{
    public function definition(): WorkflowDefinition
    {
        return WorkflowDefinition::create()
            ->name('Content Creation')
            ->description('Creates, reviews, and publishes content')

            // Step 1: Research the topic
            ->agent('research', 'research-agent', function ($ctx) {
                return "Research the following topic thoroughly: {$ctx->get('topic')}";
            })

            // Step 2: Generate content based on research
            ->agent('write', 'writer-agent', function ($ctx) {
                $research = $ctx->get('research')['content'];
                return "Write a comprehensive article based on this research:\n\n{$research}";
            })

            // Step 3: Quality check callback
            ->callback('quality-check', function ($ctx) {
                $content = $ctx->get('write')['content'];
                $wordCount = str_word_count($content);

                return [
                    'word_count' => $wordCount,
                    'passes_minimum' => $wordCount >= 500,
                ];
            })

            // Step 4: Human approval for publishing
            ->approval('editor-review', function ($ctx) {
                $content = $ctx->get('write')['content'];
                $quality = $ctx->get('quality-check');

                return "Please review this article ({$quality['word_count']} words):\n\n{$content}";
            })

            // Step 5: Publish the content
            ->callback('publish', function ($ctx) {
                $content = $ctx->get('write')['content'];

                // Save to database, publish to CMS, etc.
                return ['published' => true, 'url' => '/articles/new-article'];
            });
    }

    public function forTeam(int|string|object $team): static
    {
        // Workflows can store team for scoping agents and resources
        return $this;
    }
}
```

Running the workflow:

```php
use App\Workflows\ContentCreationWorkflow;
use AgenticOrchestrator\Workflows\WorkflowRunner;

$runner = app(WorkflowRunner::class);

$result = $runner->run(ContentCreationWorkflow::class, [
    'topic' => 'The Future of AI in Healthcare',
]);

if ($result->isSuccess()) {
    $publishedUrl = $result->get('publish')['url'];
    echo "Content published at: {$publishedUrl}";
} elseif ($result->isPaused()) {
    // Workflow is waiting for human approval
    $state = $result->getState();
    // Store state for later resumption
}
```

## Documentation Structure

This workflows documentation section covers:

| Document | Description |
|----------|-------------|
| [Creating Workflows](./creating-workflows.md) | Implementing the WorkflowInterface and defining steps |
| [Steps](./steps.md) | All available step types and their configuration |
| [Context](./context.md) | Working with WorkflowContext for data sharing |
| [Results](./results.md) | Understanding WorkflowResult and StepResult |
| [Patterns](./patterns.md) | Supervisor, Parallel, and orchestration patterns |

## Next Steps

- Learn how to [create workflows](./creating-workflows.md) by implementing the WorkflowInterface
- Explore the different [step types](./steps.md) available for building workflows
- Understand how to pass data between steps using [WorkflowContext](./context.md)
