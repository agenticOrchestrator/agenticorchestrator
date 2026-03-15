<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Throwable;

/**
 * Step Result - Represents the outcome of a workflow step execution.
 *
 * @implements Arrayable<string, mixed>
 */
class StepResult implements Arrayable, JsonSerializable
{
    /**
     * Result status constants.
     */
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_PENDING = 'pending';

    public const STATUS_WAITING = 'waiting'; // Waiting for human approval

    /**
     * Create a new step result.
     *
     * @param  string  $status  The execution status
     * @param  mixed  $output  The step output data
     * @param  string|null  $message  Optional message
     * @param  Throwable|null  $exception  Exception if failed
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function __construct(
        public readonly string $status,
        public readonly mixed $output = null,
        public readonly ?string $message = null,
        public readonly ?Throwable $exception = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a successful result.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function success(mixed $output = null, array $metadata = []): static
    {
        return new static(
            status: self::STATUS_SUCCESS,
            output: $output,
            metadata: $metadata,
        );
    }

    /**
     * Create a failed result.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function failed(string $message, ?Throwable $exception = null, array $metadata = []): static
    {
        return new static(
            status: self::STATUS_FAILED,
            message: $message,
            exception: $exception,
            metadata: $metadata,
        );
    }

    /**
     * Create a skipped result.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function skipped(string $reason = 'Condition not met', array $metadata = []): static
    {
        return new static(
            status: self::STATUS_SKIPPED,
            message: $reason,
            metadata: $metadata,
        );
    }

    /**
     * Create a pending result (for async steps).
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function pending(string $message = 'Step execution pending', array $metadata = []): static
    {
        return new static(
            status: self::STATUS_PENDING,
            message: $message,
            metadata: $metadata,
        );
    }

    /**
     * Create a waiting result (for human-in-the-loop).
     *
     * @param  array<string, mixed>  $approvalData
     * @param  array<string, mixed>  $metadata
     */
    public static function waiting(string $message, array $approvalData = [], array $metadata = []): static
    {
        return new static(
            status: self::STATUS_WAITING,
            output: $approvalData,
            message: $message,
            metadata: $metadata,
        );
    }

    /**
     * Check if the step succeeded.
     */
    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Check if the step failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if the step was skipped.
     */
    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    /**
     * Check if the step is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the step is waiting for approval.
     */
    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAITING;
    }

    /**
     * Check if execution should continue after this step.
     */
    public function shouldContinue(): bool
    {
        return $this->isSuccess() || $this->isSkipped();
    }

    /**
     * Check if workflow should pause.
     */
    public function shouldPause(): bool
    {
        return $this->isWaiting() || $this->isPending();
    }

    /**
     * Get metadata value.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'output' => $this->output,
            'message' => $this->message,
            'exception' => $this->exception?->getMessage(),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Serialize to JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
