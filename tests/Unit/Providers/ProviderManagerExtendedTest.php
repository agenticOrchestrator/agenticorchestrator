<?php

declare(strict_types=1);

use AgenticOrchestrator\Providers\ProviderManager;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Tool;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

describe('ProviderManager Extended Tests', function () {
    beforeEach(function () {
        $this->manager = new ProviderManager(
            providers: [
                'openai' => ['api_key' => 'test-key'],
                'anthropic' => ['api_key' => 'test-key'],
            ],
            defaultProvider: 'openai'
        );

        // Helper to invoke protected methods via reflection
        $this->invokeProtectedMethod = function (string $methodName, array $args = []) {
            $reflection = new ReflectionClass(ProviderManager::class);
            $method = $reflection->getMethod($methodName);
            $method->setAccessible(true);

            return $method->invokeArgs($this->manager, $args);
        };
    });

    describe('mapProviderName()', function () {
        it('maps openai to OpenAI provider', function () {
            $result = ($this->invokeProtectedMethod)('mapProviderName', ['openai']);

            expect($result)->toBe(Provider::OpenAI);
        });

        it('maps anthropic to Anthropic provider', function () {
            $result = ($this->invokeProtectedMethod)('mapProviderName', ['anthropic']);

            expect($result)->toBe(Provider::Anthropic);
        });

        it('maps gemini to Gemini provider', function () {
            $result = ($this->invokeProtectedMethod)('mapProviderName', ['gemini']);

            expect($result)->toBe(Provider::Gemini);
        });

        it('maps google to Gemini provider', function () {
            $result = ($this->invokeProtectedMethod)('mapProviderName', ['google']);

            expect($result)->toBe(Provider::Gemini);
        });

        it('maps mistral to Mistral provider', function () {
            $result = ($this->invokeProtectedMethod)('mapProviderName', ['mistral']);

            expect($result)->toBe(Provider::Mistral);
        });

        it('maps ollama to Ollama provider', function () {
            $result = ($this->invokeProtectedMethod)('mapProviderName', ['ollama']);

            expect($result)->toBe(Provider::Ollama);
        });

        it('maps groq to Groq provider', function () {
            $result = ($this->invokeProtectedMethod)('mapProviderName', ['groq']);

            expect($result)->toBe(Provider::Groq);
        });

        it('maps xai to XAI provider', function () {
            $result = ($this->invokeProtectedMethod)('mapProviderName', ['xai']);

            expect($result)->toBe(Provider::XAI);
        });

        it('maps deepseek to DeepSeek provider', function () {
            $result = ($this->invokeProtectedMethod)('mapProviderName', ['deepseek']);

            expect($result)->toBe(Provider::DeepSeek);
        });

        it('maps unknown provider to OpenAI as default', function () {
            $result = ($this->invokeProtectedMethod)('mapProviderName', ['unknown_provider']);

            expect($result)->toBe(Provider::OpenAI);
        });

        it('handles case insensitive provider names', function () {
            $result = ($this->invokeProtectedMethod)('mapProviderName', ['OPENAI']);

            expect($result)->toBe(Provider::OpenAI);
        });

        it('handles mixed case provider names', function () {
            $result = ($this->invokeProtectedMethod)('mapProviderName', ['AnThRoPiC']);

            expect($result)->toBe(Provider::Anthropic);
        });
    });

    describe('convertMessages()', function () {
        it('converts system message correctly', function () {
            $messages = [
                ['role' => 'system', 'content' => 'You are a helpful assistant'],
            ];

            $result = ($this->invokeProtectedMethod)('convertMessages', [$messages]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(SystemMessage::class)
                ->and($result[0]->content)->toBe('You are a helpful assistant');
        });

        it('converts user message correctly', function () {
            $messages = [
                ['role' => 'user', 'content' => 'Hello, how are you?'],
            ];

            $result = ($this->invokeProtectedMethod)('convertMessages', [$messages]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(UserMessage::class)
                ->and($result[0]->content)->toBe('Hello, how are you?');
        });

        it('converts assistant message without tool calls correctly', function () {
            $messages = [
                ['role' => 'assistant', 'content' => 'I am doing well, thank you!'],
            ];

            $result = ($this->invokeProtectedMethod)('convertMessages', [$messages]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(AssistantMessage::class)
                ->and($result[0]->content)->toBe('I am doing well, thank you!');
        });

        it('converts assistant message with tool calls correctly', function () {
            $toolCalls = [
                [
                    'id' => 'call_123',
                    'type' => 'function',
                    'function' => ['name' => 'get_weather', 'arguments' => '{"location":"NYC"}'],
                ],
            ];

            $messages = [
                ['role' => 'assistant', 'content' => 'Let me check the weather', 'tool_calls' => $toolCalls],
            ];

            $result = ($this->invokeProtectedMethod)('convertMessages', [$messages]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(AssistantMessage::class)
                ->and($result[0]->content)->toBe('Let me check the weather');
        });

        it('converts tool message correctly', function () {
            $messages = [
                [
                    'role' => 'tool',
                    'content' => '{"temperature": 72, "condition": "sunny"}',
                    'tool_call_id' => 'call_123',
                    'tool_name' => 'get_weather',
                ],
            ];

            $result = ($this->invokeProtectedMethod)('convertMessages', [$messages]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(ToolResultMessage::class);
        });

        it('converts tool message with missing tool_call_id', function () {
            $messages = [
                [
                    'role' => 'tool',
                    'content' => 'Tool result',
                ],
            ];

            $result = ($this->invokeProtectedMethod)('convertMessages', [$messages]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(ToolResultMessage::class);
        });

        it('converts tool message with missing tool_name', function () {
            $messages = [
                [
                    'role' => 'tool',
                    'content' => 'Tool result',
                    'tool_call_id' => 'call_456',
                ],
            ];

            $result = ($this->invokeProtectedMethod)('convertMessages', [$messages]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(ToolResultMessage::class);
        });

        it('converts unknown role to user message as default', function () {
            $messages = [
                ['role' => 'unknown_role', 'content' => 'Some content'],
            ];

            $result = ($this->invokeProtectedMethod)('convertMessages', [$messages]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(UserMessage::class)
                ->and($result[0]->content)->toBe('Some content');
        });

        it('converts multiple messages in correct order', function () {
            $messages = [
                ['role' => 'system', 'content' => 'System prompt'],
                ['role' => 'user', 'content' => 'User message'],
                ['role' => 'assistant', 'content' => 'Assistant response'],
            ];

            $result = ($this->invokeProtectedMethod)('convertMessages', [$messages]);

            expect($result)->toHaveCount(3)
                ->and($result[0])->toBeInstanceOf(SystemMessage::class)
                ->and($result[1])->toBeInstanceOf(UserMessage::class)
                ->and($result[2])->toBeInstanceOf(AssistantMessage::class);
        });

        it('handles empty messages array', function () {
            $messages = [];

            $result = ($this->invokeProtectedMethod)('convertMessages', [$messages]);

            expect($result)->toBeArray()
                ->and($result)->toHaveCount(0);
        });
    });

    describe('convertTools()', function () {
        it('converts tool with string parameter correctly', function () {
            $tools = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_weather',
                        'description' => 'Get current weather',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'location' => [
                                    'type' => 'string',
                                    'description' => 'City name',
                                ],
                            ],
                            'required' => ['location'],
                        ],
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('convertTools', [$tools]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(Tool::class);
        });

        it('converts tool with number parameter correctly', function () {
            $tools = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'calculate',
                        'description' => 'Perform calculation',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'value' => [
                                    'type' => 'number',
                                    'description' => 'Number value',
                                ],
                            ],
                            'required' => ['value'],
                        ],
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('convertTools', [$tools]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(Tool::class);
        });

        it('converts tool with integer parameter correctly', function () {
            $tools = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'count',
                        'description' => 'Count items',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'count' => [
                                    'type' => 'integer',
                                    'description' => 'Integer count',
                                ],
                            ],
                            'required' => ['count'],
                        ],
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('convertTools', [$tools]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(Tool::class);
        });

        it('converts tool with boolean parameter correctly', function () {
            $tools = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'toggle',
                        'description' => 'Toggle setting',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'enabled' => [
                                    'type' => 'boolean',
                                    'description' => 'Enable or disable',
                                ],
                            ],
                            'required' => ['enabled'],
                        ],
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('convertTools', [$tools]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(Tool::class);
        });

        it('converts tool with unknown parameter type as string', function () {
            $tools = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'custom',
                        'description' => 'Custom function',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'custom_param' => [
                                    'type' => 'unknown_type',
                                    'description' => 'Custom parameter',
                                ],
                            ],
                            'required' => ['custom_param'],
                        ],
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('convertTools', [$tools]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(Tool::class);
        });

        it('converts tool with multiple parameters of mixed types', function () {
            $tools = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'complex_tool',
                        'description' => 'Tool with multiple parameters',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string', 'description' => 'Name'],
                                'age' => ['type' => 'integer', 'description' => 'Age'],
                                'score' => ['type' => 'number', 'description' => 'Score'],
                                'active' => ['type' => 'boolean', 'description' => 'Active status'],
                            ],
                            'required' => ['name', 'age'],
                        ],
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('convertTools', [$tools]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(Tool::class);
        });

        it('converts tool with optional parameters correctly', function () {
            $tools = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'optional_params',
                        'description' => 'Tool with optional parameters',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'required_param' => ['type' => 'string', 'description' => 'Required'],
                                'optional_param' => ['type' => 'string', 'description' => 'Optional'],
                            ],
                            'required' => ['required_param'],
                        ],
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('convertTools', [$tools]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(Tool::class);
        });

        it('converts tool without parameters correctly', function () {
            $tools = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'no_params',
                        'description' => 'Tool without parameters',
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('convertTools', [$tools]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(Tool::class);
        });

        it('converts tool with missing properties in parameters', function () {
            $tools = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'missing_props',
                        'description' => 'Tool with missing properties',
                        'parameters' => [
                            'type' => 'object',
                            'required' => ['something'],
                        ],
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('convertTools', [$tools]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(Tool::class);
        });

        it('converts tool with parameter missing description', function () {
            $tools = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'no_desc',
                        'description' => 'Tool with parameter missing description',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'param' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('convertTools', [$tools]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(Tool::class);
        });

        it('converts tool with parameter missing type', function () {
            $tools = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'no_type',
                        'description' => 'Tool with parameter missing type',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'param' => ['description' => 'A parameter'],
                            ],
                        ],
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('convertTools', [$tools]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(Tool::class);
        });

        it('converts multiple tools correctly', function () {
            $tools = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'tool_one',
                        'description' => 'First tool',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'param1' => ['type' => 'string', 'description' => 'Parameter 1'],
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'tool_two',
                        'description' => 'Second tool',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'param2' => ['type' => 'number', 'description' => 'Parameter 2'],
                            ],
                        ],
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('convertTools', [$tools]);

            expect($result)->toHaveCount(2)
                ->and($result[0])->toBeInstanceOf(Tool::class)
                ->and($result[1])->toBeInstanceOf(Tool::class);
        });

        it('handles empty tools array', function () {
            $tools = [];

            $result = ($this->invokeProtectedMethod)('convertTools', [$tools]);

            expect($result)->toBeArray()
                ->and($result)->toHaveCount(0);
        });

        it('converts tool with parameters as non-array', function () {
            $tools = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'invalid_params',
                        'description' => 'Tool with invalid parameters',
                        'parameters' => 'not_an_array',
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('convertTools', [$tools]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(Tool::class);
        });

        it('converts tool with properties as non-array', function () {
            $tools = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'invalid_properties',
                        'description' => 'Tool with invalid properties',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => 'not_an_array',
                        ],
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('convertTools', [$tools]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBeInstanceOf(Tool::class);
        });
    });

    describe('extractToolCalls()', function () {
        it('returns empty array when response has no toolCalls property', function () {
            $response = (object) [
                'text' => 'Some text',
                'finishReason' => 'stop',
            ];

            $result = ($this->invokeProtectedMethod)('extractToolCalls', [$response]);

            expect($result)->toBeArray()
                ->and($result)->toHaveCount(0);
        });

        it('returns empty array when toolCalls is empty array', function () {
            $response = (object) [
                'text' => 'Some text',
                'toolCalls' => [],
            ];

            $result = ($this->invokeProtectedMethod)('extractToolCalls', [$response]);

            expect($result)->toBeArray()
                ->and($result)->toHaveCount(0);
        });

        it('extracts tool call with string arguments correctly', function () {
            $response = (object) [
                'toolCalls' => [
                    (object) [
                        'id' => 'call_123',
                        'name' => 'get_weather',
                        'arguments' => '{"location":"NYC"}',
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('extractToolCalls', [$response]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBe([
                    'id' => 'call_123',
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_weather',
                        'arguments' => '{"location":"NYC"}',
                    ],
                ]);
        });

        it('extracts tool call with array arguments and converts to JSON', function () {
            $response = (object) [
                'toolCalls' => [
                    (object) [
                        'id' => 'call_456',
                        'name' => 'calculate',
                        'arguments' => ['x' => 10, 'y' => 20],
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('extractToolCalls', [$response]);

            expect($result)->toHaveCount(1)
                ->and($result[0])->toBe([
                    'id' => 'call_456',
                    'type' => 'function',
                    'function' => [
                        'name' => 'calculate',
                        'arguments' => '{"x":10,"y":20}',
                    ],
                ]);
        });

        it('generates unique id when tool call has no id', function () {
            $response = (object) [
                'toolCalls' => [
                    (object) [
                        'name' => 'no_id_tool',
                        'arguments' => '{"param":"value"}',
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('extractToolCalls', [$response]);

            expect($result)->toHaveCount(1)
                ->and($result[0]['id'])->toStartWith('call_')
                ->and($result[0]['type'])->toBe('function')
                ->and($result[0]['function']['name'])->toBe('no_id_tool')
                ->and($result[0]['function']['arguments'])->toBe('{"param":"value"}');
        });

        it('extracts multiple tool calls correctly', function () {
            $response = (object) [
                'toolCalls' => [
                    (object) [
                        'id' => 'call_1',
                        'name' => 'tool_one',
                        'arguments' => '{"a":"b"}',
                    ],
                    (object) [
                        'id' => 'call_2',
                        'name' => 'tool_two',
                        'arguments' => ['c' => 'd'],
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('extractToolCalls', [$response]);

            expect($result)->toHaveCount(2)
                ->and($result[0]['id'])->toBe('call_1')
                ->and($result[0]['function']['name'])->toBe('tool_one')
                ->and($result[1]['id'])->toBe('call_2')
                ->and($result[1]['function']['name'])->toBe('tool_two')
                ->and($result[1]['function']['arguments'])->toBe('{"c":"d"}');
        });

        it('handles tool call with empty arguments object', function () {
            $response = (object) [
                'toolCalls' => [
                    (object) [
                        'id' => 'call_empty',
                        'name' => 'no_args_tool',
                        'arguments' => [],
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('extractToolCalls', [$response]);

            expect($result)->toHaveCount(1)
                ->and($result[0]['function']['arguments'])->toBe('[]');
        });

        it('handles tool call with complex nested arguments', function () {
            $response = (object) [
                'toolCalls' => [
                    (object) [
                        'id' => 'call_complex',
                        'name' => 'complex_tool',
                        'arguments' => [
                            'nested' => [
                                'level1' => [
                                    'level2' => 'value',
                                ],
                            ],
                            'array' => [1, 2, 3],
                        ],
                    ],
                ],
            ];

            $result = ($this->invokeProtectedMethod)('extractToolCalls', [$response]);

            expect($result)->toHaveCount(1)
                ->and($result[0]['function']['arguments'])->toBe('{"nested":{"level1":{"level2":"value"}},"array":[1,2,3]}');
        });
    });

    describe('chat() error handling', function () {
        it('wraps provider exceptions in RuntimeException', function () {
            $manager = new ProviderManager(
                providers: ['invalid' => ['api_key' => 'invalid']],
                defaultProvider: 'invalid'
            );

            expect(fn () => $manager->chat(
                provider: 'invalid',
                model: 'invalid-model',
                messages: [['role' => 'user', 'content' => 'test']],
                tools: [],
                temperature: 0.7,
                maxTokens: null
            ))->toThrow(RuntimeException::class);
        });

        it('includes provider name in error message', function () {
            $manager = new ProviderManager(
                providers: ['test_provider' => ['api_key' => 'invalid']],
                defaultProvider: 'test_provider'
            );

            try {
                $manager->chat(
                    provider: 'test_provider',
                    model: 'invalid-model',
                    messages: [['role' => 'user', 'content' => 'test']],
                    tools: [],
                    temperature: 0.7,
                    maxTokens: null
                );

                expect(true)->toBeFalse('Exception should have been thrown');
            } catch (RuntimeException $e) {
                expect($e->getMessage())->toContain('test_provider');
            }
        });

        it('preserves original exception as previous', function () {
            $manager = new ProviderManager(
                providers: ['error_provider' => ['api_key' => 'invalid']],
                defaultProvider: 'error_provider'
            );

            try {
                $manager->chat(
                    provider: 'error_provider',
                    model: 'invalid-model',
                    messages: [['role' => 'user', 'content' => 'test']],
                    tools: [],
                    temperature: 0.7,
                    maxTokens: null
                );

                expect(true)->toBeFalse('Exception should have been thrown');
            } catch (RuntimeException $e) {
                expect($e->getPrevious())->not->toBeNull();
            }
        });
    });
});
