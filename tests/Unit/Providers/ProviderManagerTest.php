<?php

declare(strict_types=1);

use AgenticOrchestrator\Providers\ProviderManager;

describe('ProviderManager', function () {
    beforeEach(function () {
        $this->manager = new ProviderManager(
            providers: [
                'openai' => ['api_key' => 'sk-test', 'model' => 'gpt-4'],
                'anthropic' => ['api_key' => 'ant-test', 'model' => 'claude-3'],
            ],
            defaultProvider: 'openai',
        );
    });

    describe('constructor', function () {
        it('creates with default values', function () {
            $manager = new ProviderManager;

            expect($manager->getDefaultProvider())->toBe('openai');
        });

        it('creates with custom providers and default', function () {
            expect($this->manager->getDefaultProvider())->toBe('openai');
        });
    });

    describe('getDefaultProvider', function () {
        it('returns the configured default provider', function () {
            expect($this->manager->getDefaultProvider())->toBe('openai');
        });
    });

    describe('hasProvider', function () {
        it('returns true for configured provider', function () {
            expect($this->manager->hasProvider('openai'))->toBeTrue()
                ->and($this->manager->hasProvider('anthropic'))->toBeTrue();
        });

        it('returns false for unconfigured provider', function () {
            expect($this->manager->hasProvider('missing'))->toBeFalse();
        });
    });

    describe('getProviderConfig', function () {
        it('returns config for existing provider', function () {
            $config = $this->manager->getProviderConfig('openai');

            expect($config)->toBe(['api_key' => 'sk-test', 'model' => 'gpt-4']);
        });

        it('returns null for missing provider', function () {
            expect($this->manager->getProviderConfig('missing'))->toBeNull();
        });
    });

    describe('chat', function () {
        it('throws RuntimeException on provider failure', function () {
            // The Prism facade is not set up, so calling chat will throw
            expect(fn () => $this->manager->chat(
                provider: 'openai',
                model: 'gpt-4',
                messages: [['role' => 'user', 'content' => 'Hello']],
            ))->toThrow(RuntimeException::class);
        });
    });
});
