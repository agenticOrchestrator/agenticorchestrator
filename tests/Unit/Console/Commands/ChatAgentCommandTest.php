<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\AgentManager;
use AgenticOrchestrator\Agents\AgentResponse;
use AgenticOrchestrator\Contracts\AgentInterface;

describe('ChatAgentCommand', function () {
    it('starts chat and exits on quit command', function () {
        $response = new AgentResponse(content: 'Hello! How can I help?');

        $agent = Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('respond')
            ->with('Hi there')
            ->once()
            ->andReturn($response);

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('make')
            ->with('chat-agent')
            ->once()
            ->andReturn($agent);

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:chat', ['agent' => 'chat-agent'])
            ->expectsQuestion('You', 'Hi there')
            ->expectsOutputToContain('Hello! How can I help?')
            ->expectsQuestion('You', 'quit')
            ->expectsOutputToContain('Goodbye!')
            ->assertSuccessful();
    });

    it('exits on exit command', function () {
        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('make')
            ->with('chat-agent')
            ->once()
            ->andReturn(Mockery::mock(AgentInterface::class));

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:chat', ['agent' => 'chat-agent'])
            ->expectsQuestion('You', 'exit')
            ->expectsOutputToContain('Goodbye!')
            ->assertSuccessful();
    });

    it('exits on q shorthand command', function () {
        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('make')
            ->with('chat-agent')
            ->once()
            ->andReturn(Mockery::mock(AgentInterface::class));

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:chat', ['agent' => 'chat-agent'])
            ->expectsQuestion('You', 'q')
            ->expectsOutputToContain('Goodbye!')
            ->assertSuccessful();
    });

    it('uses team scoping when team option provided', function () {
        $agent = Mockery::mock(AgentInterface::class);

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('makeForTeam')
            ->with('chat-agent', '10')
            ->once()
            ->andReturn($agent);

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:chat', ['agent' => 'chat-agent', '--team' => '10'])
            ->expectsQuestion('You', 'exit')
            ->assertSuccessful();
    });

    it('applies user scoping when user option provided', function () {
        $agent = Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('forUser')
            ->with('user-5')
            ->once()
            ->andReturnSelf();

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('make')
            ->with('chat-agent')
            ->once()
            ->andReturn($agent);

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:chat', ['agent' => 'chat-agent', '--user' => 'user-5'])
            ->expectsQuestion('You', 'quit')
            ->assertSuccessful();
    });

    it('returns failure on exception', function () {
        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('make')
            ->with('bad-agent')
            ->once()
            ->andThrow(new RuntimeException('No such agent'));

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:chat', ['agent' => 'bad-agent'])
            ->expectsOutputToContain('Error: No such agent')
            ->assertFailed();
    });

    it('skips empty messages in chat loop', function () {
        $response = new AgentResponse(content: 'Got it');

        $agent = Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('respond')
            ->with('real message')
            ->once()
            ->andReturn($response);

        $manager = Mockery::mock(AgentManager::class);
        $manager->shouldReceive('make')
            ->with('chat-agent')
            ->once()
            ->andReturn($agent);

        $this->app->instance(AgentManager::class, $manager);

        $this->artisan('agent:chat', ['agent' => 'chat-agent'])
            ->expectsQuestion('You', '')
            ->expectsQuestion('You', 'real message')
            ->expectsQuestion('You', 'quit')
            ->assertSuccessful();
    });
});
