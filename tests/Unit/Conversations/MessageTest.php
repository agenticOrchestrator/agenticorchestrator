<?php

declare(strict_types=1);

use AgenticOrchestrator\Conversations\Message;
use AgenticOrchestrator\Conversations\MessageRole;
use Illuminate\Support\Carbon;

describe('Message', function () {

    describe('static constructors', function () {

        describe('user', function () {
            it('creates a user message with role User', function () {
                $message = Message::user('Hello agent');

                expect($message->role)->toBe(MessageRole::User);
                expect($message->content)->toBe('Hello agent');
                expect($message->id)->toStartWith('msg_');
                expect($message->createdAt)->toBeInstanceOf(Carbon::class);
                expect($message->toolCalls)->toBeNull();
                expect($message->toolCallId)->toBeNull();
            });

            it('accepts metadata', function () {
                $message = Message::user('Hello', ['source' => 'web']);

                expect($message->metadata)->toBe(['source' => 'web']);
            });
        });

        describe('assistant', function () {
            it('creates an assistant message with role Assistant', function () {
                $message = Message::assistant('I can help');

                expect($message->role)->toBe(MessageRole::Assistant);
                expect($message->content)->toBe('I can help');
                expect($message->id)->toStartWith('msg_');
                expect($message->createdAt)->toBeInstanceOf(Carbon::class);
            });

            it('accepts tool calls', function () {
                $toolCalls = [
                    [
                        'id' => 'tc1',
                        'type' => 'function',
                        'function' => ['name' => 'search', 'arguments' => '{}'],
                    ],
                ];

                $message = Message::assistant('Searching...', $toolCalls);

                expect($message->toolCalls)->toBe($toolCalls);
            });

            it('accepts metadata', function () {
                $message = Message::assistant('Response', null, ['model' => 'gpt-4o']);

                expect($message->metadata)->toBe(['model' => 'gpt-4o']);
            });
        });

        describe('system', function () {
            it('creates a system message with role System', function () {
                $message = Message::system('You are a helpful assistant');

                expect($message->role)->toBe(MessageRole::System);
                expect($message->content)->toBe('You are a helpful assistant');
                expect($message->id)->toStartWith('msg_');
                expect($message->createdAt)->toBeInstanceOf(Carbon::class);
            });
        });

        describe('tool', function () {
            it('creates a tool result message with role Tool', function () {
                $message = Message::tool('tc1', '{"result": 42}');

                expect($message->role)->toBe(MessageRole::Tool);
                expect($message->content)->toBe('{"result": 42}');
                expect($message->toolCallId)->toBe('tc1');
                expect($message->id)->toStartWith('msg_');
                expect($message->createdAt)->toBeInstanceOf(Carbon::class);
            });

            it('accepts metadata', function () {
                $message = Message::tool('tc1', 'result', ['duration' => 150]);

                expect($message->metadata)->toBe(['duration' => 150]);
            });
        });
    });

    describe('hasToolCalls', function () {
        it('returns false when no tool calls', function () {
            $message = Message::user('Hello');

            expect($message->hasToolCalls())->toBeFalse();
        });

        it('returns false when tool calls is null', function () {
            $message = Message::assistant('Response');

            expect($message->hasToolCalls())->toBeFalse();
        });

        it('returns true when tool calls exist', function () {
            $message = Message::assistant('Response', [
                ['id' => 'tc1', 'type' => 'function', 'function' => ['name' => 'test', 'arguments' => '{}']],
            ]);

            expect($message->hasToolCalls())->toBeTrue();
        });

        it('returns false when tool calls array is empty', function () {
            $message = Message::assistant('Response', []);

            expect($message->hasToolCalls())->toBeFalse();
        });
    });

    describe('getMeta', function () {
        it('returns a metadata value by key', function () {
            $message = Message::user('Hello', ['source' => 'api', 'version' => 2]);

            expect($message->getMeta('source'))->toBe('api');
            expect($message->getMeta('version'))->toBe(2);
        });

        it('returns default when key does not exist', function () {
            $message = Message::user('Hello');

            expect($message->getMeta('missing'))->toBeNull();
            expect($message->getMeta('missing', 'fallback'))->toBe('fallback');
        });
    });

    describe('toApiFormat', function () {
        it('returns basic role and content', function () {
            $message = Message::user('Hello');
            $api = $message->toApiFormat();

            expect($api)->toHaveKey('role', 'user');
            expect($api)->toHaveKey('content', 'Hello');
            expect($api)->not->toHaveKey('tool_calls');
            expect($api)->not->toHaveKey('tool_call_id');
        });

        it('includes tool_calls when present', function () {
            $toolCalls = [
                ['id' => 'tc1', 'type' => 'function', 'function' => ['name' => 'test', 'arguments' => '{}']],
            ];
            $message = Message::assistant('Response', $toolCalls);
            $api = $message->toApiFormat();

            expect($api)->toHaveKey('tool_calls');
            expect($api['tool_calls'])->toBe($toolCalls);
        });

        it('includes tool_call_id when present', function () {
            $message = Message::tool('tc1', 'result data');
            $api = $message->toApiFormat();

            expect($api)->toHaveKey('tool_call_id', 'tc1');
        });
    });

    describe('toArray', function () {
        it('returns all fields as an array', function () {
            $message = Message::user('Hello', ['source' => 'test']);
            $array = $message->toArray();

            expect($array)->toHaveKey('id');
            expect($array)->toHaveKey('role', 'user');
            expect($array)->toHaveKey('content', 'Hello');
            expect($array)->toHaveKey('tool_calls');
            expect($array)->toHaveKey('tool_call_id');
            expect($array)->toHaveKey('tokens');
            expect($array)->toHaveKey('metadata');
            expect($array)->toHaveKey('created_at');
            expect($array['metadata'])->toBe(['source' => 'test']);
        });
    });

    describe('jsonSerialize', function () {
        it('returns the same as toArray', function () {
            $message = Message::user('Hello');

            expect($message->jsonSerialize())->toBe($message->toArray());
        });
    });

    describe('fromArray', function () {
        it('reconstructs a message from an array', function () {
            $data = [
                'role' => 'user',
                'content' => 'Hello from array',
                'id' => 'msg_custom123',
                'metadata' => ['key' => 'value'],
            ];

            $message = Message::fromArray($data);

            expect($message->role)->toBe(MessageRole::User);
            expect($message->content)->toBe('Hello from array');
            expect($message->id)->toBe('msg_custom123');
            expect($message->metadata)->toBe(['key' => 'value']);
        });

        it('handles tool call data', function () {
            $toolCalls = [
                ['id' => 'tc1', 'type' => 'function', 'function' => ['name' => 'test', 'arguments' => '{}']],
            ];

            $data = [
                'role' => 'assistant',
                'content' => 'Calling tool',
                'tool_calls' => $toolCalls,
            ];

            $message = Message::fromArray($data);

            expect($message->role)->toBe(MessageRole::Assistant);
            expect($message->toolCalls)->toBe($toolCalls);
        });

        it('handles tool result data', function () {
            $data = [
                'role' => 'tool',
                'content' => '{"result": 42}',
                'tool_call_id' => 'tc1',
            ];

            $message = Message::fromArray($data);

            expect($message->role)->toBe(MessageRole::Tool);
            expect($message->toolCallId)->toBe('tc1');
        });

        it('parses created_at from ISO string', function () {
            $data = [
                'role' => 'user',
                'content' => 'Hello',
                'created_at' => '2025-06-15T10:30:00+00:00',
            ];

            $message = Message::fromArray($data);

            expect($message->createdAt)->toBeInstanceOf(Carbon::class);
            expect($message->createdAt->year)->toBe(2025);
        });

        it('handles missing optional fields gracefully', function () {
            $data = [
                'role' => 'system',
                'content' => 'System prompt',
            ];

            $message = Message::fromArray($data);

            expect($message->role)->toBe(MessageRole::System);
            expect($message->content)->toBe('System prompt');
            expect($message->id)->toBeNull();
            expect($message->toolCalls)->toBeNull();
            expect($message->toolCallId)->toBeNull();
            expect($message->tokens)->toBeNull();
            expect($message->metadata)->toBe([]);
            expect($message->createdAt)->toBeNull();
        });
    });

    describe('roundtrip serialization', function () {
        it('survives toArray -> fromArray roundtrip', function () {
            $original = Message::user('Roundtrip test', ['tag' => 'test']);
            $array = $original->toArray();
            $restored = Message::fromArray($array);

            expect($restored->role)->toBe($original->role);
            expect($restored->content)->toBe($original->content);
            expect($restored->id)->toBe($original->id);
            expect($restored->metadata)->toBe($original->metadata);
        });
    });
});
