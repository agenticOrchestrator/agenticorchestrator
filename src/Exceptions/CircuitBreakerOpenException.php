<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Exceptions;

use Throwable;

/**
 * Thrown when a circuit breaker is open and requests are blocked.
 */
class CircuitBreakerOpenException extends AgentException
{
    protected string $serviceName = '';

    protected ?int $openUntil = null;

    protected int $failureCount = 0;

    /**
     * Create a new circuit breaker open exception.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $serviceName,
        ?int $openUntil = null,
        int $failureCount = 0,
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
    ) {
        $this->serviceName = $serviceName;
        $this->openUntil = $openUntil;
        $this->failureCount = $failureCount;

        $message = "Circuit breaker is open for '{$serviceName}'";
        if ($openUntil !== null) {
            $remainingSeconds = max(0, $openUntil - time());
            $message .= ", retry in {$remainingSeconds} seconds";
        }

        parent::__construct($message, $code, $previous, array_merge($context, [
            'service_name' => $serviceName,
            'open_until' => $openUntil,
            'failure_count' => $failureCount,
        ]));

        $this->recoverable = true;
    }

    /**
     * Create for a provider.
     */
    public static function forProvider(
        string $provider,
        ?int $openUntil = null,
        int $failureCount = 0,
    ): static {
        return new static("provider:{$provider}", $openUntil, $failureCount);
    }

    /**
     * Create for an agent.
     */
    public static function forAgent(
        string $agentName,
        ?int $openUntil = null,
        int $failureCount = 0,
    ): static {
        return new static("agent:{$agentName}", $openUntil, $failureCount);
    }

    /**
     * Create for a tool.
     */
    public static function forTool(
        string $toolName,
        ?int $openUntil = null,
        int $failureCount = 0,
    ): static {
        return new static("tool:{$toolName}", $openUntil, $failureCount);
    }

    /**
     * Get the service name.
     */
    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    /**
     * Get the timestamp when the circuit will close.
     */
    public function getOpenUntil(): ?int
    {
        return $this->openUntil;
    }

    /**
     * Get the failure count that caused the circuit to open.
     */
    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    /**
     * Get the remaining time until the circuit closes.
     */
    public function getRemainingSeconds(): int
    {
        if ($this->openUntil === null) {
            return 0;
        }

        return max(0, $this->openUntil - time());
    }
}
