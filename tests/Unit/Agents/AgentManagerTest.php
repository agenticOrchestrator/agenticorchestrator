<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Agents\AgentManager;
use AgenticOrchestrator\Exceptions\AgentNotFoundException;
use Illuminate\Container\Container;

// Test agent class
class TestAgent extends Agent
{
    public function instructions(): string
    {
        return 'Test agent instructions';
    }
}

// Test system agent class
class SystemTestAgent extends Agent
{
    protected bool $isSystem = true;

    protected string $name = 'system-test';

    public function instructions(): string
    {
        return 'System agent instructions';
    }
}

beforeEach(function () {
    $this->container = new Container;
    $this->manager = new AgentManager($this->container);
});

test('registers an agent', function () {
    $this->manager->register(TestAgent::class, 'test');

    expect($this->manager->has('test'))->toBeTrue();
    expect($this->manager->all())->toHaveKey('test');
});

test('registers a system agent', function () {
    $this->manager->registerSystemAgent(SystemTestAgent::class);

    expect($this->manager->has('system-test'))->toBeTrue();
    expect($this->manager->isSystemAgent('system-test'))->toBeTrue();
});

test('registers agent for specific team', function () {
    $this->manager->registerForTeam(1, TestAgent::class, 'team-agent');

    expect($this->manager->isAccessibleByTeam('team-agent', 1))->toBeTrue();
    expect($this->manager->isAccessibleByTeam('team-agent', 2))->toBeFalse();
});

test('system agents are accessible by all teams', function () {
    $this->manager->registerSystemAgent(SystemTestAgent::class);

    expect($this->manager->isAccessibleByTeam('system-test', 1))->toBeTrue();
    expect($this->manager->isAccessibleByTeam('system-test', 2))->toBeTrue();
    expect($this->manager->isAccessibleByTeam('system-test', 999))->toBeTrue();
});

test('throws exception for non-existent agent', function () {
    $this->manager->make('non-existent');
})->throws(AgentNotFoundException::class);

test('resolves agent name from class', function () {
    $this->manager->register(TestAgent::class);

    // TestAgent should become 'test' (remove 'Agent' suffix, kebab-case)
    expect($this->manager->has('test'))->toBeTrue();
});

test('forgets an agent', function () {
    $this->manager->register(TestAgent::class, 'test');
    expect($this->manager->has('test'))->toBeTrue();

    $this->manager->forget('test');
    expect($this->manager->has('test'))->toBeFalse();
});

test('flushes all agents', function () {
    $this->manager->register(TestAgent::class, 'test');
    $this->manager->registerSystemAgent(SystemTestAgent::class);
    $this->manager->registerForTeam(1, TestAgent::class, 'team-agent');

    $this->manager->flush();

    expect($this->manager->all())->toBeEmpty();
    expect($this->manager->systemAgents())->toBeEmpty();
});

test('gets agents for team including system agents', function () {
    $this->manager->registerSystemAgent(SystemTestAgent::class);
    $this->manager->registerForTeam(1, TestAgent::class, 'team-agent');

    $teamAgents = $this->manager->forTeam(1);

    expect($teamAgents)->toHaveCount(2);
    expect($teamAgents)->toHaveKey('system-test');
    expect($teamAgents)->toHaveKey('team-agent');
});

test('gets custom agents for team excluding system agents', function () {
    $this->manager->registerSystemAgent(SystemTestAgent::class);
    $this->manager->registerForTeam(1, TestAgent::class, 'team-agent');

    $customAgents = $this->manager->customAgentsForTeam(1);

    expect($customAgents)->toHaveCount(1);
    expect($customAgents)->toHaveKey('team-agent');
    expect($customAgents)->not->toHaveKey('system-test');
});

test('validates agent class implements interface', function () {
    $this->manager->register(stdClass::class, 'invalid');
})->throws(InvalidArgumentException::class);

test('validates agent class exists', function () {
    $this->manager->register('NonExistentClass', 'invalid');
})->throws(InvalidArgumentException::class);

test('gets agent metadata', function () {
    $this->manager->register(TestAgent::class, 'test');
    $this->manager->registerSystemAgent(SystemTestAgent::class);

    $metadata = $this->manager->getAgentMetadata();

    expect($metadata)->toHaveKey('test');
    expect($metadata)->toHaveKey('system-test');
    expect($metadata['test']['system'])->toBeFalse();
    expect($metadata['system-test']['system'])->toBeTrue();
});

test('clears team agents', function () {
    $this->manager->registerForTeam(1, TestAgent::class, 'agent-1');
    $this->manager->registerForTeam(1, TestAgent::class, 'agent-2');
    $this->manager->registerForTeam(2, TestAgent::class, 'agent-3');

    $this->manager->clearTeam(1);

    expect($this->manager->customAgentsForTeam(1))->toBeEmpty();
    expect($this->manager->customAgentsForTeam(2))->toHaveCount(1);
});
