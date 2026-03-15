<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Console\Commands;

use AgenticOrchestrator\Agents\AgentManager;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'agent:chat')]
class ChatAgentCommand extends Command
{
    /**
     * The console command signature.
     */
    protected $signature = 'agent:chat
                            {agent : The agent name to chat with}
                            {--team= : Team ID to scope the agent}
                            {--user= : User ID for the conversation}';

    /**
     * The console command description.
     */
    protected $description = 'Start an interactive chat session with an agent';

    /**
     * Execute the console command.
     */
    public function handle(AgentManager $manager): int
    {
        $agentName = (string) $this->argument('agent');
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

            $this->info("Starting chat with agent: {$agentName}");
            $this->info('Type "exit" or "quit" to end the conversation.');
            $this->newLine();

            while (true) {
                $message = $this->ask('You');

                if (in_array(strtolower($message), ['exit', 'quit', 'q'])) {
                    $this->info('Goodbye!');
                    break;
                }

                if (empty($message)) {
                    continue;
                }

                $response = $agent->respond($message);

                $this->newLine();
                $this->line("<info>Agent:</info> {$response->content}");
                $this->newLine();
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
