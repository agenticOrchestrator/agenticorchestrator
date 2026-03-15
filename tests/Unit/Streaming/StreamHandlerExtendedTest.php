<?php

declare(strict_types=1);

use AgenticOrchestrator\Streaming\StreamChunk;
use AgenticOrchestrator\Streaming\StreamHandler;
use AgenticOrchestrator\Streaming\StreamResponse;

function createStreamFromChunks(array $chunks): StreamResponse
{
    $generator = (function () use ($chunks) {
        foreach ($chunks as $chunk) {
            yield $chunk;
        }
    })();

    return new StreamResponse($generator);
}

describe('StreamHandler Extended', function () {
    describe('toSSEResponse headers', function () {
        it('sets X-Accel-Buffering header to no for nginx proxy support', function () {
            $stream = createStreamFromChunks([StreamChunk::done()]);
            $handler = StreamHandler::for($stream);
            $response = $handler->toSSEResponse();

            expect($response->headers->get('X-Accel-Buffering'))->toBe('no');
        });
    });

    describe('toJSONLResponse', function () {
        it('does not include X-Accel-Buffering header', function () {
            $stream = createStreamFromChunks([StreamChunk::done()]);
            $handler = StreamHandler::for($stream);
            $response = $handler->toJSONLResponse();

            expect($response->headers->has('X-Accel-Buffering'))->toBeFalse();
        });
    });

    describe('toTextResponse', function () {
        it('does not include X-Accel-Buffering header', function () {
            $stream = createStreamFromChunks([StreamChunk::done()]);
            $handler = StreamHandler::for($stream);
            $response = $handler->toTextResponse();

            expect($response->headers->has('X-Accel-Buffering'))->toBeFalse();
        });
    });

    describe('onFlush callback', function () {
        it('returns same instance for method chaining', function () {
            $stream = createStreamFromChunks([StreamChunk::done()]);
            $handler = StreamHandler::for($stream);
            $flushed = false;

            $result = $handler->onFlush(function () use (&$flushed) {
                $flushed = true;
            });

            expect($result)->toBe($handler);
        });
    });

    describe('bufferSize fluency', function () {
        it('returns same instance for method chaining', function () {
            $stream = createStreamFromChunks([StreamChunk::done()]);
            $handler = StreamHandler::for($stream);

            $result = $handler->bufferSize(2048);

            expect($result)->toBe($handler);
        });
    });

    describe('buffered edge cases', function () {
        it('handles single chunk exactly at buffer size boundary', function () {
            $content = str_repeat('x', 50);
            $stream = createStreamFromChunks([
                StreamChunk::content($content, 0),
                StreamChunk::done([], 1),
            ]);

            $handler = StreamHandler::for($stream);
            $buffered = [];

            foreach ($handler->buffered(50) as $chunk) {
                $buffered[] = $chunk;
            }

            expect($buffered)->toHaveCount(1)
                ->and(strlen($buffered[0]))->toBe(50);
        });

        it('handles many small chunks into one buffer', function () {
            $chunks = [];
            for ($i = 0; $i < 10; $i++) {
                $chunks[] = StreamChunk::content('ab', $i);
            }
            $chunks[] = StreamChunk::done([], 10);

            $stream = createStreamFromChunks($chunks);
            $handler = StreamHandler::for($stream);
            $buffered = [];

            foreach ($handler->buffered(100) as $chunk) {
                $buffered[] = $chunk;
            }

            // 10 * 2 = 20 chars, all under 100 buffer size, yielded as remainder
            expect($buffered)->toHaveCount(1)
                ->and($buffered[0])->toBe('abababababababababab');
        });

        it('handles empty stream producing no output', function () {
            $stream = createStreamFromChunks([
                StreamChunk::done([], 0),
            ]);

            $handler = StreamHandler::for($stream);
            $buffered = [];

            foreach ($handler->buffered(10) as $chunk) {
                $buffered[] = $chunk;
            }

            expect($buffered)->toBeEmpty();
        });
    });

    describe('transform edge cases', function () {
        it('handles transformer that returns null for all chunks', function () {
            $stream = createStreamFromChunks([
                StreamChunk::content('a', 0),
                StreamChunk::content('b', 1),
                StreamChunk::done([], 2),
            ]);

            $handler = StreamHandler::for($stream);
            $transformed = [];

            foreach ($handler->transform(fn () => null) as $chunk) {
                $transformed[] = $chunk;
            }

            expect($transformed)->toBeEmpty();
        });

        it('handles transformer that passes all chunks through', function () {
            $stream = createStreamFromChunks([
                StreamChunk::content('hello', 0),
                StreamChunk::done([], 1),
            ]);

            $handler = StreamHandler::for($stream);
            $transformed = [];

            foreach ($handler->transform(fn (StreamChunk $c) => $c) as $chunk) {
                $transformed[] = $chunk;
            }

            expect($transformed)->toHaveCount(2);
        });
    });

    describe('filter edge cases', function () {
        it('handles filtering with empty type array', function () {
            $stream = createStreamFromChunks([
                StreamChunk::content('text', 0),
                StreamChunk::done([], 1),
            ]);

            $handler = StreamHandler::for($stream);
            $chunks = [];

            foreach ($handler->filter([]) as $chunk) {
                $chunks[] = $chunk;
            }

            expect($chunks)->toBeEmpty();
        });

        it('handles filtering for done type', function () {
            $stream = createStreamFromChunks([
                StreamChunk::content('text', 0),
                StreamChunk::toolCall(['name' => 'tool'], 1),
                StreamChunk::done([], 2),
            ]);

            $handler = StreamHandler::for($stream);
            $chunks = [];

            foreach ($handler->filter('done') as $chunk) {
                $chunks[] = $chunk;
            }

            expect($chunks)->toHaveCount(1)
                ->and($chunks[0]->type)->toBe(StreamChunk::TYPE_DONE);
        });

        it('handles filtering for tool_call type', function () {
            $stream = createStreamFromChunks([
                StreamChunk::content('text', 0),
                StreamChunk::toolCall(['name' => 'search'], 1),
                StreamChunk::toolCall(['name' => 'fetch'], 2),
                StreamChunk::done([], 3),
            ]);

            $handler = StreamHandler::for($stream);
            $chunks = [];

            foreach ($handler->filter('tool_call') as $chunk) {
                $chunks[] = $chunk;
            }

            expect($chunks)->toHaveCount(2);
        });
    });

    describe('contentOnly edge cases', function () {
        it('handles stream with only non-content chunks', function () {
            $stream = createStreamFromChunks([
                StreamChunk::toolCall(['name' => 'tool'], 0),
                StreamChunk::error('something', 1),
                StreamChunk::done([], 2),
            ]);

            $handler = StreamHandler::for($stream);
            $texts = [];

            foreach ($handler->contentOnly() as $text) {
                $texts[] = $text;
            }

            expect($texts)->toBeEmpty();
        });

        it('preserves exact content including whitespace', function () {
            $stream = createStreamFromChunks([
                StreamChunk::content('  hello  ', 0),
                StreamChunk::content("\nworld\n", 1),
                StreamChunk::done([], 2),
            ]);

            $handler = StreamHandler::for($stream);
            $texts = [];

            foreach ($handler->contentOnly() as $text) {
                $texts[] = $text;
            }

            expect($texts)->toBe(['  hello  ', "\nworld\n"]);
        });
    });

    describe('chaining operations', function () {
        it('allows creating handler then setting buffer and flush', function () {
            $stream = createStreamFromChunks([StreamChunk::done()]);

            $handler = StreamHandler::for($stream)
                ->bufferSize(1024)
                ->onFlush(function () {});

            expect($handler)->toBeInstanceOf(StreamHandler::class);
        });
    });
});
