<?php

declare(strict_types=1);

use AgenticOrchestrator\Mcp\McpClient;
use AgenticOrchestrator\Mcp\McpTool;
use AgenticOrchestrator\Mcp\McpToolProvider;

describe('McpToolProvider', function () {
    beforeEach(function () {
        $this->provider = McpToolProvider::make();
    });

    describe('make', function () {
        it('creates a new instance via static factory', function () {
            expect(McpToolProvider::make())->toBeInstanceOf(McpToolProvider::class);
        });
    });

    describe('addServer', function () {
        it('adds a server and returns fluent instance', function () {
            $result = $this->provider->addServer('test', ['url' => 'http://localhost:3000']);

            expect($result)->toBe($this->provider)
                ->and($this->provider->getServerNames())->toContain('test');
        });

        it('adds multiple servers', function () {
            $this->provider
                ->addServer('s1', ['url' => 'http://localhost:3001'])
                ->addServer('s2', ['url' => 'http://localhost:3002']);

            expect($this->provider->getServerNames())->toBe(['s1', 's2']);
        });
    });

    describe('addClient', function () {
        it('adds a client directly', function () {
            $client = McpClient::make();
            $result = $this->provider->addClient('custom', $client);

            expect($result)->toBe($this->provider)
                ->and($this->provider->getClient('custom'))->toBe($client);
        });
    });

    describe('getClient', function () {
        it('returns null for unknown server', function () {
            expect($this->provider->getClient('nonexistent'))->toBeNull();
        });

        it('returns the client for a known server', function () {
            $client = McpClient::make();
            $this->provider->addClient('known', $client);

            expect($this->provider->getClient('known'))->toBe($client);
        });
    });

    describe('getServerNames', function () {
        it('returns empty array when no servers added', function () {
            expect($this->provider->getServerNames())->toBe([]);
        });

        it('returns all server names in order', function () {
            $this->provider
                ->addServer('alpha', [])
                ->addServer('beta', [])
                ->addClient('gamma', McpClient::make());

            expect($this->provider->getServerNames())->toBe(['alpha', 'beta', 'gamma']);
        });
    });

    describe('getTools', function () {
        it('returns empty array when not connected', function () {
            expect($this->provider->getTools())->toBe([]);
        });
    });

    describe('getTool', function () {
        it('returns null when no tools registered', function () {
            expect($this->provider->getTool('anything'))->toBeNull();
        });

        it('finds tool by prefixed name after connect', function () {
            $client = Mockery::mock(McpClient::class);
            $tool = new McpTool($client, 'search', 'Search tool');

            $client->shouldReceive('connect')->once();
            $client->shouldReceive('getTools')->andReturn([$tool]);

            $this->provider->addClient('server1', $client);
            $this->provider->connect();

            expect($this->provider->getTool('server1:search'))->toBe($tool);
        });

        it('finds tool by bare name across servers', function () {
            $client = Mockery::mock(McpClient::class);
            $tool = new McpTool($client, 'search', 'Search tool');

            $client->shouldReceive('connect')->once();
            $client->shouldReceive('getTools')->andReturn([$tool]);

            $this->provider->addClient('server1', $client);
            $this->provider->connect();

            expect($this->provider->getTool('search'))->toBe($tool);
        });
    });

    describe('hasTool', function () {
        it('returns false when tool does not exist', function () {
            expect($this->provider->hasTool('missing'))->toBeFalse();
        });

        it('returns true when tool exists', function () {
            $client = Mockery::mock(McpClient::class);
            $tool = new McpTool($client, 'query', 'Query tool');

            $client->shouldReceive('connect')->once();
            $client->shouldReceive('getTools')->andReturn([$tool]);

            $this->provider->addClient('db', $client);
            $this->provider->connect();

            expect($this->provider->hasTool('db:query'))->toBeTrue();
        });
    });

    describe('connect', function () {
        it('connects all clients and discovers tools', function () {
            $client1 = Mockery::mock(McpClient::class);
            $client2 = Mockery::mock(McpClient::class);

            $tool1 = new McpTool($client1, 'tool_a', 'Tool A');
            $tool2 = new McpTool($client2, 'tool_b', 'Tool B');

            $client1->shouldReceive('connect')->once();
            $client1->shouldReceive('getTools')->andReturn([$tool1]);
            $client2->shouldReceive('connect')->once();
            $client2->shouldReceive('getTools')->andReturn([$tool2]);

            $this->provider->addClient('s1', $client1);
            $this->provider->addClient('s2', $client2);

            $result = $this->provider->connect();

            expect($result)->toBe($this->provider)
                ->and($this->provider->getTools())->toHaveCount(2);
        });

        it('handles connection failures gracefully', function () {
            $client = Mockery::mock(McpClient::class);
            $client->shouldReceive('connect')->andThrow(new RuntimeException('Connection refused'));

            $this->provider->addClient('failing', $client);

            // Should not throw, just log
            $result = $this->provider->connect();

            expect($result)->toBe($this->provider)
                ->and($this->provider->getTools())->toBeEmpty();
        });

        it('prefixes tool names with server name', function () {
            $client = Mockery::mock(McpClient::class);
            $tool = new McpTool($client, 'read_file', 'Reads a file');

            $client->shouldReceive('connect')->once();
            $client->shouldReceive('getTools')->andReturn([$tool]);

            $this->provider->addClient('filesystem', $client);
            $this->provider->connect();

            expect($this->provider->getTool('filesystem:read_file'))->toBe($tool);
        });
    });

    describe('getToolsFromServer', function () {
        it('returns only tools from specified server', function () {
            $client1 = Mockery::mock(McpClient::class);
            $client2 = Mockery::mock(McpClient::class);

            $tool1 = new McpTool($client1, 'tool_a', 'Tool A');
            $tool2 = new McpTool($client2, 'tool_b', 'Tool B');
            $tool3 = new McpTool($client1, 'tool_c', 'Tool C');

            $client1->shouldReceive('connect')->once();
            $client1->shouldReceive('getTools')->andReturn([$tool1, $tool3]);
            $client2->shouldReceive('connect')->once();
            $client2->shouldReceive('getTools')->andReturn([$tool2]);

            $this->provider->addClient('s1', $client1);
            $this->provider->addClient('s2', $client2);
            $this->provider->connect();

            $s1Tools = $this->provider->getToolsFromServer('s1');
            $s2Tools = $this->provider->getToolsFromServer('s2');

            expect($s1Tools)->toHaveCount(2)
                ->and($s2Tools)->toHaveCount(1);
        });

        it('returns empty array for unknown server', function () {
            expect($this->provider->getToolsFromServer('unknown'))->toBe([]);
        });
    });

    describe('getSchemas', function () {
        it('returns schemas for all tools', function () {
            $client = Mockery::mock(McpClient::class);
            $tool = new McpTool($client, 'my_tool', 'My description', [
                'type' => 'object',
                'properties' => ['q' => ['type' => 'string']],
            ]);

            $client->shouldReceive('connect')->once();
            $client->shouldReceive('getTools')->andReturn([$tool]);

            $this->provider->addClient('srv', $client);
            $this->provider->connect();

            $schemas = $this->provider->getSchemas();

            expect($schemas)->toHaveCount(1)
                ->and($schemas[0]['type'])->toBe('function')
                ->and($schemas[0]['function']['name'])->toBe('my_tool');
        });

        it('returns empty array when no tools', function () {
            expect($this->provider->getSchemas())->toBe([]);
        });
    });

    describe('disconnect', function () {
        it('disconnects all clients and clears tools', function () {
            $client = Mockery::mock(McpClient::class);
            $tool = new McpTool($client, 'tool', 'desc');

            $client->shouldReceive('connect')->once();
            $client->shouldReceive('getTools')->andReturn([$tool]);
            $client->shouldReceive('disconnect')->once();

            $this->provider->addClient('srv', $client);
            $this->provider->connect();

            expect($this->provider->getTools())->toHaveCount(1);

            $this->provider->disconnect();

            expect($this->provider->getTools())->toBeEmpty();
        });
    });

    describe('reconnect', function () {
        it('disconnects and reconnects', function () {
            $client = Mockery::mock(McpClient::class);
            $tool = new McpTool($client, 'tool', 'desc');

            $client->shouldReceive('disconnect')->once();
            $client->shouldReceive('connect')->once();
            $client->shouldReceive('getTools')->andReturn([$tool]);

            $this->provider->addClient('srv', $client);

            $result = $this->provider->reconnect();

            expect($result)->toBe($this->provider)
                ->and($this->provider->getTools())->toHaveCount(1);
        });
    });
});
