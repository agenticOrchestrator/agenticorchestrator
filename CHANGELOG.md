# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Laravel 13 support: widened the `illuminate/*` constraints to `^11.0|^12.0|^13.0`.
- Queue-based parallel execution driver: `WorkflowDefinition::parallelQueued()` fans branches out across queue workers via `Bus::batch()` for true concurrency, while `parallel()` keeps the in-process synchronous behavior. Pluggable via the new `ParallelDriverInterface` (`SyncParallelDriver`, `QueueParallelDriver`) and configurable under `workflows.parallel.*`.
- Core agent system with Agent, AgentResponse, AgentContext, and extensible tool architecture
- Multi-tenancy support with TenantManager, 6 resolver strategies, and team-scoped isolation
- Memory system with session, cache, database, vector, and RAG-backed drivers
- Workflow engine supporting sequential, parallel, conditional, loop, while, delay, sub-workflow, human approval, supervisor, map-reduce, and chain-of-thought patterns
- Streaming support with StreamResponse, StreamChunk, and StreamHandler
- Structured output via SchemaBuilder and StructuredResponse
- Embeddings and vector store integrations (OpenAI embeddings, PgVector, Pinecone, Qdrant, Weaviate, Chroma)
- RAG pipeline with document loaders (Markdown, JSON, directory), recursive character chunking, hybrid retrieval, and reranking
- HybridResponse system for combined LLM and RAG responses with source attribution
- Evaluation framework with LLM-as-judge, assertion-based testing, metrics, and test suites
- Usage tracking for tokens, cost, and latency with per-agent reporting
- Resilience layer with retry policies, circuit breaker, and provider fallback chains
- Rate limiting scoped per-agent, per-team, per-user, and per-token
- Caching for responses, embeddings, and tool results
- MCP (Model Context Protocol) client integration and tool provider
- Testing utilities: FakeAgent, FakeTool, FakeMemory, FakeWorkflow, FakeResponse, and AgentTestCase
- 10 Artisan console commands for agent, tool, and workflow management
- Event system for agent lifecycle hooks
- Comprehensive documentation (68 markdown files)
