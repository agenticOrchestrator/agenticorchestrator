<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\AgentManager;
use AgenticOrchestrator\Facades\Agent;
use AgenticOrchestrator\Facades\Memory;
use AgenticOrchestrator\Facades\Tenant;
use AgenticOrchestrator\Memory\MemoryManager;

describe('Facades', function () {
    it('Agent facade resolves to AgentManager', function () {
        $reflection = new ReflectionMethod(Agent::class, 'getFacadeAccessor');

        $result = $reflection->invoke(null);

        expect($result)->toBe(AgentManager::class);
    });

    it('Memory facade resolves to MemoryManager', function () {
        $reflection = new ReflectionMethod(Memory::class, 'getFacadeAccessor');

        $result = $reflection->invoke(null);

        expect($result)->toBe(MemoryManager::class);
    });

    it('Tenant facade resolves to agent-tenancy', function () {
        $reflection = new ReflectionMethod(Tenant::class, 'getFacadeAccessor');

        $result = $reflection->invoke(null);

        expect($result)->toBe('agent-tenancy');
    });
});
