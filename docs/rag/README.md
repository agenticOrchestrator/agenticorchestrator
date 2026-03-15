# RAG Pipeline Builder

The RAG (Retrieval-Augmented Generation) Pipeline Builder provides a fluent interface for ingesting documents, performing semantic retrieval, and injecting relevant context into LLM prompts.

## Table of Contents

- [Quick Start](#quick-start)
- [Core Concepts](#core-concepts)
- [Documentation](#documentation)
- [Configuration](#configuration)

## Quick Start

### Basic Usage

```php
use AgenticOrchestrator\Rag\RagPipeline;
use AgenticOrchestrator\Embeddings\Providers\OpenAIEmbeddings;
use AgenticOrchestrator\Embeddings\Stores\PgVectorStore;

// Create a pipeline
$pipeline = RagPipeline::make()
    ->embeddings(new OpenAIEmbeddings($apiKey))
    ->store(new PgVectorStore($connection))
    ->namespace('knowledge_base')
    ->chunkSize(1000)
    ->chunkOverlap(200);

// Ingest documents from a directory
$result = $pipeline->from('/path/to/docs')->ingest();
echo "Processed {$result->documentsProcessed} documents, created {$result->chunksCreated} chunks";

// Query for relevant context
$result = $pipeline->limit(5)->threshold(0.7)->query('How do I reset my password?');

if ($result->hasContext()) {
    echo $result->getContext();
}
```

### Agent Integration

```php
use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Agents\Concerns\HasRag;
use AgenticOrchestrator\Rag\Attributes\RagSource;

#[RagSource(namespace: 'customer_faq', limit: 5, threshold: 0.7)]
class CustomerSupportAgent extends Agent
{
    use HasRag;

    protected string $name = 'Customer Support';
    protected string $instructions = 'Help customers with their questions using the knowledge base.';
}

// Use the agent with RAG
$agent = CustomerSupportAgent::make()->withRag($pipeline);
$response = $agent->respond('How do I reset my password?');
// RAG context is automatically injected into the prompt
```

## Core Concepts

### Documents

Documents are the primary unit of content in the RAG pipeline. They contain text content and associated metadata.

```php
use AgenticOrchestrator\Rag\Document;

// Create from text
$doc = Document::fromText('Your content here', ['category' => 'faq']);

// Create from file
$doc = Document::fromFile('/path/to/file.txt', $content, ['author' => 'System']);
```

### Chunking

Large documents are split into smaller chunks for more precise retrieval. Two strategies are available:

- **FixedSizeChunker**: Splits by character count with overlap
- **RecursiveCharacterChunker**: Splits by semantic boundaries (paragraphs, sentences)

### Retrieval

Documents are embedded and stored in a vector database. Queries are also embedded and compared using similarity search.

### Multi-Tenancy

All operations support namespace scoping for multi-tenant applications:

```php
$pipeline->namespace('knowledge_base')->forTenant($tenantId);
```

## Documentation

| Document | Description |
|----------|-------------|
| [Pipeline](pipeline.md) | Complete pipeline API reference |
| [Loaders](loaders.md) | Document loading from various sources |
| [Chunking](chunking.md) | Text chunking strategies |
| [Retrieval](retrieval.md) | Retrievers and rerankers |
| [Agent Integration](agent-integration.md) | Using RAG with agents |
| [Hybrid Responses](../hybrid-responses/README.md) | Combining LLM and RAG responses |

## Configuration

Add to your `.env` file:

```env
AGENT_RAG_CHUNKER=recursive
AGENT_RAG_RETRIEVER=vector
AGENT_RAG_CHUNK_SIZE=1000
AGENT_RAG_CHUNK_OVERLAP=200
AGENT_RAG_RETRIEVE_LIMIT=5
AGENT_RAG_SCORE_THRESHOLD=0.7
```

Configuration in `config/agent-orchestrator.php`:

```php
'rag' => [
    'default_chunker' => env('AGENT_RAG_CHUNKER', 'recursive'),
    'default_retriever' => env('AGENT_RAG_RETRIEVER', 'vector'),
    'chunking' => [
        'size' => env('AGENT_RAG_CHUNK_SIZE', 1000),
        'overlap' => env('AGENT_RAG_CHUNK_OVERLAP', 200),
    ],
    'retrieval' => [
        'limit' => env('AGENT_RAG_RETRIEVE_LIMIT', 5),
        'threshold' => env('AGENT_RAG_SCORE_THRESHOLD', 0.7),
    ],
],
```

## Requirements

- PHP 8.2+
- An embedding provider (OpenAI, etc.)
- A vector store (PgVector, Qdrant, Chroma, etc.)
