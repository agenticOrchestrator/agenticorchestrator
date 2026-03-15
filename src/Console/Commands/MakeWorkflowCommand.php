<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'agent:make-workflow')]
class MakeWorkflowCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'agent:make-workflow';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new workflow class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Workflow';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        if ($this->option('parallel')) {
            return $this->resolveStubPath('/stubs/workflow.parallel.stub');
        }

        if ($this->option('conditional')) {
            return $this->resolveStubPath('/stubs/workflow.conditional.stub');
        }

        return $this->resolveStubPath('/stubs/workflow.stub');
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
        return $rootNamespace.'\Workflows';
    }

    /**
     * Get the console command options.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the workflow already exists'],
            ['parallel', 'p', InputOption::VALUE_NONE, 'Create a workflow with parallel step example'],
            ['conditional', 'c', InputOption::VALUE_NONE, 'Create a workflow with conditional step example'],
        ];
    }
}
