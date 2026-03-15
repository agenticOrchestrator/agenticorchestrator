<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Console\Commands;

use AgenticOrchestrator\Tools\ToolRegistry;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'agent:list-tools')]
class ListToolsCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'agent:list-tools
                            {--schema : Show tool schemas}
                            {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all registered tools';

    /**
     * Execute the console command.
     */
    public function handle(ToolRegistry $registry): int
    {
        $tools = $registry->all()->toArray();
        $discovered = $registry->discovered()->toArray();

        if (empty($tools) && empty($discovered)) {
            $this->info('No tools registered.');

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->outputJson($registry, $tools, $discovered);

            return self::SUCCESS;
        }

        $this->displayTables($registry, $tools, $discovered);

        return self::SUCCESS;
    }

    /**
     * Output as JSON.
     *
     * @param  array<string, mixed>  $tools
     * @param  array<string, mixed>  $discovered
     */
    protected function outputJson(ToolRegistry $registry, array $tools, array $discovered): void
    {
        $output = [
            'registered' => [],
            'discovered' => [],
        ];

        foreach ($tools as $name => $toolClass) {
            $output['registered'][$name] = [
                'class' => $toolClass,
                'schema' => $this->option('schema') ? $registry->getSchema($name) : null,
            ];
        }

        foreach ($discovered as $name => $info) {
            $output['discovered'][$name] = [
                'class' => $info['class'] ?? null,
                'method' => $info['method'] ?? null,
                'schema' => $this->option('schema') ? ($info['schema'] ?? null) : null,
            ];
        }

        $this->line(json_encode($output, JSON_PRETTY_PRINT));
    }

    /**
     * Display tools in tables.
     *
     * @param  array<string, mixed>  $tools
     * @param  array<string, mixed>  $discovered
     */
    protected function displayTables(ToolRegistry $registry, array $tools, array $discovered): void
    {
        if (! empty($tools)) {
            $this->info('Registered Tools (Class-based):');
            $this->newLine();

            $headers = ['Name', 'Class'];
            $rows = [];

            foreach ($tools as $name => $toolClass) {
                $rows[] = [$name, $this->truncateClass($toolClass)];
            }

            $this->table($headers, $rows);
            $this->newLine();
        }

        if (! empty($discovered)) {
            $this->info('Discovered Tools (Method-based):');
            $this->newLine();

            $headers = ['Name', 'Class', 'Method', 'Description'];
            $rows = [];

            foreach ($discovered as $name => $info) {
                $description = $info['schema']['function']['description'] ?? '-';
                if (strlen($description) > 40) {
                    $description = substr($description, 0, 37).'...';
                }

                $rows[] = [
                    $name,
                    $this->truncateClass($info['class'] ?? ''),
                    $info['method'] ?? '-',
                    $description,
                ];
            }

            $this->table($headers, $rows);
            $this->newLine();
        }

        $total = count($tools) + count($discovered);
        $this->info(sprintf('Total: %d tool(s)', $total));

        if ($this->option('schema')) {
            $this->newLine();
            $this->warn('Use --json flag to see full schemas.');
        }
    }

    /**
     * Truncate class name for display.
     */
    protected function truncateClass(string $class): string
    {
        if (strlen($class) <= 50) {
            return $class;
        }

        return '...'.substr($class, -47);
    }
}
