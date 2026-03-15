<?php

declare(strict_types=1);

use AgenticOrchestrator\Console\Commands\ListToolsCommand;
use AgenticOrchestrator\Tools\ToolRegistry;
use Illuminate\Support\Collection;

describe('ListToolsCommand', function () {
    it('displays message when no tools are registered', function () {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('all')->once()->andReturn(new Collection([]));
        $registry->shouldReceive('discovered')->once()->andReturn(new Collection([]));

        $this->app->instance(ToolRegistry::class, $registry);

        $this->artisan('agent:list-tools')
            ->expectsOutputToContain('No tools registered')
            ->assertSuccessful();
    });

    it('displays registered tools in a table', function () {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('all')->once()->andReturn(new Collection([
            'search' => 'App\\Tools\\SearchTool',
            'calculator' => 'App\\Tools\\CalculatorTool',
        ]));
        $registry->shouldReceive('discovered')->once()->andReturn(new Collection([]));

        $this->app->instance(ToolRegistry::class, $registry);

        $this->artisan('agent:list-tools')
            ->expectsOutputToContain('Registered Tools (Class-based)')
            ->expectsOutputToContain('search')
            ->expectsOutputToContain('calculator')
            ->expectsOutputToContain('Total: 2 tool(s)')
            ->assertSuccessful();
    });

    it('displays discovered tools in a table', function () {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('all')->once()->andReturn(new Collection([]));
        $registry->shouldReceive('discovered')->once()->andReturn(new Collection([
            'lookup' => [
                'class' => 'App\\Services\\LookupService',
                'method' => 'lookup',
                'schema' => [
                    'function' => ['description' => 'Look up records in database'],
                ],
            ],
        ]));

        $this->app->instance(ToolRegistry::class, $registry);

        $this->artisan('agent:list-tools')
            ->expectsOutputToContain('Discovered Tools (Method-based)')
            ->expectsOutputToContain('lookup')
            ->expectsOutputToContain('Total: 1 tool(s)')
            ->assertSuccessful();
    });

    it('outputs JSON when json option provided', function () {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('all')->andReturn(new Collection([
            'json-tool' => 'App\\Tools\\JsonTool',
        ]));
        $registry->shouldReceive('discovered')->andReturn(new Collection([]));
        $registry->shouldReceive('getSchema')->andReturn(null);
        $registry->shouldReceive('toArray')->andReturn([]);

        $this->app->forgetInstance(ToolRegistry::class);
        $this->app->instance(ToolRegistry::class, $registry);

        $this->artisan('agent:list-tools', ['--json' => true])
            ->expectsOutputToContain('registered')
            ->assertSuccessful();
    });

    it('outputs JSON with schemas when both flags provided', function () {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('all')->once()->andReturn(new Collection([
            'search' => 'App\\Tools\\SearchTool',
        ]));
        $registry->shouldReceive('discovered')->once()->andReturn(new Collection([]));
        $registry->shouldReceive('getSchema')
            ->with('search')
            ->once()
            ->andReturn(['type' => 'function', 'function' => ['name' => 'search']]);

        $this->app->instance(ToolRegistry::class, $registry);

        $this->artisan('agent:list-tools', ['--json' => true, '--schema' => true])
            ->expectsOutputToContain('"schema"')
            ->assertSuccessful();
    });

    it('shows schema hint when schema flag used without json', function () {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('all')->once()->andReturn(new Collection([
            'search' => 'App\\Tools\\SearchTool',
        ]));
        $registry->shouldReceive('discovered')->once()->andReturn(new Collection([]));

        $this->app->instance(ToolRegistry::class, $registry);

        $this->artisan('agent:list-tools', ['--schema' => true])
            ->expectsOutputToContain('Use --json flag to see full schemas')
            ->assertSuccessful();
    });

    it('truncates long descriptions in discovered tools table', function () {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('all')->once()->andReturn(new Collection([]));
        $registry->shouldReceive('discovered')->once()->andReturn(new Collection([
            'verbose-tool' => [
                'class' => 'App\\Tools\\VerboseTool',
                'method' => 'execute',
                'schema' => [
                    'function' => ['description' => 'This is a very long description that exceeds the forty character limit for display'],
                ],
            ],
        ]));

        $this->app->instance(ToolRegistry::class, $registry);

        $this->artisan('agent:list-tools')
            ->expectsOutputToContain('...')
            ->assertSuccessful();
    });

    it('displays both registered and discovered tools', function () {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('all')->once()->andReturn(new Collection([
            'search' => 'App\\Tools\\SearchTool',
        ]));
        $registry->shouldReceive('discovered')->once()->andReturn(new Collection([
            'lookup' => [
                'class' => 'App\\Services\\Lookup',
                'method' => 'find',
                'schema' => ['function' => ['description' => 'Find items']],
            ],
        ]));

        $this->app->instance(ToolRegistry::class, $registry);

        $this->artisan('agent:list-tools')
            ->expectsOutputToContain('Registered Tools (Class-based)')
            ->expectsOutputToContain('Discovered Tools (Method-based)')
            ->expectsOutputToContain('Total: 2 tool(s)')
            ->assertSuccessful();
    });

    it('truncates long class names', function () {
        $command = new ListToolsCommand;
        $reflection = new ReflectionMethod($command, 'truncateClass');

        $shortClass = 'App\\Tools\\Short';
        $longClass = 'App\\VeryLongNamespace\\EvenLongerSubNamespace\\ExtremelyVerboseToolClassName';

        expect($reflection->invoke($command, $shortClass))->toBe($shortClass)
            ->and($reflection->invoke($command, $longClass))->toStartWith('...');
    });

    it('handles discovered tools with missing optional fields', function () {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('all')->once()->andReturn(new Collection([]));
        $registry->shouldReceive('discovered')->once()->andReturn(new Collection([
            'minimal-tool' => [
                'class' => null,
                'method' => null,
                'schema' => [],
            ],
        ]));

        $this->app->instance(ToolRegistry::class, $registry);

        $this->artisan('agent:list-tools')
            ->expectsOutputToContain('minimal-tool')
            ->assertSuccessful();
    });
});
