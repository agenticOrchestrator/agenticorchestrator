<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests;

use AgenticOrchestrator\AgenticOrchestratorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            AgenticOrchestratorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Configure test environment
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configure agent-orchestrator for testing
        config()->set('agent-orchestrator.default_provider', 'openai');
        config()->set('agent-orchestrator.memory.default', 'session');
        config()->set('agent-orchestrator.tracking.enabled', false);
    }
}
