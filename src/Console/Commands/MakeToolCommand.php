<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'agent:make-tool')]
class MakeToolCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'agent:make-tool';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new tool class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Tool';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        if ($this->option('invokable')) {
            return $this->resolveStubPath('/stubs/tool.invokable.stub');
        }

        return $this->resolveStubPath('/stubs/tool.stub');
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
        return $rootNamespace.'\Tools';
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        return $this->replaceToolName($stub, $name);
    }

    /**
     * Replace the tool name placeholder.
     */
    protected function replaceToolName(string $stub, string $name): string
    {
        $toolName = $this->getToolName($name);

        return str_replace('{{ toolName }}', $toolName, $stub);
    }

    /**
     * Get the tool name from the class name.
     */
    protected function getToolName(string $name): string
    {
        $className = class_basename($name);

        // Remove 'Tool' suffix if present
        if (str_ends_with($className, 'Tool')) {
            $className = substr($className, 0, -4);
        }

        // Convert to snake_case
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
    }

    /**
     * Get the console command options.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the tool already exists'],
            ['invokable', 'i', InputOption::VALUE_NONE, 'Create an invokable single-method tool'],
        ];
    }
}
