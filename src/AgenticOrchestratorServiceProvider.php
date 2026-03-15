<?php

declare(strict_types=1);

namespace AgenticOrchestrator;

use AgenticOrchestrator\Agents\AgentManager;
use AgenticOrchestrator\Caching\EmbeddingCache;
use AgenticOrchestrator\Caching\ResponseCache;
use AgenticOrchestrator\Caching\ToolResultCache;
use AgenticOrchestrator\Console\Commands\ChatAgentCommand;
use AgenticOrchestrator\Console\Commands\EvaluateAgentCommand;
use AgenticOrchestrator\Console\Commands\ListAgentsCommand;
use AgenticOrchestrator\Console\Commands\ListToolsCommand;
use AgenticOrchestrator\Console\Commands\MakeAgentCommand;
use AgenticOrchestrator\Console\Commands\MakeEvaluationCommand;
use AgenticOrchestrator\Console\Commands\MakeToolCommand;
use AgenticOrchestrator\Console\Commands\MakeWorkflowCommand;
use AgenticOrchestrator\Console\Commands\RunAgentCommand;
use AgenticOrchestrator\Console\Commands\RunWorkflowCommand;
use AgenticOrchestrator\Console\Commands\SyncSystemAgentsCommand;
use AgenticOrchestrator\Memory\MemoryManager;
use AgenticOrchestrator\MultiTenancy\TenantManager;
use AgenticOrchestrator\Providers\ProviderManager;
use AgenticOrchestrator\RateLimiting\AgentRateLimiter;
use AgenticOrchestrator\RateLimiting\TeamRateLimiter;
use AgenticOrchestrator\RateLimiting\TokenRateLimiter;
use AgenticOrchestrator\RateLimiting\UserRateLimiter;
use AgenticOrchestrator\Resilience\ProviderFallbackChain;
use AgenticOrchestrator\Resilience\RetryStrategy;
use AgenticOrchestrator\Tools\ToolRegistry;
use AgenticOrchestrator\Tracking\CostCalculator;
use AgenticOrchestrator\Tracking\UsageTracker;
use AgenticOrchestrator\Workflows\WorkflowRunner;
use Illuminate\Support\ServiceProvider;

class AgenticOrchestratorServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/agent-orchestrator.php',
            'agent-orchestrator'
        );

        $this->registerManagers();
        $this->registerAliases();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishConfig();
        $this->publishMigrations();
        $this->publishStubs();
        $this->registerCommands();
        $this->registerRoutes();
        $this->registerMultiTenancy();
    }

    /**
     * Register manager singletons.
     */
    protected function registerManagers(): void
    {
        // Tenant Manager - Multi-tenancy resolution
        $this->app->singleton(TenantManager::class, function ($app) {
            return new TenantManager(
                $app,
                config('agent-orchestrator.multi_tenancy', []),
            );
        });

        // Agent Manager - Registry and factory for agents
        $this->app->singleton(AgentManager::class, function ($app) {
            return new AgentManager($app);
        });

        // Provider Manager - LLM provider abstraction
        $this->app->singleton(ProviderManager::class, function ($app) {
            return new ProviderManager(
                config('agent-orchestrator.providers', []),
                config('agent-orchestrator.default_provider', 'openai'),
            );
        });

        // Memory Manager - Memory driver abstraction
        $this->app->singleton(MemoryManager::class, function ($app) {
            return new MemoryManager(
                $app,
                config('agent-orchestrator.memory', []),
            );
        });

        // Tool Registry - Tool discovery and registration
        $this->app->singleton(ToolRegistry::class, function ($app) {
            return new ToolRegistry($app);
        });

        // Workflow Runner - Workflow execution engine
        $this->app->singleton(WorkflowRunner::class, function ($app) {
            return new WorkflowRunner(
                $app,
                config('agent-orchestrator.workflows', []),
            );
        });

        // Cost Calculator - Pricing calculations
        $this->app->singleton(CostCalculator::class, function ($app) {
            return new CostCalculator(
                config('agent-orchestrator.pricing', []),
            );
        });

        // Usage Tracker - Token/cost tracking
        $this->app->singleton(UsageTracker::class, function ($app) {
            return new UsageTracker(
                $app->make(CostCalculator::class),
            );
        });

        // Retry Strategy - Configurable retry logic
        $this->app->singleton(RetryStrategy::class, function ($app) {
            return new RetryStrategy(
                config('agent-orchestrator.error_handling.retry', []),
            );
        });

        // Provider Fallback Chain - Provider fallback management
        $this->app->singleton(ProviderFallbackChain::class, function ($app) {
            $fallbacks = config('agent-orchestrator.error_handling.fallbacks', []);
            $chain = new ProviderFallbackChain;

            foreach ($fallbacks as $provider => $fallback) {
                if ($provider !== 'default' && isset($fallback['provider'], $fallback['model'])) {
                    $chain->addProvider($fallback['provider'], $fallback['model']);
                }
            }

            if (config('agent-orchestrator.error_handling.circuit_breaker.enabled', true)) {
                $chain->withCircuitBreaker(
                    config('agent-orchestrator.error_handling.circuit_breaker', [])
                );
            }

            return $chain;
        });

        // Rate Limiters
        $this->app->singleton(AgentRateLimiter::class, function ($app) {
            $config = config('agent-orchestrator.rate_limiting.per_agent', []);

            return (new AgentRateLimiter)
                ->maxRequests($config['requests'] ?? 500)
                ->windowSeconds($config['period'] ?? 60);
        });

        $this->app->singleton(TeamRateLimiter::class, function ($app) {
            $config = config('agent-orchestrator.rate_limiting.per_team', []);

            return (new TeamRateLimiter)
                ->maxRequests($config['requests'] ?? 1000)
                ->windowSeconds($config['period'] ?? 60);
        });

        $this->app->singleton(UserRateLimiter::class, function ($app) {
            $config = config('agent-orchestrator.rate_limiting.per_user', []);

            return (new UserRateLimiter)
                ->maxRequests($config['requests'] ?? 100)
                ->windowSeconds($config['period'] ?? 60);
        });

        $this->app->singleton(TokenRateLimiter::class, function ($app) {
            $config = config('agent-orchestrator.rate_limiting.tokens', []);

            return (new TokenRateLimiter)
                ->maxTokens($config['per_user'] ?? 100000)
                ->windowSeconds(60);
        });

        // Caching Services
        $this->app->singleton(ResponseCache::class, function ($app) {
            return new ResponseCache(
                config('agent-orchestrator.caching.responses', [])
            );
        });

        $this->app->singleton(EmbeddingCache::class, function ($app) {
            return new EmbeddingCache(
                config('agent-orchestrator.caching.embeddings', [])
            );
        });

        $this->app->singleton(ToolResultCache::class, function ($app) {
            return new ToolResultCache(
                config('agent-orchestrator.caching.tools', [])
            );
        });
    }

    /**
     * Register facade aliases.
     */
    protected function registerAliases(): void
    {
        $this->app->alias(AgentManager::class, 'agent-orchestrator');
        $this->app->alias(MemoryManager::class, 'agent-memory');
        $this->app->alias(WorkflowRunner::class, 'agent-workflow');
        $this->app->alias(TenantManager::class, 'agent-tenancy');
    }

    /**
     * Publish configuration file.
     */
    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__.'/../config/agent-orchestrator.php' => config_path('agent-orchestrator.php'),
        ], 'agent-orchestrator-config');
    }

    /**
     * Publish database migrations.
     */
    protected function publishMigrations(): void
    {
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'agent-orchestrator-migrations');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Publish stub files for artisan commands.
     */
    protected function publishStubs(): void
    {
        $this->publishes([
            __DIR__.'/../resources/stubs' => base_path('stubs/agent-orchestrator'),
        ], 'agent-orchestrator-stubs');
    }

    /**
     * Register artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeAgentCommand::class,
                MakeToolCommand::class,
                MakeWorkflowCommand::class,
                MakeEvaluationCommand::class,
                ListAgentsCommand::class,
                ListToolsCommand::class,
                RunAgentCommand::class,
                ChatAgentCommand::class,
                EvaluateAgentCommand::class,
                RunWorkflowCommand::class,
                SyncSystemAgentsCommand::class,
            ]);
        }
    }

    /**
     * Register API routes if enabled.
     */
    protected function registerRoutes(): void
    {
        if (config('agent-orchestrator.routes.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }
    }

    /**
     * Register multi-tenancy support.
     */
    protected function registerMultiTenancy(): void
    {
        if (! config('agent-orchestrator.multi_tenancy.enabled', true)) {
            return;
        }

        // Register system agents from config
        $systemAgents = config('agent-orchestrator.multi_tenancy.system_agents', []);

        $this->app->booted(function () use ($systemAgents) {
            $manager = $this->app->make(AgentManager::class);

            foreach ($systemAgents as $agentClass) {
                $manager->registerSystemAgent($agentClass);
            }
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            TenantManager::class,
            AgentManager::class,
            ProviderManager::class,
            MemoryManager::class,
            ToolRegistry::class,
            WorkflowRunner::class,
            CostCalculator::class,
            UsageTracker::class,
            RetryStrategy::class,
            ProviderFallbackChain::class,
            AgentRateLimiter::class,
            TeamRateLimiter::class,
            UserRateLimiter::class,
            TokenRateLimiter::class,
            ResponseCache::class,
            EmbeddingCache::class,
            ToolResultCache::class,
            'agent-orchestrator',
            'agent-memory',
            'agent-workflow',
            'agent-tenancy',
        ];
    }
}
