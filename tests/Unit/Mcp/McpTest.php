<?php

declare(strict_types=1);

use AgenticOrchestrator\Mcp\McpClient;
use AgenticOrchestrator\Mcp\McpTool;
use AgenticOrchestrator\Mcp\McpToolProvider;

describe('McpClient', function () {
    it('creates with config', function () {
        $client = McpClient::make([
            'url' => 'http://localhost:3000',
            'api_key' => 'test-key',
            'timeout' => 60,
        ]);

        expect($client)->toBeInstanceOf(McpClient::class);
    });

    it('creates from url', function () {
        $client = McpClient::url('http://mcp.example.com');

        expect($client)->toBeInstanceOf(McpClient::class);
    });

    it('sets transport type', function () {
        $client = McpClient::make()->transport('stdio');

        expect($client)->toBeInstanceOf(McpClient::class);
    });

    it('sets headers', function () {
        $client = McpClient::make()
            ->withHeaders(['X-Custom' => 'value']);

        expect($client)->toBeInstanceOf(McpClient::class);
    });

    it('is not connected initially', function () {
        $client = McpClient::make();

        expect($client->isConnected())->toBeFalse();
    });
});

describe('McpTool', function () {
    it('returns schema', function () {
        $client = McpClient::make();
        $tool = new McpTool(
            client: $client,
            name: 'my_tool',
            description: 'A test tool',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string'],
                ],
                'required' => ['query'],
            ],
        );

        $schema = $tool->toSchema();

        expect($schema['type'])->toBe('function')
            ->and($schema['function']['name'])->toBe('my_tool')
            ->and($schema['function']['description'])->toBe('A test tool')
            ->and($schema['function']['parameters']['properties'])->toHaveKey('query');
    });

    it('validates required arguments', function () {
        $client = McpClient::make();
        $tool = new McpTool(
            client: $client,
            name: 'tool',
            description: '',
            inputSchema: [
                'required' => ['query'],
            ],
        );

        expect($tool->validate(['query' => 'test']))->toBeTrue()
            ->and($tool->validate([]))->toBeFalse();
    });

    it('is parallel by default', function () {
        $client = McpClient::make();
        $tool = new McpTool($client, 'tool', '');

        expect($tool->isParallel())->toBeTrue();
    });
});

describe('McpToolProvider', function () {
    it('adds servers', function () {
        $provider = McpToolProvider::make()
            ->addServer('server1', ['url' => 'http://localhost:3001'])
            ->addServer('server2', ['url' => 'http://localhost:3002']);

        expect($provider->getServerNames())->toBe(['server1', 'server2']);
    });

    it('adds clients directly', function () {
        $client = McpClient::make();
        $provider = McpToolProvider::make()
            ->addClient('custom', $client);

        expect($provider->getClient('custom'))->toBe($client);
    });

    it('returns empty tools when not connected', function () {
        $provider = McpToolProvider::make();

        expect($provider->getTools())->toBeEmpty();
    });

    it('disconnects all servers', function () {
        $provider = McpToolProvider::make()
            ->addServer('test', ['url' => 'http://localhost:3000']);

        $provider->disconnect();

        expect($provider->getTools())->toBeEmpty();
    });
});
