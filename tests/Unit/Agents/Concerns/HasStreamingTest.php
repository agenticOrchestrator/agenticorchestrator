<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\Concerns\HasStreaming;
use AgenticOrchestrator\Streaming\StreamChunk;
use AgenticOrchestrator\Streaming\StreamResponse;

describe('HasStreaming', function () {

    beforeEach(function () {
        $this->streamingAgent = new class
        {
            use HasStreaming;

            protected bool $streamingEnabled = true;

            public function respond(string $message, array $context = []): object
            {
                return (object) ['content' => 'Hello world', 'usage' => [], 'model' => 'gpt-4o'];
            }

            public function testCreateStreamGenerator(string $message, array $context = []): Generator
            {
                return $this->createStreamGenerator($message, $context);
            }

            public function testWrapPrismStream(iterable $prismStream): Generator
            {
                return $this->wrapPrismStream($prismStream);
            }
        };
    });

    describe('enableStreaming / disableStreaming', function () {
        it('enables streaming with fluent return', function () {
            $this->streamingAgent->disableStreaming();
            $result = $this->streamingAgent->enableStreaming();

            expect($result)->toBe($this->streamingAgent);
            expect($this->streamingAgent->isStreamingEnabled())->toBeTrue();
        });

        it('disables streaming with fluent return', function () {
            $result = $this->streamingAgent->disableStreaming();

            expect($result)->toBe($this->streamingAgent);
            expect($this->streamingAgent->isStreamingEnabled())->toBeFalse();
        });
    });

    describe('isStreamingEnabled', function () {
        it('returns true by default', function () {
            expect($this->streamingAgent->isStreamingEnabled())->toBeTrue();
        });
    });

    describe('stream', function () {
        it('returns a StreamResponse instance', function () {
            $response = $this->streamingAgent->stream('Hello');

            expect($response)->toBeInstanceOf(StreamResponse::class);
        });
    });

    describe('streamWith', function () {
        it('returns a StreamResponse with callbacks attached', function () {
            $contentCalled = false;
            $toolCallCalled = false;
            $doneCalled = false;

            $response = $this->streamingAgent->streamWith(
                message: 'Hello',
                onContent: function () use (&$contentCalled) {
                    $contentCalled = true;
                },
                onToolCall: function () use (&$toolCallCalled) {
                    $toolCallCalled = true;
                },
                onDone: function () use (&$doneCalled) {
                    $doneCalled = true;
                },
            );

            expect($response)->toBeInstanceOf(StreamResponse::class);

            // Consume the stream to trigger callbacks
            foreach ($response as $chunk) {
                // iterate
            }

            expect($contentCalled)->toBeTrue();
            expect($doneCalled)->toBeTrue();
        });

        it('works with null callbacks', function () {
            $response = $this->streamingAgent->streamWith(
                message: 'Hello',
            );

            expect($response)->toBeInstanceOf(StreamResponse::class);
        });
    });

    describe('createStreamGenerator', function () {
        it('yields content chunks from a simulated streaming response', function () {
            $generator = $this->streamingAgent->testCreateStreamGenerator('test message');

            $chunks = [];
            foreach ($generator as $chunk) {
                $chunks[] = $chunk;
            }

            // Should have word chunks plus a done chunk
            expect(count($chunks))->toBeGreaterThanOrEqual(2);

            // Last chunk should be done
            $lastChunk = end($chunks);
            expect($lastChunk->type)->toBe(StreamChunk::TYPE_DONE);

            // First chunks should be content
            expect($chunks[0]->type)->toBe(StreamChunk::TYPE_CONTENT);
        });
    });

    describe('wrapPrismStream', function () {
        it('wraps content chunks from a prism stream', function () {
            $prismStream = [
                ['content' => 'Hello '],
                ['content' => 'World'],
            ];

            $generator = $this->streamingAgent->testWrapPrismStream($prismStream);
            $chunks = iterator_to_array($generator);

            expect(count($chunks))->toBe(2);
            expect($chunks[0]->type)->toBe(StreamChunk::TYPE_CONTENT);
            expect($chunks[0]->content)->toBe('Hello ');
            expect($chunks[1]->content)->toBe('World');
        });

        it('wraps delta content chunks', function () {
            $prismStream = [
                ['delta' => ['content' => 'delta text']],
            ];

            $generator = $this->streamingAgent->testWrapPrismStream($prismStream);
            $chunks = iterator_to_array($generator);

            expect(count($chunks))->toBe(1);
            expect($chunks[0]->content)->toBe('delta text');
        });

        it('wraps tool call chunks', function () {
            $toolCall = ['id' => 'tc1', 'function' => ['name' => 'search', 'arguments' => '{}']];
            $prismStream = [
                ['tool_calls' => [$toolCall]],
            ];

            $generator = $this->streamingAgent->testWrapPrismStream($prismStream);
            $chunks = iterator_to_array($generator);

            expect(count($chunks))->toBe(1);
            expect($chunks[0]->type)->toBe(StreamChunk::TYPE_TOOL_CALL);
        });

        it('wraps finish reason chunks', function () {
            $prismStream = [
                ['finish_reason' => 'stop', 'usage' => ['total_tokens' => 100]],
            ];

            $generator = $this->streamingAgent->testWrapPrismStream($prismStream);
            $chunks = iterator_to_array($generator);

            expect(count($chunks))->toBe(1);
            expect($chunks[0]->type)->toBe(StreamChunk::TYPE_DONE);
        });
    });
});
