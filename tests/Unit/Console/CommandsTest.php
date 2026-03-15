<?php

declare(strict_types=1);

use AgenticOrchestrator\Console\Commands\ChatAgentCommand;
use AgenticOrchestrator\Console\Commands\ListAgentsCommand;
use AgenticOrchestrator\Console\Commands\ListToolsCommand;
use AgenticOrchestrator\Console\Commands\MakeAgentCommand;
use AgenticOrchestrator\Console\Commands\MakeEvaluationCommand;
use AgenticOrchestrator\Console\Commands\MakeToolCommand;
use AgenticOrchestrator\Console\Commands\MakeWorkflowCommand;
use AgenticOrchestrator\Console\Commands\RunAgentCommand;
use AgenticOrchestrator\Console\Commands\RunWorkflowCommand;
use AgenticOrchestrator\Console\Commands\SyncSystemAgentsCommand;

describe('MakeAgentCommand', function () {
    it('has the correct signature', function () {
        $command = new MakeAgentCommand(app('files'));

        expect($command->getName())->toBe('agent:make')
            ->and($command->getDescription())->toBe('Create a new agent class');
    });

    it('has system option', function () {
        $command = new MakeAgentCommand(app('files'));
        $definition = $command->getDefinition();

        expect($definition->hasOption('system'))->toBeTrue()
            ->and($definition->hasOption('tool'))->toBeTrue()
            ->and($definition->hasOption('force'))->toBeTrue();
    });
});

describe('MakeToolCommand', function () {
    it('has the correct signature', function () {
        $command = new MakeToolCommand(app('files'));

        expect($command->getName())->toBe('agent:make-tool')
            ->and($command->getDescription())->toBe('Create a new tool class');
    });

    it('has invokable option', function () {
        $command = new MakeToolCommand(app('files'));
        $definition = $command->getDefinition();

        expect($definition->hasOption('invokable'))->toBeTrue()
            ->and($definition->hasOption('force'))->toBeTrue();
    });
});

describe('MakeWorkflowCommand', function () {
    it('has the correct signature', function () {
        $command = new MakeWorkflowCommand(app('files'));

        expect($command->getName())->toBe('agent:make-workflow')
            ->and($command->getDescription())->toBe('Create a new workflow class');
    });

    it('has parallel and conditional options', function () {
        $command = new MakeWorkflowCommand(app('files'));
        $definition = $command->getDefinition();

        expect($definition->hasOption('parallel'))->toBeTrue()
            ->and($definition->hasOption('conditional'))->toBeTrue()
            ->and($definition->hasOption('force'))->toBeTrue();
    });
});

describe('MakeEvaluationCommand', function () {
    it('has the correct signature', function () {
        $command = new MakeEvaluationCommand(app('files'));

        expect($command->getName())->toBe('agent:make-evaluation')
            ->and($command->getDescription())->toBe('Create a new agent evaluation test suite');
    });

    it('has agent option', function () {
        $command = new MakeEvaluationCommand(app('files'));
        $definition = $command->getDefinition();

        expect($definition->hasOption('agent'))->toBeTrue()
            ->and($definition->hasOption('force'))->toBeTrue();
    });
});

describe('ListAgentsCommand', function () {
    it('has the correct signature', function () {
        $command = new ListAgentsCommand;

        expect($command->getName())->toBe('agent:list')
            ->and($command->getDescription())->toBe('List all registered agents');
    });

    it('has filter options', function () {
        $command = new ListAgentsCommand;
        $definition = $command->getDefinition();

        expect($definition->hasOption('team'))->toBeTrue()
            ->and($definition->hasOption('system'))->toBeTrue()
            ->and($definition->hasOption('custom'))->toBeTrue()
            ->and($definition->hasOption('json'))->toBeTrue();
    });
});

describe('ListToolsCommand', function () {
    it('has the correct signature', function () {
        $command = new ListToolsCommand;

        expect($command->getName())->toBe('agent:list-tools')
            ->and($command->getDescription())->toBe('List all registered tools');
    });

    it('has output options', function () {
        $command = new ListToolsCommand;
        $definition = $command->getDefinition();

        expect($definition->hasOption('schema'))->toBeTrue()
            ->and($definition->hasOption('json'))->toBeTrue();
    });
});

describe('RunAgentCommand', function () {
    it('has the correct signature', function () {
        $command = new RunAgentCommand;

        expect($command->getName())->toBe('agent:run')
            ->and($command->getDescription())->toBe('Run an agent with a single message');
    });

    it('has required arguments', function () {
        $command = new RunAgentCommand;
        $definition = $command->getDefinition();

        expect($definition->hasArgument('agent'))->toBeTrue()
            ->and($definition->hasArgument('message'))->toBeTrue();
    });

    it('has context options', function () {
        $command = new RunAgentCommand;
        $definition = $command->getDefinition();

        expect($definition->hasOption('team'))->toBeTrue()
            ->and($definition->hasOption('user'))->toBeTrue();
    });
});

describe('ChatAgentCommand', function () {
    it('has the correct signature', function () {
        $command = new ChatAgentCommand;

        expect($command->getName())->toBe('agent:chat')
            ->and($command->getDescription())->toBe('Start an interactive chat session with an agent');
    });

    it('has agent argument', function () {
        $command = new ChatAgentCommand;
        $definition = $command->getDefinition();

        expect($definition->hasArgument('agent'))->toBeTrue();
    });

    it('has context options', function () {
        $command = new ChatAgentCommand;
        $definition = $command->getDefinition();

        expect($definition->hasOption('team'))->toBeTrue()
            ->and($definition->hasOption('user'))->toBeTrue();
    });
});

describe('RunWorkflowCommand', function () {
    it('has the correct signature', function () {
        $command = new RunWorkflowCommand;

        expect($command->getName())->toBe('workflow:run')
            ->and($command->getDescription())->toContain('workflow');
    });
});

describe('SyncSystemAgentsCommand', function () {
    it('has the correct signature', function () {
        $command = new SyncSystemAgentsCommand;

        expect($command->getName())->toBe('agent:sync-system')
            ->and($command->getDescription())->toBe('Sync system agents from configuration');
    });

    it('has dry-run option', function () {
        $command = new SyncSystemAgentsCommand;
        $definition = $command->getDefinition();

        expect($definition->hasOption('dry-run'))->toBeTrue();
    });
});
