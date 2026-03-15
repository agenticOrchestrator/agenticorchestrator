<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Console\Commands;

use AgenticOrchestrator\Agents\AgentManager;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'agent:list')]
class ListAgentsCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'agent:list
                            {--team= : Filter by team ID}
                            {--system : Show only system agents}
                            {--custom : Show only custom (non-system) agents}
                            {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all registered agents';

    /**
     * Execute the console command.
     */
    public function handle(AgentManager $manager): int
    {
        $agents = $this->getAgents($manager);

        if (empty($agents)) {
            $this->info('No agents registered.');

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line(json_encode($agents, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->displayTable($agents);

        return self::SUCCESS;
    }

    /**
     * Get the agents based on filters.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getAgents(AgentManager $manager): array
    {
        $teamId = $this->option('team') ? (string) $this->option('team') : null;
        $systemOnly = (bool) $this->option('system');
        $customOnly = (bool) $this->option('custom');

        if ($teamId !== null) {
            if ($customOnly) {
                return $this->formatAgents($manager->customAgentsForTeam((int) $teamId)->toArray(), false);
            }

            return $this->formatAgents($manager->forTeam((int) $teamId)->toArray(), null);
        }

        if ($systemOnly) {
            return $this->formatAgents($manager->systemAgents()->toArray(), true);
        }

        if ($customOnly) {
            // Get all non-system agents
            $all = $manager->all()->toArray();
            $system = $manager->systemAgents()->toArray();
            $custom = array_diff_key($all, $system);

            return $this->formatAgents($custom, false);
        }

        return $this->formatAgents($manager->all()->toArray(), null);
    }

    /**
     * Format agents for display.
     *
     * @param  array<string, mixed>  $agents
     * @return array<string, array<string, mixed>>
     */
    protected function formatAgents(array $agents, ?bool $isSystem): array
    {
        $formatted = [];

        foreach ($agents as $name => $agentClass) {
            $formatted[$name] = [
                'name' => $name,
                'class' => is_string($agentClass) ? $agentClass : get_class($agentClass),
                'system' => $isSystem ?? $this->isSystemAgent($name),
            ];
        }

        return $formatted;
    }

    /**
     * Check if an agent is a system agent (simple heuristic).
     */
    protected function isSystemAgent(string $name): bool
    {
        return str_starts_with($name, 'system-') || str_contains($name, 'system');
    }

    /**
     * Display agents in a table.
     *
     * @param  array<string, array<string, mixed>>  $agents
     */
    protected function displayTable(array $agents): void
    {
        $headers = ['Name', 'Class', 'Type'];
        $rows = [];

        foreach ($agents as $agent) {
            $rows[] = [
                $agent['name'],
                $this->truncateClass($agent['class']),
                $agent['system'] ? '<fg=cyan>System</>' : '<fg=green>Custom</>',
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();
        $this->info(sprintf('Total: %d agent(s)', count($agents)));
    }

    /**
     * Truncate class name for display.
     */
    protected function truncateClass(string $class): string
    {
        if (strlen($class) <= 60) {
            return $class;
        }

        return '...'.substr($class, -57);
    }
}
