<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'agent:make')]
class MakeAgentCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'agent:make';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new agent class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Agent';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        if ($this->option('system')) {
            return $this->resolveStubPath('/stubs/agent.system.stub');
        }

        if ($this->option('tool')) {
            return $this->resolveStubPath('/stubs/agent.tools.stub');
        }

        return $this->resolveStubPath('/stubs/agent.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     */
    protected function resolveStubPath(string $stub): string
    {
        $customPath = $this->laravel->basePath(trim($stub, '/'));

        return file_exists($customPath)
            ? $customPath
            : __DIR__.'/../../../resources'.$stub;
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Agents';
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        return $this->replaceAgentName($stub, $name);
    }

    /**
     * Replace the agent name placeholder.
     */
    protected function replaceAgentName(string $stub, string $name): string
    {
        $agentName = $this->getAgentName($name);

        return str_replace('{{ agentName }}', $agentName, $stub);
    }

    /**
     * Get the agent name from the class name.
     */
    protected function getAgentName(string $name): string
    {
        $className = class_basename($name);

        // Remove 'Agent' suffix if present
        if (str_ends_with($className, 'Agent')) {
            $className = substr($className, 0, -5);
        }

        // Convert to kebab-case
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $className));
    }

    /**
     * Get the console command options.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the agent already exists'],
            ['system', 's', InputOption::VALUE_NONE, 'Create a system agent (available to all teams)'],
            ['tool', 't', InputOption::VALUE_NONE, 'Create an agent with example tool methods'],
        ];
    }
}
