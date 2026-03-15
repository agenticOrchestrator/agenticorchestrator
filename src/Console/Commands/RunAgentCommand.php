<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Console\Commands;

use AgenticOrchestrator\Agents\AgentManager;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'agent:run')]
class RunAgentCommand extends Command
{
    /**
     * The console command signature.
     */
    protected $signature = 'agent:run
                            {agent : The agent name to run}
                            {message : The message to send to the agent}
                            {--team= : Team ID to scope the agent}
                            {--user= : User ID for the conversation}';

    /**
     * The console command description.
     */
    protected $description = 'Run an agent with a single message';

    /**
     * Execute the console command.
     */
    public function handle(AgentManager $manager): int
    {
        $agentName = (string) $this->argument('agent');
        $message = (string) $this->argument('message');
        $teamId = $this->option('team') ? (string) $this->option('team') : null;
        $userId = $this->option('user') ? (string) $this->option('user') : null;

        try {
            if ($teamId) {
                $agent = $manager->makeForTeam($agentName, $teamId);
            } else {
                $agent = $manager->make($agentName);
            }

            if ($userId) {
                $agent = $agent->forUser($userId);
            }

            $this->info("Running agent: {$agentName}");
            $this->newLine();

            $response = $agent->respond($message);

            $this->line($response->content);
            $this->newLine();

            if ($response->hasToolCalls()) {
                $this->info('Tool calls made: '.count($response->getToolCalls()));
            }

            $this->info("Tokens used: {$response->getTotalTokens()}");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
