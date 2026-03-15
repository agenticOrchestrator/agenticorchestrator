<?php

declare(strict_types=1);

use AgenticOrchestrator\Streaming\StreamChunk;
use AgenticOrchestrator\Streaming\StreamHandler;
use AgenticOrchestrator\Streaming\StreamResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

function createTestStream(array $chunks): StreamResponse
{
    $generator = (function () use ($chunks) {
        foreach ($chunks as $chunk) {
            yield $chunk;
        }
    })();

    return new StreamResponse($generator);
}

describe('construction and factory', function () {
    it('creates handler via constructor', function () {
        $stream = createTestStream([StreamChunk::done()]);
        $handler = new StreamHandler($stream);

        expect($handler)->toBeInstanceOf(StreamHandler::class);
    });

    it('creates handler via static for method', function () {
        $stream = createTestStream([StreamChunk::done()]);
        $handler = StreamHandler::for($stream);

        expect($handler)->toBeInstanceOf(StreamHandler::class);
    });
});

describe('bufferSize', function () {
    it('sets buffer size fluently', function () {
        $stream = createTestStream([StreamChunk::done()]);
        $handler = StreamHandler::for($stream);

        $result = $handler->bufferSize(1024);

        expect($result)->toBeInstanceOf(StreamHandler::class);
    });
});

describe('onFlush', function () {
    it('sets flush callback fluently', function () {
        $stream = createTestStream([StreamChunk::done()]);
        $handler = StreamHandler::for($stream);

        $result = $handler->onFlush(function () {});

        expect($result)->toBeInstanceOf(StreamHandler::class);
    });
});

describe('toSSEResponse', function () {
    it('returns a StreamedResponse with correct headers', function () {
        $stream = createTestStream([
            StreamChunk::content('Hello', 0),
            StreamChunk::done([], 1),
        ]);

        $handler = StreamHandler::for($stream);
        $response = $handler->toSSEResponse();

        expect($response)->toBeInstanceOf(StreamedResponse::class);
        expect($response->getStatusCode())->toBe(200);
        expect($response->headers->get('Content-Type'))->toBe('text/event-stream');
        expect($response->headers->get('Cache-Control'))->toContain('no-cache');
        expect($response->headers->get('Connection'))->toBe('keep-alive');
        expect($response->headers->get('X-Accel-Buffering'))->toBe('no');
    });
});

describe('toJSONLResponse', function () {
    it('returns a StreamedResponse with correct headers', function () {
        $stream = createTestStream([
            StreamChunk::content('Hello', 0),
            StreamChunk::done([], 1),
        ]);

        $handler = StreamHandler::for($stream);
        $response = $handler->toJSONLResponse();

        expect($response)->toBeInstanceOf(StreamedResponse::class);
        expect($response->getStatusCode())->toBe(200);
        expect($response->headers->get('Content-Type'))->toBe('application/x-ndjson');
        expect($response->headers->get('Cache-Control'))->toContain('no-cache');
        expect($response->headers->get('Connection'))->toBe('keep-alive');
    });
});

describe('toTextResponse', function () {
    it('returns a StreamedResponse with correct headers', function () {
        $stream = createTestStream([
            StreamChunk::content('Hello', 0),
            StreamChunk::done([], 1),
        ]);

        $handler = StreamHandler::for($stream);
        $response = $handler->toTextResponse();

        expect($response)->toBeInstanceOf(StreamedResponse::class);
        expect($response->getStatusCode())->toBe(200);
        expect($response->headers->get('Content-Type'))->toBe('text/plain');
        expect($response->headers->get('Cache-Control'))->toContain('no-cache');
        expect($response->headers->get('Connection'))->toBe('keep-alive');
    });
});

describe('buffered', function () {
    it('buffers content chunks and yields when buffer fills', function () {
        $stream = createTestStream([
            StreamChunk::content('Hello', 0),
            StreamChunk::content(' World', 1),
            StreamChunk::content('!', 2),
            StreamChunk::done([], 3),
        ]);

        $handler = StreamHandler::for($stream);
        $buffered = [];

        foreach ($handler->buffered(10) as $chunk) {
            $buffered[] = $chunk;
        }

        // "Hello World" = 11 chars >= 10, then "!" is remainder
        expect($buffered)->toHaveCount(2);
        expect($buffered[0])->toBe('Hello World');
        expect($buffered[1])->toBe('!');
    });

    it('yields remaining buffer at end', function () {
        $stream = createTestStream([
            StreamChunk::content('Hi', 0),
            StreamChunk::done([], 1),
        ]);

        $handler = StreamHandler::for($stream);
        $buffered = [];

        foreach ($handler->buffered(100) as $chunk) {
            $buffered[] = $chunk;
        }

        expect($buffered)->toHaveCount(1);
        expect($buffered[0])->toBe('Hi');
    });

    it('yields nothing when no content chunks', function () {
        $stream = createTestStream([
            StreamChunk::toolCall(['name' => 'search'], 0),
            StreamChunk::done([], 1),
        ]);

        $handler = StreamHandler::for($stream);
        $buffered = [];

        foreach ($handler->buffered(10) as $chunk) {
            $buffered[] = $chunk;
        }

        expect($buffered)->toBeEmpty();
    });

    it('skips non-content chunks in buffering', function () {
        $stream = createTestStream([
            StreamChunk::content('Hello', 0),
            StreamChunk::toolCall(['name' => 'tool'], 1),
            StreamChunk::content(' World', 2),
            StreamChunk::done([], 3),
        ]);

        $handler = StreamHandler::for($stream);
        $buffered = [];

        foreach ($handler->buffered(100) as $chunk) {
            $buffered[] = $chunk;
        }

        expect($buffered)->toHaveCount(1);
        expect($buffered[0])->toBe('Hello World');
    });

    it('uses default buffer size of 100', function () {
        $longContent = str_repeat('a', 50);
        $stream = createTestStream([
            StreamChunk::content($longContent, 0),
            StreamChunk::content($longContent, 1),
            StreamChunk::content($longContent, 2),
            StreamChunk::done([], 3),
        ]);

        $handler = StreamHandler::for($stream);
        $buffered = [];

        foreach ($handler->buffered() as $chunk) {
            $buffered[] = $chunk;
        }

        // 50 + 50 = 100 >= 100 (first yield), then 50 (remainder)
        expect($buffered)->toHaveCount(2);
        expect(strlen($buffered[0]))->toBe(100);
        expect(strlen($buffered[1]))->toBe(50);
    });
});

describe('transform', function () {
    it('transforms chunks through callback', function () {
        $stream = createTestStream([
            StreamChunk::content('hello', 0),
            StreamChunk::content('world', 1),
            StreamChunk::done([], 2),
        ]);

        $handler = StreamHandler::for($stream);
        $transformed = [];

        foreach ($handler->transform(function (StreamChunk $chunk) {
            if ($chunk->isContent()) {
                return StreamChunk::content(strtoupper($chunk->content), $chunk->index);
            }

            return $chunk;
        }) as $chunk) {
            $transformed[] = $chunk;
        }

        expect($transformed)->toHaveCount(3);
        expect($transformed[0]->content)->toBe('HELLO');
        expect($transformed[1]->content)->toBe('WORLD');
    });

    it('filters out chunks when transformer returns null', function () {
        $stream = createTestStream([
            StreamChunk::content('keep', 0),
            StreamChunk::error('remove', 1),
            StreamChunk::content('also keep', 2),
            StreamChunk::done([], 3),
        ]);

        $handler = StreamHandler::for($stream);
        $transformed = [];

        foreach ($handler->transform(function (StreamChunk $chunk) {
            if ($chunk->isError()) {
                return null;
            }

            return $chunk;
        }) as $chunk) {
            $transformed[] = $chunk;
        }

        expect($transformed)->toHaveCount(3);
        expect($transformed[0]->content)->toBe('keep');
        expect($transformed[1]->content)->toBe('also keep');
    });
});

describe('filter', function () {
    it('filters by single type as string', function () {
        $stream = createTestStream([
            StreamChunk::content('text', 0),
            StreamChunk::toolCall(['name' => 'tool'], 1),
            StreamChunk::content('more', 2),
            StreamChunk::done([], 3),
        ]);

        $handler = StreamHandler::for($stream);
        $contentChunks = [];

        foreach ($handler->filter('content') as $chunk) {
            $contentChunks[] = $chunk;
        }

        expect($contentChunks)->toHaveCount(2);
        expect($contentChunks[0]->content)->toBe('text');
        expect($contentChunks[1]->content)->toBe('more');
    });

    it('filters by multiple types as array', function () {
        $stream = createTestStream([
            StreamChunk::content('text', 0),
            StreamChunk::toolCall(['name' => 'tool'], 1),
            StreamChunk::error('err', 2),
            StreamChunk::done([], 3),
        ]);

        $handler = StreamHandler::for($stream);
        $chunks = [];

        foreach ($handler->filter(['content', 'error']) as $chunk) {
            $chunks[] = $chunk;
        }

        expect($chunks)->toHaveCount(2);
        expect($chunks[0]->type)->toBe(StreamChunk::TYPE_CONTENT);
        expect($chunks[1]->type)->toBe(StreamChunk::TYPE_ERROR);
    });

    it('returns no chunks when type does not match', function () {
        $stream = createTestStream([
            StreamChunk::content('text', 0),
            StreamChunk::done([], 1),
        ]);

        $handler = StreamHandler::for($stream);
        $chunks = [];

        foreach ($handler->filter('tool_call') as $chunk) {
            $chunks[] = $chunk;
        }

        expect($chunks)->toBeEmpty();
    });
});

describe('contentOnly', function () {
    it('yields only text content from content chunks', function () {
        $stream = createTestStream([
            StreamChunk::content('Hello', 0),
            StreamChunk::toolCall(['name' => 'tool'], 1),
            StreamChunk::content(' World', 2),
            StreamChunk::error('err', 3),
            StreamChunk::done([], 4),
        ]);

        $handler = StreamHandler::for($stream);
        $texts = [];

        foreach ($handler->contentOnly() as $text) {
            $texts[] = $text;
        }

        expect($texts)->toBe(['Hello', ' World']);
    });

    it('yields nothing when no content chunks exist', function () {
        $stream = createTestStream([
            StreamChunk::toolCall(['name' => 'tool'], 0),
            StreamChunk::done([], 1),
        ]);

        $handler = StreamHandler::for($stream);
        $texts = [];

        foreach ($handler->contentOnly() as $text) {
            $texts[] = $text;
        }

        expect($texts)->toBeEmpty();
    });
});
