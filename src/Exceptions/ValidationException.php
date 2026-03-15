<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Exceptions;

use Throwable;

/**
 * Thrown when validation fails.
 */
class ValidationException extends AgentException
{
    /**
     * Validation errors by field.
     *
     * @var array<string, array<string>>
     */
    protected array $errors = [];

    /**
     * Create a new validation exception.
     *
     * @param  array<string, array<string>|string>  $errors
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        array $errors = [],
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
    ) {
        // Normalize errors to arrays
        $this->errors = array_map(
            fn ($e) => is_array($e) ? $e : [$e],
            $errors
        );

        if ($message === '') {
            $message = $this->buildMessage();
        }

        parent::__construct($message, $code, $previous, array_merge($context, [
            'validation_errors' => $this->errors,
        ]));
    }

    /**
     * Create with errors.
     *
     * @param  array<string, array<string>|string>  $errors
     */
    public static function withErrors(array $errors): static
    {
        return new static($errors);
    }

    /**
     * Create for a single field.
     *
     * @param  string|array<string>  $messages
     */
    public static function forField(string $field, string|array $messages): static
    {
        return new static([
            $field => is_array($messages) ? $messages : [$messages],
        ]);
    }

    /**
     * Create for required fields.
     *
     * @param  array<string>  $fields
     */
    public static function required(array $fields): static
    {
        $errors = [];
        foreach ($fields as $field) {
            $errors[$field] = ["The {$field} field is required."];
        }

        return new static($errors);
    }

    /**
     * Create for invalid type.
     */
    public static function invalidType(string $field, string $expected, string $actual): static
    {
        return new static([
            $field => ["Expected {$expected}, got {$actual}."],
        ]);
    }

    /**
     * Get all validation errors.
     *
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field.
     *
     * @return array<string>
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Check if a field has errors.
     */
    public function hasFieldErrors(string $field): bool
    {
        return isset($this->errors[$field]) && count($this->errors[$field]) > 0;
    }

    /**
     * Get all error messages flattened.
     *
     * @return array<string>
     */
    public function getAllMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $fieldErrors) {
            $messages = array_merge($messages, $fieldErrors);
        }

        return $messages;
    }

    /**
     * Build error message from errors.
     */
    protected function buildMessage(): string
    {
        $count = 0;
        foreach ($this->errors as $fieldErrors) {
            $count += count($fieldErrors);
        }

        if ($count === 0) {
            return 'Validation failed';
        }

        if ($count === 1) {
            $messages = $this->getAllMessages();

            return $messages[0];
        }

        return "Validation failed with {$count} errors";
    }
}
