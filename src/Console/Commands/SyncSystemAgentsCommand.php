<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Console\Commands;

use AgenticOrchestrator\Agents\AgentManager;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Sync system agents from configuration.
 */
#[AsCommand(name: 'agent:sync-system')]
class SyncSystemAgentsCommand extends Command
{
    /**
     * The console command signature.
     */
    protected $signature = 'agent:sync-system
                            {--dry-run : Show what would be synced without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Sync system agents from configuration';

    /**
     * Execute the console command.
     */
    public function handle(AgentManager $manager): int
    {
        $systemAgents = config('agent-orchestrator.multi_tenancy.system_agents', []);
        $dryRun = $this->option('dry-run');

        if (empty($systemAgents)) {
            $this->warn('No system agents configured in agent-orchestrator.multi_tenancy.system_agents');

            return self::SUCCESS;
        }

        $this->info($dryRun ? 'System agents that would be synced:' : 'Syncing system agents...');

        foreach ($systemAgents as $agentClass) {
            if (! class_exists($agentClass)) {
                $this->error("Agent class not found: {$agentClass}");

                continue;
            }

            $name = class_basename($agentClass);

            if ($dryRun) {
                $this->line("  - {$name} ({$agentClass})");
            } else {
                $manager->registerSystemAgent($agentClass);
                $this->info("Registered: {$name}");
            }
        }

        if (! $dryRun) {
            $this->newLine();
            $this->info('System agents synced successfully.');
        }

        return self::SUCCESS;
    }
}
