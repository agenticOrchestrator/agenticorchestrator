<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\AgentManager;

describe('SyncSystemAgentsCommand', function () {
    it('warns when no system agents are configured', function () {
        config()->set('agent-orchestrator.multi_tenancy.system_agents', []);

        $manager = Mockery::mock(AgentManager::class);
        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:sync-system')
            ->expectsOutputToContain('No system agents configured')
            ->assertSuccessful();
    });

    it('registers system agents from configuration', function () {
        // Use a real existing class name for class_exists() check
        $agentClass = AgentManager::class;

        config()->set('agent-orchestrator.multi_tenancy.system_agents', [$agentClass]);

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('registerSystemAgent')
            ->with($agentClass)
            ->once();

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:sync-system')
            ->expectsOutputToContain('Syncing system agents...')
            ->expectsOutputToContain('Registered: AgentManager')
            ->expectsOutputToContain('System agents synced successfully')
            ->assertSuccessful();
    });

    it('shows dry-run information without making changes', function () {
        $agentClass = AgentManager::class;

        config()->set('agent-orchestrator.multi_tenancy.system_agents', [$agentClass]);

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldNotReceive('registerSystemAgent');

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:sync-system', ['--dry-run' => true])
            ->expectsOutputToContain('System agents that would be synced')
            ->expectsOutputToContain('AgentManager')
            ->assertSuccessful();
    });

    it('reports error for non-existent agent classes', function () {
        config()->set('agent-orchestrator.multi_tenancy.system_agents', [
            'App\\Agents\\NonExistentAgent',
        ]);

        $manager = Mockery::mock(AgentManager::class);
        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:sync-system')
            ->expectsOutputToContain('Agent class not found: App\\Agents\\NonExistentAgent')
            ->assertSuccessful();
    });

    it('processes mix of valid and invalid agent classes', function () {
        $validClass = AgentManager::class;

        config()->set('agent-orchestrator.multi_tenancy.system_agents', [
            $validClass,
            'App\\Agents\\MissingAgent',
        ]);

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('registerSystemAgent')
            ->with($validClass)
            ->once();

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:sync-system')
            ->expectsOutputToContain('Registered: AgentManager')
            ->expectsOutputToContain('Agent class not found: App\\Agents\\MissingAgent')
            ->assertSuccessful();
    });
});
