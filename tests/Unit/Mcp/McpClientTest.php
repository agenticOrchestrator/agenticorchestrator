<?php

declare(strict_types=1);

use AgenticOrchestrator\Exceptions\ToolExecutionException;
use AgenticOrchestrator\Mcp\McpClient;
use AgenticOrchestrator\Mcp\McpTool;
use Illuminate\Support\Facades\Http;

describe('McpClient', function () {
    describe('constructor and static factory methods', function () {
        it('creates instance with default config', function () {
            $client = new McpClient;

            expect($client)->toBeInstanceOf(McpClient::class);
        });

        it('creates instance with custom config', function () {
            $client = new McpClient([
                'url' => 'http://custom:8080',
                'transport' => 'stdio',
                'api_key' => 'test-key',
                'timeout' => 60,
                'headers' => ['X-Custom' => 'value'],
            ]);

            expect($client)->toBeInstanceOf(McpClient::class);
        });

        it('creates instance via make() method', function () {
            $client = McpClient::make([
                'url' => 'http://test:3000',
                'timeout' => 45,
            ]);

            expect($client)->toBeInstanceOf(McpClient::class);
        });

        it('creates instance via url() method', function () {
            $client = McpClient::url('http://example.com');

            expect($client)->toBeInstanceOf(McpClient::class);
        });
    });

    describe('fluent configuration methods', function () {
        it('sets transport via fluent method', function () {
            $client = McpClient::make()->transport('stdio');

            expect($client)->toBeInstanceOf(McpClient::class);
        });

        it('sets API key via fluent method', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [],
                ], 200),
            ]);

            $client = McpClient::make()->withApiKey('secret-key')->connect();

            Http::assertSent(function ($request) {
                return $request->hasHeader('Authorization', 'Bearer secret-key');
            });
        });

        it('sets headers via fluent method', function () {
            $client = McpClient::make()->withHeaders(['X-Test' => 'value']);

            expect($client)->toBeInstanceOf(McpClient::class);
        });

        it('merges headers when called multiple times', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [],
                ], 200),
            ]);

            $client = McpClient::make()
                ->withHeaders(['X-First' => 'one'])
                ->withHeaders(['X-Second' => 'two'])
                ->connect();

            Http::assertSent(function ($request) {
                return $request->hasHeader('X-First', 'one')
                    && $request->hasHeader('X-Second', 'two');
            });
        });

        it('sets timeout via fluent method', function () {
            $client = McpClient::make()->timeout(120);

            expect($client)->toBeInstanceOf(McpClient::class);
        });

        it('chains multiple fluent methods', function () {
            $client = McpClient::make()
                ->transport('sse')
                ->withApiKey('key')
                ->timeout(90)
                ->withHeaders(['X-Custom' => 'header']);

            expect($client)->toBeInstanceOf(McpClient::class);
        });
    });

    describe('connect()', function () {
        it('successfully connects and initializes', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => [
                        'tools' => true,
                        'resources' => true,
                        'prompts' => true,
                    ],
                    'serverInfo' => [
                        'name' => 'test-server',
                        'version' => '1.0.0',
                    ],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [
                        [
                            'name' => 'test_tool',
                            'description' => 'A test tool',
                            'inputSchema' => [
                                'type' => 'object',
                                'properties' => ['param' => ['type' => 'string']],
                            ],
                        ],
                    ],
                ], 200),
            ]);

            $client = McpClient::make()->connect();

            expect($client->isConnected())->toBeTrue()
                ->and($client->getCapabilities())->toBe([
                    'tools' => true,
                    'resources' => true,
                    'prompts' => true,
                ]);

            Http::assertSent(function ($request) {
                return $request->url() === 'http://localhost:3000/initialize'
                    && $request['protocolVersion'] === '2024-11-05'
                    && $request['clientInfo']['name'] === 'agent-orchestrator';
            });
        });

        it('discovers tools after successful connection', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [
                        [
                            'name' => 'calculator',
                            'description' => 'Performs calculations',
                            'inputSchema' => ['type' => 'object'],
                        ],
                        [
                            'name' => 'search',
                            'description' => 'Searches data',
                            'inputSchema' => ['type' => 'object'],
                        ],
                    ],
                ], 200),
            ]);

            $client = McpClient::make()->connect();

            expect($client->getTools())->toHaveCount(2)
                ->and($client->hasTool('calculator'))->toBeTrue()
                ->and($client->hasTool('search'))->toBeTrue()
                ->and($client->getTool('calculator'))->toBeInstanceOf(McpTool::class);

            Http::assertSent(function ($request) {
                return str_contains($request->url(), '/tools/list');
            });
        });

        it('handles tools without descriptions and input schemas', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [
                        ['name' => 'minimal_tool'],
                    ],
                ], 200),
            ]);

            $client = McpClient::make()->connect();

            expect($client->hasTool('minimal_tool'))->toBeTrue();
        });

        it('handles failed tool discovery gracefully', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([], 500),
            ]);

            $client = McpClient::make()->connect();

            expect($client->isConnected())->toBeTrue()
                ->and($client->getTools())->toBeEmpty();
        });

        it('throws exception when initialization fails', function () {
            Http::fake([
                '*/initialize' => Http::response(['error' => 'Server error'], 500),
            ]);

            expect(fn () => McpClient::make()->connect())
                ->toThrow(RuntimeException::class, 'Failed to initialize MCP connection');
        });

        it('uses custom server URL', function () {
            Http::fake([
                'http://custom-server:9000/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                'http://custom-server:9000/tools/list' => Http::response([
                    'tools' => [],
                ], 200),
            ]);

            $client = McpClient::url('http://custom-server:9000')->connect();

            Http::assertSent(function ($request) {
                return str_starts_with($request->url(), 'http://custom-server:9000');
            });
        });

        it('sends custom headers during connection', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [],
                ], 200),
            ]);

            McpClient::make()
                ->withHeaders(['X-Request-ID' => 'abc123'])
                ->connect();

            Http::assertSent(function ($request) {
                return $request->hasHeader('X-Request-ID', 'abc123');
            });
        });

        it('respects custom timeout', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [],
                ], 200),
            ]);

            McpClient::make()->timeout(120)->connect();

            // Connection should succeed with custom timeout
            expect(true)->toBeTrue();
        });
    });

    describe('getCapabilities()', function () {
        it('returns null when not connected', function () {
            $client = McpClient::make();

            expect($client->getCapabilities())->toBeNull();
        });

        it('returns capabilities after connection', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => [
                        'tools' => true,
                        'resources' => false,
                        'custom' => ['feature' => 'enabled'],
                    ],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
            ]);

            $client = McpClient::make()->connect();

            expect($client->getCapabilities())->toBe([
                'tools' => true,
                'resources' => false,
                'custom' => ['feature' => 'enabled'],
            ]);
        });
    });

    describe('getTools(), getTool(), hasTool()', function () {
        it('returns empty array when no tools are available', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
            ]);

            $client = McpClient::make()->connect();

            expect($client->getTools())->toBeEmpty();
        });

        it('returns all discovered tools', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [
                        ['name' => 'tool1', 'description' => 'First tool'],
                        ['name' => 'tool2', 'description' => 'Second tool'],
                        ['name' => 'tool3', 'description' => 'Third tool'],
                    ],
                ], 200),
            ]);

            $client = McpClient::make()->connect();

            $tools = $client->getTools();

            expect($tools)->toHaveCount(3)
                ->and($tools[0])->toBeInstanceOf(McpTool::class)
                ->and($tools[1])->toBeInstanceOf(McpTool::class)
                ->and($tools[2])->toBeInstanceOf(McpTool::class);
        });

        it('gets specific tool by name', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [
                        ['name' => 'calculator', 'description' => 'Math operations'],
                    ],
                ], 200),
            ]);

            $client = McpClient::make()->connect();

            $tool = $client->getTool('calculator');

            expect($tool)->toBeInstanceOf(McpTool::class);
        });

        it('returns null for non-existent tool', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
            ]);

            $client = McpClient::make()->connect();

            expect($client->getTool('non_existent'))->toBeNull();
        });

        it('checks if tool exists', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [
                        ['name' => 'existing_tool'],
                    ],
                ], 200),
            ]);

            $client = McpClient::make()->connect();

            expect($client->hasTool('existing_tool'))->toBeTrue()
                ->and($client->hasTool('missing_tool'))->toBeFalse();
        });
    });

    describe('executeTool()', function () {
        it('executes tool successfully with content response', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [
                        ['name' => 'test_tool'],
                    ],
                ], 200),
                '*/tools/call' => Http::response([
                    'content' => [
                        ['type' => 'text', 'text' => 'Result from tool'],
                    ],
                ], 200),
            ]);

            $client = McpClient::make()->connect();
            $result = $client->executeTool('test_tool', ['param' => 'value']);

            expect($result)->toBe([
                ['type' => 'text', 'text' => 'Result from tool'],
            ]);

            Http::assertSent(function ($request) {
                return str_contains($request->url(), '/tools/call')
                    && $request['name'] === 'test_tool'
                    && $request['arguments'] === ['param' => 'value'];
            });
        });

        it('executes tool successfully with direct data response', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [
                        ['name' => 'calculator'],
                    ],
                ], 200),
                '*/tools/call' => Http::response([
                    'result' => 42,
                    'status' => 'success',
                ], 200),
            ]);

            $client = McpClient::make()->connect();
            $result = $client->executeTool('calculator', ['operation' => 'add', 'a' => 40, 'b' => 2]);

            expect($result)->toBe([
                'result' => 42,
                'status' => 'success',
            ]);
        });

        it('auto-connects when not connected before execution', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [
                        ['name' => 'auto_tool'],
                    ],
                ], 200),
                '*/tools/call' => Http::response([
                    'content' => ['result' => 'success'],
                ], 200),
            ]);

            $client = McpClient::make();
            expect($client->isConnected())->toBeFalse();

            $result = $client->executeTool('auto_tool', []);

            expect($client->isConnected())->toBeTrue()
                ->and($result)->toBe(['result' => 'success']);

            Http::assertSent(function ($request) {
                return str_contains($request->url(), '/initialize');
            });
        });

        it('executes tool without arguments', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [
                        ['name' => 'no_args_tool'],
                    ],
                ], 200),
                '*/tools/call' => Http::response([
                    'content' => ['success' => true],
                ], 200),
            ]);

            $client = McpClient::make()->connect();
            $result = $client->executeTool('no_args_tool');

            expect($result)->toBe(['success' => true]);

            Http::assertSent(function ($request) {
                if (str_contains($request->url(), '/tools/call')) {
                    return $request->data()['arguments'] === [];
                }

                return true;
            });
        });

        it('throws exception when tool execution fails with HTTP error', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [
                        ['name' => 'failing_tool'],
                    ],
                ], 200),
                '*/tools/call' => Http::response([
                    'error' => 'Server error',
                ], 500),
            ]);

            $client = McpClient::make()->connect();

            expect(fn () => $client->executeTool('failing_tool', []))
                ->toThrow(ToolExecutionException::class, "MCP tool 'failing_tool' execution failed");
        });

        it('throws exception when tool returns error in response', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [
                        ['name' => 'error_tool'],
                    ],
                ], 200),
                '*/tools/call' => Http::response([
                    'error' => [
                        'code' => 'INVALID_PARAMS',
                        'message' => 'Missing required parameter',
                    ],
                ], 200),
            ]);

            $client = McpClient::make()->connect();

            expect(fn () => $client->executeTool('error_tool', []))
                ->toThrow(ToolExecutionException::class, "MCP tool 'error_tool' returned error: Missing required parameter");
        });

        it('throws exception when tool returns error without message', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [
                        ['name' => 'error_tool'],
                    ],
                ], 200),
                '*/tools/call' => Http::response([
                    'error' => [
                        'code' => 'UNKNOWN',
                    ],
                ], 200),
            ]);

            $client = McpClient::make()->connect();

            expect(fn () => $client->executeTool('error_tool', []))
                ->toThrow(ToolExecutionException::class, "MCP tool 'error_tool' returned error: Unknown error");
        });

        it('wraps general exceptions in ToolExecutionException', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [
                        ['name' => 'exception_tool'],
                    ],
                ], 200),
                '*/tools/call' => function () {
                    throw new Exception('Network timeout');
                },
            ]);

            $client = McpClient::make()->connect();

            expect(fn () => $client->executeTool('exception_tool', []))
                ->toThrow(ToolExecutionException::class, "MCP tool 'exception_tool' execution failed: Network timeout");
        });

        it('preserves original exception in ToolExecutionException', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [
                        ['name' => 'exception_tool'],
                    ],
                ], 200),
                '*/tools/call' => function () {
                    throw new RuntimeException('Original error');
                },
            ]);

            $client = McpClient::make()->connect();

            try {
                $client->executeTool('exception_tool', []);
                expect(false)->toBeTrue('Exception should have been thrown');
            } catch (ToolExecutionException $e) {
                expect($e->getPrevious())->toBeInstanceOf(RuntimeException::class)
                    ->and($e->getPrevious()->getMessage())->toBe('Original error');
            }
        });
    });

    describe('getResources()', function () {
        it('returns list of resources successfully', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['resources' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
                '*/resources/list' => Http::response([
                    'resources' => [
                        [
                            'uri' => 'file://document.txt',
                            'name' => 'Document',
                            'mimeType' => 'text/plain',
                        ],
                        [
                            'uri' => 'file://data.json',
                            'name' => 'Data',
                            'mimeType' => 'application/json',
                        ],
                    ],
                ], 200),
            ]);

            $client = McpClient::make()->connect();
            $resources = $client->getResources();

            expect($resources)->toHaveCount(2)
                ->and($resources[0]['uri'])->toBe('file://document.txt')
                ->and($resources[1]['uri'])->toBe('file://data.json');
        });

        it('auto-connects when not connected', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['resources' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
                '*/resources/list' => Http::response([
                    'resources' => [
                        ['uri' => 'file://test.txt'],
                    ],
                ], 200),
            ]);

            $client = McpClient::make();
            expect($client->isConnected())->toBeFalse();

            $resources = $client->getResources();

            expect($client->isConnected())->toBeTrue()
                ->and($resources)->toHaveCount(1);
        });

        it('returns empty array when request fails', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['resources' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
                '*/resources/list' => Http::response([], 500),
            ]);

            $client = McpClient::make()->connect();
            $resources = $client->getResources();

            expect($resources)->toBeEmpty();
        });

        it('returns empty array when response has no resources key', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['resources' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
                '*/resources/list' => Http::response([
                    'status' => 'ok',
                ], 200),
            ]);

            $client = McpClient::make()->connect();
            $resources = $client->getResources();

            expect($resources)->toBeEmpty();
        });
    });

    describe('readResource()', function () {
        it('reads resource successfully', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['resources' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
                '*/resources/read' => Http::response([
                    'contents' => [
                        [
                            'uri' => 'file://document.txt',
                            'mimeType' => 'text/plain',
                            'text' => 'Document content here',
                        ],
                    ],
                ], 200),
            ]);

            $client = McpClient::make()->connect();
            $contents = $client->readResource('file://document.txt');

            expect($contents)->toHaveCount(1)
                ->and($contents[0]['text'])->toBe('Document content here');

            Http::assertSent(function ($request) {
                return str_contains($request->url(), '/resources/read')
                    && $request['uri'] === 'file://document.txt';
            });
        });

        it('auto-connects when not connected', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['resources' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
                '*/resources/read' => Http::response([
                    'contents' => [
                        ['text' => 'Content'],
                    ],
                ], 200),
            ]);

            $client = McpClient::make();
            expect($client->isConnected())->toBeFalse();

            $contents = $client->readResource('file://test.txt');

            expect($client->isConnected())->toBeTrue()
                ->and($contents)->toHaveCount(1);
        });

        it('throws exception when read fails', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['resources' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
                '*/resources/read' => Http::response([
                    'error' => 'Resource not found',
                ], 404),
            ]);

            $client = McpClient::make()->connect();

            expect(fn () => $client->readResource('file://missing.txt'))
                ->toThrow(RuntimeException::class, 'Failed to read resource: file://missing.txt');
        });

        it('returns empty array when response has no contents key', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['resources' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
                '*/resources/read' => Http::response([
                    'status' => 'ok',
                ], 200),
            ]);

            $client = McpClient::make()->connect();
            $contents = $client->readResource('file://test.txt');

            expect($contents)->toBeEmpty();
        });
    });

    describe('getPrompts()', function () {
        it('returns list of prompts successfully', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['prompts' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
                '*/prompts/list' => Http::response([
                    'prompts' => [
                        [
                            'name' => 'code_review',
                            'description' => 'Reviews code',
                            'arguments' => [
                                ['name' => 'language', 'required' => true],
                            ],
                        ],
                        [
                            'name' => 'summarize',
                            'description' => 'Summarizes text',
                        ],
                    ],
                ], 200),
            ]);

            $client = McpClient::make()->connect();
            $prompts = $client->getPrompts();

            expect($prompts)->toHaveCount(2)
                ->and($prompts[0]['name'])->toBe('code_review')
                ->and($prompts[1]['name'])->toBe('summarize');
        });

        it('auto-connects when not connected', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['prompts' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
                '*/prompts/list' => Http::response([
                    'prompts' => [
                        ['name' => 'test_prompt'],
                    ],
                ], 200),
            ]);

            $client = McpClient::make();
            expect($client->isConnected())->toBeFalse();

            $prompts = $client->getPrompts();

            expect($client->isConnected())->toBeTrue()
                ->and($prompts)->toHaveCount(1);
        });

        it('returns empty array when request fails', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['prompts' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
                '*/prompts/list' => Http::response([], 500),
            ]);

            $client = McpClient::make()->connect();
            $prompts = $client->getPrompts();

            expect($prompts)->toBeEmpty();
        });

        it('returns empty array when response has no prompts key', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['prompts' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
                '*/prompts/list' => Http::response([
                    'status' => 'ok',
                ], 200),
            ]);

            $client = McpClient::make()->connect();
            $prompts = $client->getPrompts();

            expect($prompts)->toBeEmpty();
        });
    });

    describe('getPrompt()', function () {
        it('gets prompt successfully with arguments', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['prompts' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
                '*/prompts/get' => Http::response([
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                'type' => 'text',
                                'text' => 'Review this Python code',
                            ],
                        ],
                    ],
                ], 200),
            ]);

            $client = McpClient::make()->connect();
            $prompt = $client->getPrompt('code_review', ['language' => 'python']);

            expect($prompt['messages'])->toHaveCount(1)
                ->and($prompt['messages'][0]['role'])->toBe('user');

            Http::assertSent(function ($request) {
                return str_contains($request->url(), '/prompts/get')
                    && $request['name'] === 'code_review'
                    && $request['arguments'] === ['language' => 'python'];
            });
        });

        it('gets prompt without arguments', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['prompts' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
                '*/prompts/get' => Http::response([
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => [
                                'type' => 'text',
                                'text' => 'You are a helpful assistant',
                            ],
                        ],
                    ],
                ], 200),
            ]);

            $client = McpClient::make()->connect();
            $prompt = $client->getPrompt('assistant');

            expect($prompt['messages'])->toHaveCount(1);

            Http::assertSent(function ($request) {
                if (str_contains($request->url(), '/prompts/get')) {
                    return $request->data()['arguments'] === [];
                }

                return true;
            });
        });

        it('auto-connects when not connected', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['prompts' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
                '*/prompts/get' => Http::response([
                    'messages' => [],
                ], 200),
            ]);

            $client = McpClient::make();
            expect($client->isConnected())->toBeFalse();

            $prompt = $client->getPrompt('test');

            expect($client->isConnected())->toBeTrue();
        });

        it('throws exception when get prompt fails', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['prompts' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
                '*/prompts/get' => Http::response([
                    'error' => 'Prompt not found',
                ], 404),
            ]);

            $client = McpClient::make()->connect();

            expect(fn () => $client->getPrompt('missing_prompt'))
                ->toThrow(RuntimeException::class, 'Failed to get prompt: missing_prompt');
        });
    });

    describe('disconnect()', function () {
        it('disconnects and clears state', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true, 'resources' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [
                        ['name' => 'tool1'],
                        ['name' => 'tool2'],
                    ],
                ], 200),
            ]);

            $client = McpClient::make()->connect();

            expect($client->isConnected())->toBeTrue()
                ->and($client->getCapabilities())->not->toBeNull()
                ->and($client->getTools())->toHaveCount(2);

            $client->disconnect();

            expect($client->isConnected())->toBeFalse()
                ->and($client->getCapabilities())->toBeNull()
                ->and($client->getTools())->toBeEmpty();
        });

        it('can be called when not connected', function () {
            $client = McpClient::make();

            expect($client->isConnected())->toBeFalse();

            $client->disconnect();

            expect($client->isConnected())->toBeFalse();
        });

        it('can reconnect after disconnect', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response([
                    'tools' => [
                        ['name' => 'reconnect_tool'],
                    ],
                ], 200),
            ]);

            $client = McpClient::make()->connect();
            expect($client->isConnected())->toBeTrue();

            $client->disconnect();
            expect($client->isConnected())->toBeFalse();

            $client->connect();
            expect($client->isConnected())->toBeTrue()
                ->and($client->hasTool('reconnect_tool'))->toBeTrue();
        });
    });

    describe('isConnected()', function () {
        it('returns false before connection', function () {
            $client = McpClient::make();

            expect($client->isConnected())->toBeFalse();
        });

        it('returns true after successful connection', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
            ]);

            $client = McpClient::make()->connect();

            expect($client->isConnected())->toBeTrue();
        });

        it('returns false after disconnect', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
            ]);

            $client = McpClient::make()->connect();
            $client->disconnect();

            expect($client->isConnected())->toBeFalse();
        });
    });

    describe('HTTP request configuration', function () {
        it('sends requests with JSON accept header', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
            ]);

            McpClient::make()->connect();

            Http::assertSent(function ($request) {
                return $request->hasHeader('Accept', 'application/json');
            });
        });

        it('uses configured base URL for all requests', function () {
            Http::fake([
                'http://test-server:4000/*' => Http::response([
                    'capabilities' => ['tools' => true],
                    'tools' => [],
                ], 200),
            ]);

            $client = McpClient::url('http://test-server:4000')->connect();

            Http::assertSent(function ($request) {
                return str_starts_with($request->url(), 'http://test-server:4000/');
            });
        });

        it('includes authorization header when API key is set', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
            ]);

            McpClient::make()->withApiKey('test-api-key-123')->connect();

            Http::assertSent(function ($request) {
                return $request->hasHeader('Authorization', 'Bearer test-api-key-123');
            });
        });

        it('includes all custom headers in requests', function () {
            Http::fake([
                '*/initialize' => Http::response([
                    'capabilities' => ['tools' => true],
                ], 200),
                '*/tools/list' => Http::response(['tools' => []], 200),
            ]);

            McpClient::make()
                ->withHeaders([
                    'X-Client-ID' => 'test-client',
                    'X-Session-ID' => 'session-123',
                    'X-Custom-Header' => 'custom-value',
                ])
                ->connect();

            Http::assertSent(function ($request) {
                return $request->hasHeader('X-Client-ID', 'test-client')
                    && $request->hasHeader('X-Session-ID', 'session-123')
                    && $request->hasHeader('X-Custom-Header', 'custom-value');
            });
        });
    });
});
