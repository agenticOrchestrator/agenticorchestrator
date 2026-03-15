<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Facades;

use AgenticOrchestrator\Memory\MemoryManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \AgenticOrchestrator\Memory\Memory driver(?string $name = null)
 * @method static string getDefaultDriver()
 * @method static void setDefaultDriver(string $name)
 * @method static array<int, string> getSupportedDrivers()
 *
 * @see MemoryManager
 */
class Memory extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return MemoryManager::class;
    }
}
