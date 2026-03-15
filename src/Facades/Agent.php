<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Facades;

use AgenticOrchestrator\Agents\AgentManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \AgenticOrchestrator\Contracts\AgentInterface make(string $name)
 * @method static void register(string $name, string $class)
 * @method static void registerSystemAgent(string $class)
 * @method static array<string, string> getRegistered()
 * @method static array<string, string> getSystemAgents()
 *
 * @see AgentManager
 */
class Agent extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return AgentManager::class;
    }
}
