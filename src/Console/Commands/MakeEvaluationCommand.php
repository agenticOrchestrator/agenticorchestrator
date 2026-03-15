<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'agent:make-evaluation')]
class MakeEvaluationCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'agent:make-evaluation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new agent evaluation test suite';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Evaluation';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/evaluation.stub');
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
        return $rootNamespace.'\Evaluations';
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $agentClass = $this->option('agent') ? (string) $this->option('agent') : null;

        if ($agentClass) {
            $stub = str_replace(
                '{{ agentClass }}',
                $agentClass,
                $stub
            );
        } else {
            // Default to guessing from the evaluation name
            $evaluationName = class_basename($name);
            $agentName = str_replace('Evaluation', '', $evaluationName);
            $stub = str_replace(
                '{{ agentClass }}',
                "\\App\\Agents\\{$agentName}Agent::class",
                $stub
            );
        }

        return $stub;
    }

    /**
     * Get the console command options.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the evaluation already exists'],
            ['agent', 'a', InputOption::VALUE_OPTIONAL, 'The agent class to evaluate'],
        ];
    }
}
