<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\AgentManager;
use AgenticOrchestrator\Console\Commands\ListAgentsCommand;
use Illuminate\Support\Collection;

describe('ListAgentsCommand', function () {
    it('displays message when no agents are registered', function () {
        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('all')->once()->andReturn(new Collection([]));

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:list')
            ->expectsOutputToContain('No agents registered')
            ->assertSuccessful();
    });

    it('displays agents in a table', function () {
        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('all')->once()->andReturn(new Collection([
            'my-agent' => 'App\\Agents\\MyAgent',
            'system-helper' => 'App\\Agents\\SystemHelper',
        ]));

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:list')
            ->expectsOutputToContain('my-agent')
            ->expectsOutputToContain('system-helper')
            ->expectsOutputToContain('Total: 2 agent(s)')
            ->assertSuccessful();
    });

    it('outputs JSON when json option is provided', function () {
        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('all')->once()->andReturn(new Collection([
            'my-agent' => 'App\\Agents\\MyAgent',
        ]));

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:list', ['--json' => true])
            ->expectsOutputToContain('"my-agent"')
            ->assertSuccessful();
    });

    it('filters by system agents only', function () {
        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('systemAgents')->once()->andReturn(new Collection([
            'system-agent' => 'App\\Agents\\SystemAgent',
        ]));

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:list', ['--system' => true])
            ->expectsOutputToContain('system-agent')
            ->expectsOutputToContain('Total: 1 agent(s)')
            ->assertSuccessful();
    });

    it('filters by custom agents only', function () {
        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('all')->once()->andReturn(new Collection([
            'custom-agent' => 'App\\Agents\\CustomAgent',
            'system-bot' => 'App\\Agents\\SystemBot',
        ]));
        $manager->shouldReceive('systemAgents')->once()->andReturn(new Collection([
            'system-bot' => 'App\\Agents\\SystemBot',
        ]));

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:list', ['--custom' => true])
            ->expectsOutputToContain('custom-agent')
            ->expectsOutputToContain('Total: 1 agent(s)')
            ->assertSuccessful();
    });

    it('filters by team ID', function () {
        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('forTeam')
            ->with(7)
            ->once()
            ->andReturn(new Collection([
                'team-agent' => 'App\\Agents\\TeamAgent',
            ]));

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:list', ['--team' => '7'])
            ->expectsOutputToContain('team-agent')
            ->assertSuccessful();
    });

    it('filters by team ID and custom only', function () {
        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('customAgentsForTeam')
            ->with(3)
            ->once()
            ->andReturn(new Collection([
                'custom-team-agent' => 'App\\Agents\\CustomTeamAgent',
            ]));

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:list', ['--team' => '3', '--custom' => true])
            ->expectsOutputToContain('custom-team-agent')
            ->assertSuccessful();
    });

    it('truncates long class names in table display', function () {
        $longClass = 'App\\VeryLongNamespace\\EvenLongerSubNamespace\\SomeExtremelyVerboseAgentClassName';

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('all')->once()->andReturn(new Collection([
            'long-agent' => $longClass,
        ]));

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:list')
            ->expectsOutputToContain('...')
            ->assertSuccessful();
    });

    it('identifies system agents by name heuristic', function () {
        $command = new ListAgentsCommand;

        $reflection = new ReflectionMethod($command, 'isSystemAgent');

        expect($reflection->invoke($command, 'system-helper'))->toBeTrue()
            ->and($reflection->invoke($command, 'my-system-agent'))->toBeTrue()
            ->and($reflection->invoke($command, 'custom-agent'))->toBeFalse();
    });
});
