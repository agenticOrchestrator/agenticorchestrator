<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Exceptions;

use Throwable;

/**
 * Thrown when an LLM provider returns an error.
 */
class ProviderException extends AgentException
{
    protected string $provider = '';

    protected ?string $model = null;

    protected ?int $statusCode = null;

    /**
     * Create a new provider exception.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $provider,
        string $message = '',
        ?string $model = null,
        ?int $statusCode = null,
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
    ) {
        $this->provider = $provider;
        $this->model = $model;
        $this->statusCode = $statusCode;

        $fullMessage = "[{$provider}] {$message}";
        if ($model) {
            $fullMessage = "[{$provider}/{$model}] {$message}";
        }

        parent::__construct($fullMessage, $code, $previous, array_merge($context, [
            'provider' => $provider,
            'model' => $model,
            'status_code' => $statusCode,
        ]));
    }

    /**
     * Create for rate limit error.
     */
    public static function rateLimited(
        string $provider,
        ?string $model = null,
        ?int $retryAfter = null,
    ): static {
        $message = 'Rate limit exceeded';
        if ($retryAfter !== null) {
            $message .= ", retry after {$retryAfter} seconds";
        }

        $exception = new static($provider, $message, $model, 429, 0, null, [
            'retry_after' => $retryAfter,
        ]);
        $exception->recoverable = true;

        return $exception;
    }

    /**
     * Create for authentication error.
     */
    public static function authenticationFailed(string $provider, ?string $model = null): static
    {
        return new static($provider, 'Authentication failed', $model, 401);
    }

    /**
     * Create for server error.
     */
    public static function serverError(
        string $provider,
        ?string $model = null,
        ?int $statusCode = null,
    ): static {
        $exception = new static($provider, 'Provider server error', $model, $statusCode ?? 500);
        $exception->recoverable = true;

        return $exception;
    }

    /**
     * Create for model not found error.
     */
    public static function modelNotFound(string $provider, string $model): static
    {
        return new static($provider, "Model not found: {$model}", $model, 404);
    }

    /**
     * Create for content filter error.
     */
    public static function contentFiltered(
        string $provider,
        ?string $model = null,
        ?string $reason = null,
    ): static {
        $message = 'Content filtered';
        if ($reason) {
            $message .= ": {$reason}";
        }

        return new static($provider, $message, $model, 400, 0, null, [
            'filter_reason' => $reason,
        ]);
    }

    /**
     * Create for context length exceeded error.
     */
    public static function contextLengthExceeded(
        string $provider,
        ?string $model = null,
        ?int $maxTokens = null,
        ?int $requestedTokens = null,
    ): static {
        $message = 'Context length exceeded';
        if ($maxTokens && $requestedTokens) {
            $message .= " (requested: {$requestedTokens}, max: {$maxTokens})";
        }

        return new static($provider, $message, $model, 400, 0, null, [
            'max_tokens' => $maxTokens,
            'requested_tokens' => $requestedTokens,
        ]);
    }

    /**
     * Get the provider name.
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Get the model name.
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Check if this is a rate limit error.
     */
    public function isRateLimited(): bool
    {
        return $this->statusCode === 429;
    }

    /**
     * Check if this is a server error.
     */
    public function isServerError(): bool
    {
        return $this->statusCode !== null && $this->statusCode >= 500;
    }
}
