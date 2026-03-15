<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\Agent;
use AgenticOrchestrator\Contracts\MemoryInterface;

// Create a concrete test agent for use across tests
function createTestAgent(array $overrides = []): Agent
{
    return new class($overrides) extends Agent
    {
        public function __construct(private array $overrides = [])
        {
            if (isset($overrides['name'])) {
                $this->name = $overrides['name'];
            }
            if (isset($overrides['description'])) {
                $this->description = $overrides['description'];
            }
            if (isset($overrides['model'])) {
                $this->model = $overrides['model'];
            }
            if (isset($overrides['provider'])) {
                $this->provider = $overrides['provider'];
            }
            if (isset($overrides['temperature'])) {
                $this->temperature = $overrides['temperature'];
            }
            if (isset($overrides['maxTokens'])) {
                $this->maxTokens = $overrides['maxTokens'];
            }
            if (isset($overrides['capabilities'])) {
                $this->capabilities = array_merge($this->capabilities, $overrides['capabilities']);
            }
            if (isset($overrides['isSystem'])) {
                $this->isSystem = $overrides['isSystem'];
            }
        }

        public function instructions(): string
        {
            return $this->overrides['instructions'] ?? 'You are a test agent.';
        }
    };
}

describe('Agent', function () {

    describe('getId', function () {
        it('returns the fully qualified class name', function () {
            $agent = createTestAgent();

            expect($agent->getId())->toBeString();
            expect($agent->getId())->not->toBeEmpty();
        });
    });

    describe('getName', function () {
        it('returns the configured name when set', function () {
            $agent = createTestAgent(['name' => 'Customer Support']);

            expect($agent->getName())->toBe('Customer Support');
        });

        it('returns class basename when name is empty', function () {
            $agent = createTestAgent();

            // Anonymous class will have a generated basename
            expect($agent->getName())->toBeString();
            expect($agent->getName())->not->toBeEmpty();
        });
    });

    describe('getDescription', function () {
        it('returns the configured description', function () {
            $agent = createTestAgent(['description' => 'Handles support tickets']);

            expect($agent->getDescription())->toBe('Handles support tickets');
        });

        it('returns empty string by default', function () {
            $agent = createTestAgent();

            expect($agent->getDescription())->toBe('');
        });
    });

    describe('getModel', function () {
        it('returns gpt-4o by default', function () {
            $agent = createTestAgent();

            expect($agent->getModel())->toBe('gpt-4o');
        });

        it('returns configured model', function () {
            $agent = createTestAgent(['model' => 'claude-3-5-sonnet']);

            expect($agent->getModel())->toBe('claude-3-5-sonnet');
        });
    });

    describe('getProvider', function () {
        it('returns openai by default', function () {
            $agent = createTestAgent();

            expect($agent->getProvider())->toBe('openai');
        });

        it('returns configured provider', function () {
            $agent = createTestAgent(['provider' => 'anthropic']);

            expect($agent->getProvider())->toBe('anthropic');
        });
    });

    describe('instructions', function () {
        it('returns the instructions string', function () {
            $agent = createTestAgent(['instructions' => 'Be helpful and concise.']);

            expect($agent->instructions())->toBe('Be helpful and concise.');
        });
    });

    describe('withModel', function () {
        it('sets the model and returns self for fluent chaining', function () {
            $agent = createTestAgent();

            $result = $agent->withModel('claude-3-opus');

            expect($result)->toBe($agent);
            expect($agent->getModel())->toBe('claude-3-opus');
        });
    });

    describe('withProvider', function () {
        it('sets the provider and returns self for fluent chaining', function () {
            $agent = createTestAgent();

            $result = $agent->withProvider('anthropic');

            expect($result)->toBe($agent);
            expect($agent->getProvider())->toBe('anthropic');
        });
    });

    describe('withTemperature', function () {
        it('sets the temperature and returns self for fluent chaining', function () {
            $agent = createTestAgent();

            $result = $agent->withTemperature(0.3);

            expect($result)->toBe($agent);
        });
    });

    describe('withMaxTokens', function () {
        it('sets max tokens and returns self for fluent chaining', function () {
            $agent = createTestAgent();

            $result = $agent->withMaxTokens(1024);

            expect($result)->toBe($agent);
        });

        it('accepts null to clear max tokens', function () {
            $agent = createTestAgent(['maxTokens' => 512]);

            $result = $agent->withMaxTokens(null);

            expect($result)->toBe($agent);
        });
    });

    describe('getConfig', function () {
        it('returns the full configuration array', function () {
            $memory = Mockery::mock(MemoryInterface::class);
            $memory->shouldReceive('getConversationHistory')->andReturn([]);
            $memory->shouldReceive('addMessage');

            $agent = createTestAgent([
                'name' => 'Test Agent',
                'description' => 'A test agent',
                'model' => 'gpt-4o',
                'provider' => 'openai',
            ]);
            $agent->withMemory($memory);

            $config = $agent->getConfig();

            expect($config)->toBeArray();
            expect($config)->toHaveKey('id');
            expect($config)->toHaveKey('name');
            expect($config)->toHaveKey('description');
            expect($config)->toHaveKey('model');
            expect($config)->toHaveKey('provider');
            expect($config)->toHaveKey('temperature');
            expect($config)->toHaveKey('max_tokens');
            expect($config)->toHaveKey('memory');
            expect($config)->toHaveKey('capabilities');
            expect($config)->toHaveKey('is_system');
            expect($config)->toHaveKey('tools');
            expect($config['name'])->toBe('Test Agent');
            expect($config['description'])->toBe('A test agent');
            expect($config['model'])->toBe('gpt-4o');
            expect($config['provider'])->toBe('openai');
        });
    });

    describe('fluent chaining', function () {
        it('supports chaining multiple with* methods', function () {
            $agent = createTestAgent();

            $result = $agent
                ->withModel('claude-3-opus')
                ->withProvider('anthropic')
                ->withTemperature(0.5)
                ->withMaxTokens(2048);

            expect($result)->toBe($agent);
            expect($agent->getModel())->toBe('claude-3-opus');
            expect($agent->getProvider())->toBe('anthropic');
        });
    });
});
