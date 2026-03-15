<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Streaming;

use Closure;
use Generator;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stream Handler - Utilities for handling streaming responses.
 *
 * Provides helpers for SSE responses, buffered streaming,
 * and stream transformations.
 */
class StreamHandler
{
    /**
     * Buffer size for output flushing.
     */
    protected int $bufferSize = 0;

    /**
     * Flush callback.
     */
    protected ?Closure $flushCallback = null;

    /**
     * Create a new stream handler.
     */
    public function __construct(
        protected StreamResponse $stream,
    ) {}

    /**
     * Create a stream handler.
     */
    public static function for(StreamResponse $stream): static
    {
        return new static($stream);
    }

    /**
     * Set buffer size for flushing.
     */
    public function bufferSize(int $size): static
    {
        $this->bufferSize = $size;

        return $this;
    }

    /**
     * Set flush callback.
     *
     * @param  Closure(): void  $callback
     */
    public function onFlush(Closure $callback): static
    {
        $this->flushCallback = $callback;

        return $this;
    }

    /**
     * Create an SSE (Server-Sent Events) response.
     */
    public function toSSEResponse(): StreamedResponse
    {
        return new StreamedResponse(function () {
            // Disable output buffering
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            foreach ($this->stream->toSSE() as $chunk) {
                echo $chunk;

                if (connection_aborted()) {
                    break;
                }

                $this->flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Create a JSONL (JSON Lines) response.
     */
    public function toJSONLResponse(): StreamedResponse
    {
        return new StreamedResponse(function () {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            foreach ($this->stream as $chunk) {
                echo json_encode($chunk->toArray())."\n";

                if (connection_aborted()) {
                    break;
                }

                $this->flush();
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Create a plain text streaming response.
     */
    public function toTextResponse(): StreamedResponse
    {
        return new StreamedResponse(function () {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            foreach ($this->stream as $chunk) {
                if ($chunk->isContent()) {
                    echo $chunk->content;
                }

                if (connection_aborted()) {
                    break;
                }

                $this->flush();
            }
        }, 200, [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Create a buffered stream that yields after buffer fills.
     *
     * @return Generator<string>
     */
    public function buffered(int $bufferSize = 100): Generator
    {
        $buffer = '';

        foreach ($this->stream as $chunk) {
            if ($chunk->isContent()) {
                $buffer .= $chunk->content;

                if (strlen($buffer) >= $bufferSize) {
                    yield $buffer;
                    $buffer = '';
                }
            }
        }

        // Yield remaining buffer
        if ($buffer !== '') {
            yield $buffer;
        }
    }

    /**
     * Transform chunks through a callback.
     *
     * @param  Closure(StreamChunk): StreamChunk|null  $transformer
     * @return Generator<StreamChunk>
     */
    public function transform(Closure $transformer): Generator
    {
        foreach ($this->stream as $chunk) {
            $transformed = $transformer($chunk);

            if ($transformed !== null) {
                yield $transformed;
            }
        }
    }

    /**
     * Filter chunks by type.
     *
     * @param  string|array<string>  $types
     * @return Generator<StreamChunk>
     */
    public function filter(string|array $types): Generator
    {
        $types = (array) $types;

        foreach ($this->stream as $chunk) {
            if (in_array($chunk->type, $types, true)) {
                yield $chunk;
            }
        }
    }

    /**
     * Get only content chunks as text stream.
     *
     * @return Generator<string>
     */
    public function contentOnly(): Generator
    {
        foreach ($this->stream as $chunk) {
            if ($chunk->isContent()) {
                yield $chunk->content;
            }
        }
    }

    /**
     * Flush output.
     */
    protected function flush(): void
    {
        if ($this->flushCallback !== null) {
            ($this->flushCallback)();
        }

        if (function_exists('ob_flush')) {
            @ob_flush();
        }

        flush();
    }
}
