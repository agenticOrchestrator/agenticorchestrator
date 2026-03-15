<?php

declare(strict_types=1);

use AgenticOrchestrator\Conversations\MessageRole;

describe('MessageRole', function () {
    it('has four cases', function () {
        $cases = MessageRole::cases();

        expect($cases)->toHaveCount(4);
    });

    it('has correct string values', function () {
        expect(MessageRole::System->value)->toBe('system')
            ->and(MessageRole::User->value)->toBe('user')
            ->and(MessageRole::Assistant->value)->toBe('assistant')
            ->and(MessageRole::Tool->value)->toBe('tool');
    });

    it('creates from string values', function () {
        expect(MessageRole::from('system'))->toBe(MessageRole::System)
            ->and(MessageRole::from('user'))->toBe(MessageRole::User)
            ->and(MessageRole::from('assistant'))->toBe(MessageRole::Assistant)
            ->and(MessageRole::from('tool'))->toBe(MessageRole::Tool);
    });

    it('returns null for invalid values via tryFrom', function () {
        expect(MessageRole::tryFrom('invalid'))->toBeNull()
            ->and(MessageRole::tryFrom(''))->toBeNull();
    });

    it('identifies user messages correctly', function () {
        expect(MessageRole::User->isUser())->toBeTrue()
            ->and(MessageRole::System->isUser())->toBeFalse()
            ->and(MessageRole::Assistant->isUser())->toBeFalse()
            ->and(MessageRole::Tool->isUser())->toBeFalse();
    });

    it('identifies assistant messages correctly', function () {
        expect(MessageRole::Assistant->isAssistant())->toBeTrue()
            ->and(MessageRole::System->isAssistant())->toBeFalse()
            ->and(MessageRole::User->isAssistant())->toBeFalse()
            ->and(MessageRole::Tool->isAssistant())->toBeFalse();
    });

    it('identifies system messages correctly', function () {
        expect(MessageRole::System->isSystem())->toBeTrue()
            ->and(MessageRole::User->isSystem())->toBeFalse()
            ->and(MessageRole::Assistant->isSystem())->toBeFalse()
            ->and(MessageRole::Tool->isSystem())->toBeFalse();
    });

    it('identifies tool messages correctly', function () {
        expect(MessageRole::Tool->isTool())->toBeTrue()
            ->and(MessageRole::System->isTool())->toBeFalse()
            ->and(MessageRole::User->isTool())->toBeFalse()
            ->and(MessageRole::Assistant->isTool())->toBeFalse();
    });

    it('returns correct labels', function () {
        expect(MessageRole::System->label())->toBe('System')
            ->and(MessageRole::User->label())->toBe('User')
            ->and(MessageRole::Assistant->label())->toBe('Assistant')
            ->and(MessageRole::Tool->label())->toBe('Tool');
    });
});
