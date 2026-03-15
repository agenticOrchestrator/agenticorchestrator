<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Conversations;

/**
 * Enum representing message roles in a conversation.
 */
enum MessageRole: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';

    /**
     * Check if this is a user message.
     */
    public function isUser(): bool
    {
        return $this === self::User;
    }

    /**
     * Check if this is an assistant message.
     */
    public function isAssistant(): bool
    {
        return $this === self::Assistant;
    }

    /**
     * Check if this is a system message.
     */
    public function isSystem(): bool
    {
        return $this === self::System;
    }

    /**
     * Check if this is a tool message.
     */
    public function isTool(): bool
    {
        return $this === self::Tool;
    }

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::System => 'System',
            self::User => 'User',
            self::Assistant => 'Assistant',
            self::Tool => 'Tool',
        };
    }
}
