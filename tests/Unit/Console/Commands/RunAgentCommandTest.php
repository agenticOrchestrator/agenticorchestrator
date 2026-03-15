<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\AgentManager;
use AgenticOrchestrator\Agents\AgentResponse;
use AgenticOrchestrator\Contracts\AgentInterface;

describe('RunAgentCommand', function () {
    it('runs an agent with a message and displays response', function () {
        $response = new AgentResponse(
            content: 'Hello from the agent!',
            usage: ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30],
        );

        $agent = Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('respond')
            ->with('Hello agent')
            ->once()
            ->andReturn($response);

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('make')
            ->with('test-agent')
            ->once()
            ->andReturn($agent);

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:run', ['agent' => 'test-agent', 'message' => 'Hello agent'])
            ->expectsOutputToContain('Running agent: test-agent')
            ->expectsOutputToContain('Hello from the agent!')
            ->expectsOutputToContain('Tokens used: 30')
            ->assertSuccessful();
    });

    it('runs an agent scoped to a team', function () {
        $response = new AgentResponse(content: 'Team response');

        $agent = Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('respond')
            ->with('Test message')
            ->once()
            ->andReturn($response);

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('makeForTeam')
            ->with('test-agent', '42')
            ->once()
            ->andReturn($agent);

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:run', [
            'agent' => 'test-agent',
            'message' => 'Test message',
            '--team' => '42',
        ])->assertSuccessful();
    });

    it('runs an agent scoped to a user', function () {
        $response = new AgentResponse(content: 'User response');

        $agent = Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('forUser')
            ->with('user-99')
            ->once()
            ->andReturnSelf();
        $agent->shouldReceive('respond')
            ->with('Test message')
            ->once()
            ->andReturn($response);

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('make')
            ->with('test-agent')
            ->once()
            ->andReturn($agent);

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:run', [
            'agent' => 'test-agent',
            'message' => 'Test message',
            '--user' => 'user-99',
        ])->assertSuccessful();
    });

    it('displays tool call count when response has tool calls', function () {
        $response = new AgentResponse(
            content: 'Done with tools',
            toolCalls: [
                ['id' => 't1', 'name' => 'search', 'arguments' => [], 'result' => 'ok'],
                ['id' => 't2', 'name' => 'calc', 'arguments' => [], 'result' => '42'],
            ],
            usage: ['prompt_tokens' => 5, 'completion_tokens' => 10, 'total_tokens' => 15],
        );

        $agent = Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('respond')->once()->andReturn($response);

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('make')->once()->andReturn($agent);

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:run', ['agent' => 'my-agent', 'message' => 'do stuff'])
            ->expectsOutputToContain('Tool calls made: 2')
            ->assertSuccessful();
    });

    it('returns failure on exception', function () {
        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('make')
            ->with('bad-agent')
            ->once()
            ->andThrow(new RuntimeException('Agent not found'));

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:run', ['agent' => 'bad-agent', 'message' => 'test'])
            ->expectsOutputToContain('Error: Agent not found')
            ->assertFailed();
    });

    it('applies both team and user scoping', function () {
        $response = new AgentResponse(content: 'Scoped response');

        $agent = Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('forUser')
            ->with('u1')
            ->once()
            ->andReturnSelf();
        $agent->shouldReceive('respond')
            ->once()
            ->andReturn($response);

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('makeForTeam')
            ->with('scoped-agent', '5')
            ->once()
            ->andReturn($agent);

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:run', [
            'agent' => 'scoped-agent',
            'message' => 'test',
            '--team' => '5',
            '--user' => 'u1',
        ])->assertSuccessful();
    });
});
