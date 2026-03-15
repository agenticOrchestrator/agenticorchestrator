<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\Concerns\HasResilience;
use AgenticOrchestrator\Agents\Concerns\HasStructuredOutput;
use AgenticOrchestrator\Agents\Concerns\HasTeamScope;
use AgenticOrchestrator\Agents\Concerns\TracksUsage;
use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\Resilience\CircuitBreaker;
use AgenticOrchestrator\Resilience\ProviderFallbackChain;
use AgenticOrchestrator\Resilience\RetryStrategy;
use AgenticOrchestrator\StructuredOutput\SchemaBuilder;
use AgenticOrchestrator\Tracking\UsageTracker;

// ---------------------------------------------------------------------------
// HasTeamScope
// ---------------------------------------------------------------------------
describe('HasTeamScope', function () {

    beforeEach(function () {
        $this->scopedAgent = new class
        {
            use HasTeamScope;

            public bool $isSystem = false;

            public function getId(): string
            {
                return 'scoped-agent';
            }
        };
    });

    describe('forTeam / getTeam', function () {

        it('sets and gets a team object', function () {
            $team = (object) ['id' => 5, 'name' => 'Engineering'];

            $result = $this->scopedAgent->forTeam($team);

            expect($result)->toBe($this->scopedAgent);
            expect($this->scopedAgent->getTeam())->toBe($team);
        });

        it('wraps a plain object into a TenantInterface', function () {
            $team = (object) ['id' => 10, 'name' => 'Sales'];

            $this->scopedAgent->forTeam($team);

            expect($this->scopedAgent->getTenant())->toBeInstanceOf(TenantInterface::class);
        });

        it('preserves an existing TenantInterface', function () {
            $tenant = Mockery::mock(TenantInterface::class);

            $this->scopedAgent->forTeam($tenant);

            expect($this->scopedAgent->getTenant())->toBe($tenant);
            expect($this->scopedAgent->getTeam())->toBe($tenant);
        });
    });

    describe('forUser / getUser', function () {

        it('sets and gets a user object', function () {
            $user = (object) ['id' => 42, 'name' => 'Alice'];

            $result = $this->scopedAgent->forUser($user);

            expect($result)->toBe($this->scopedAgent);
            expect($this->scopedAgent->getUser())->toBe($user);
        });

        it('defaults user to null', function () {
            expect($this->scopedAgent->getUser())->toBeNull();
        });
    });

    describe('isSystemAgent', function () {

        it('returns false by default', function () {
            expect($this->scopedAgent->isSystemAgent())->toBeFalse();
        });

        it('returns true when isSystem is set', function () {
            $this->scopedAgent->isSystem = true;

            expect($this->scopedAgent->isSystemAgent())->toBeTrue();
        });
    });

    describe('isAccessibleBy', function () {

        it('grants access for system agents', function () {
            $this->scopedAgent->isSystem = true;
            $anyTeam = (object) ['id' => 999];

            expect($this->scopedAgent->isAccessibleBy($anyTeam))->toBeTrue();
        });

        it('grants access when no team is set', function () {
            $anyTeam = (object) ['id' => 1];

            expect($this->scopedAgent->isAccessibleBy($anyTeam))->toBeTrue();
        });

        it('grants access to the owning team', function () {
            $team = (object) ['id' => 7];
            $this->scopedAgent->forTeam($team);

            $sameTeam = (object) ['id' => 7];

            expect($this->scopedAgent->isAccessibleBy($sameTeam))->toBeTrue();
        });

        it('denies access to a different team', function () {
            $ownerTeam = (object) ['id' => 7];
            $this->scopedAgent->forTeam($ownerTeam);

            $otherTeam = (object) ['id' => 99];

            expect($this->scopedAgent->isAccessibleBy($otherTeam))->toBeFalse();
        });
    });
});

// ---------------------------------------------------------------------------
// TracksUsage
// ---------------------------------------------------------------------------
describe('TracksUsage', function () {

    beforeEach(function () {
        $this->trackingAgent = new class
        {
            use TracksUsage;
        };
    });

    describe('enable / disable tracking', function () {

        it('enables tracking with fluent return', function () {
            $result = $this->trackingAgent->enableTracking();

            expect($result)->toBe($this->trackingAgent);
        });

        it('disables tracking with fluent return', function () {
            $result = $this->trackingAgent->disableTracking();

            expect($result)->toBe($this->trackingAgent);
        });
    });

    describe('setUsageTracker / getUsageTracker', function () {

        it('sets a custom usage tracker', function () {
            $tracker = Mockery::mock(UsageTracker::class);

            $result = $this->trackingAgent->setUsageTracker($tracker);

            expect($result)->toBe($this->trackingAgent);
            expect($this->trackingAgent->getUsageTracker())->toBe($tracker);
        });
    });

    describe('last usage record accessors', function () {

        it('returns null when no record exists', function () {
            expect($this->trackingAgent->getLastUsageRecord())->toBeNull();
        });

        it('returns zero cost when no record exists', function () {
            expect($this->trackingAgent->getLastRequestCost())->toBe(0.0);
        });

        it('returns zero tokens when no record exists', function () {
            expect($this->trackingAgent->getLastRequestTokens())->toBe(0);
        });

        it('returns zero latency when no record exists', function () {
            expect($this->trackingAgent->getLastRequestLatency())->toBe(0.0);
        });
    });
});

// ---------------------------------------------------------------------------
// HasStructuredOutput
// ---------------------------------------------------------------------------
describe('HasStructuredOutput', function () {

    beforeEach(function () {
        $this->structuredAgent = new class
        {
            use HasStructuredOutput;
        };
    });

    describe('withSchema / getSchema / hasSchema', function () {

        it('starts with no schema', function () {
            expect($this->structuredAgent->hasSchema())->toBeFalse();
            expect($this->structuredAgent->getSchema())->toBeNull();
        });

        it('sets schema from array', function () {
            $schema = [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                ],
            ];

            $result = $this->structuredAgent->withSchema($schema);

            expect($result)->toBe($this->structuredAgent);
            expect($this->structuredAgent->hasSchema())->toBeTrue();
            expect($this->structuredAgent->getSchema())->toBe($schema);
        });

        it('sets schema from SchemaBuilder', function () {
            $builder = SchemaBuilder::object()
                ->stringProperty('title', 'The title', required: true)
                ->strict();

            $this->structuredAgent->withSchema($builder);

            $schema = $this->structuredAgent->getSchema();
            expect($schema)->toBeArray();
            expect($schema['type'])->toBe('object');
        });
    });

    describe('withoutSchema', function () {

        it('clears an existing schema', function () {
            $this->structuredAgent->withSchema(['type' => 'object']);
            $result = $this->structuredAgent->withoutSchema();

            expect($result)->toBe($this->structuredAgent);
            expect($this->structuredAgent->hasSchema())->toBeFalse();
            expect($this->structuredAgent->getSchema())->toBeNull();
        });
    });

    describe('validation toggle', function () {

        it('enables validation by default', function () {
            // The default is true; validate we can toggle
            $result = $this->structuredAgent->validateOutput(true);

            expect($result)->toBe($this->structuredAgent);
        });

        it('disables validation via skipValidation', function () {
            $result = $this->structuredAgent->skipValidation();

            expect($result)->toBe($this->structuredAgent);
        });
    });

    describe('includeSchemaInPrompt', function () {

        it('returns self for fluent chaining', function () {
            $result = $this->structuredAgent->includeSchemaInPrompt(false);

            expect($result)->toBe($this->structuredAgent);
        });
    });

    describe('respondStructured throws without schema', function () {

        it('throws InvalidArgumentException when no schema is defined', function () {
            $agent = new class
            {
                use HasStructuredOutput;

                public function respond(string $message, array $context = []): object
                {
                    return (object) ['content' => '{}'];
                }
            };

            expect(fn () => $agent->respondStructured('test'))
                ->toThrow(InvalidArgumentException::class, 'No schema defined');
        });
    });

    describe('extractJson', function () {

        it('extracts JSON from markdown code block', function () {
            $agent = new class
            {
                use HasStructuredOutput;

                public function testExtractJson(string $content): string
                {
                    return $this->extractJson($content);
                }
            };

            $input = "Here is the result:\n```json\n{\"name\": \"test\"}\n```\nDone.";
            expect($agent->testExtractJson($input))->toBe('{"name": "test"}');
        });

        it('extracts JSON object from raw text', function () {
            $agent = new class
            {
                use HasStructuredOutput;

                public function testExtractJson(string $content): string
                {
                    return $this->extractJson($content);
                }
            };

            $input = 'The answer is {"result": 42} as expected.';
            expect($agent->testExtractJson($input))->toBe('{"result": 42}');
        });

        it('returns trimmed content when no JSON detected', function () {
            $agent = new class
            {
                use HasStructuredOutput;

                public function testExtractJson(string $content): string
                {
                    return $this->extractJson($content);
                }
            };

            expect($agent->testExtractJson('  plain text  '))->toBe('plain text');
        });
    });

    describe('addSchemaToMessage', function () {

        it('appends schema instructions to the message', function () {
            $agent = new class
            {
                use HasStructuredOutput;

                public function testAddSchemaToMessage(string $message): string
                {
                    return $this->addSchemaToMessage($message);
                }
            };

            $agent->withSchema(['type' => 'object', 'properties' => ['name' => ['type' => 'string']]]);

            $result = $agent->testAddSchemaToMessage('Analyze this.');

            expect($result)->toContain('Analyze this.');
            expect($result)->toContain('Respond with valid JSON matching this schema');
            expect($result)->toContain('"type": "object"');
        });
    });

    describe('preset schemas', function () {

        it('creates a list schema via withListSchema', function () {
            $this->structuredAgent->withListSchema('product');

            $schema = $this->structuredAgent->getSchema();
            expect($schema)->toBeArray();
            expect($schema['type'])->toBe('object');
            expect($schema['properties'])->toHaveKey('items');
            expect($schema['properties'])->toHaveKey('count');
        });

        it('creates a decision schema via withDecisionSchema', function () {
            $this->structuredAgent->withDecisionSchema('approval');

            $schema = $this->structuredAgent->getSchema();
            expect($schema['type'])->toBe('object');
            expect($schema['properties'])->toHaveKey('decision');
            expect($schema['properties'])->toHaveKey('reasoning');
            expect($schema['properties'])->toHaveKey('confidence');
        });

        it('creates a classification schema via withClassificationSchema', function () {
            $this->structuredAgent->withClassificationSchema(['spam', 'ham', 'unsure']);

            $schema = $this->structuredAgent->getSchema();
            expect($schema['type'])->toBe('object');
            expect($schema['properties'])->toHaveKey('category');
            expect($schema['properties'])->toHaveKey('reasoning');
            expect($schema['properties'])->toHaveKey('confidence');
        });

        it('creates an extraction schema via withExtractionSchema', function () {
            $this->structuredAgent->withExtractionSchema([
                'name' => ['type' => 'string', 'description' => 'Person name', 'required' => true],
                'age' => ['type' => 'integer', 'description' => 'Person age'],
                'active' => ['type' => 'boolean'],
                'score' => ['type' => 'number'],
            ]);

            $schema = $this->structuredAgent->getSchema();
            expect($schema['type'])->toBe('object');
            expect($schema['properties'])->toHaveKey('name');
            expect($schema['properties'])->toHaveKey('age');
            expect($schema['properties'])->toHaveKey('active');
            expect($schema['properties'])->toHaveKey('score');
        });
    });
});

// ---------------------------------------------------------------------------
// HasResilience
// ---------------------------------------------------------------------------
describe('HasResilience', function () {

    beforeEach(function () {
        $this->resilientAgent = new class
        {
            use HasResilience;

            protected function getProvider(): string
            {
                return 'openai';
            }

            protected function getModel(): string
            {
                return 'gpt-4o';
            }

            protected function getProviderConfig(): array
            {
                return [];
            }

            public function getName(): string
            {
                return 'test-resilient-agent';
            }
        };
    });

    describe('retry strategy', function () {

        it('creates a default retry strategy on first access', function () {
            $strategy = $this->resilientAgent->getRetryStrategy();

            expect($strategy)->toBeInstanceOf(RetryStrategy::class);
        });

        it('accepts a RetryStrategy instance', function () {
            $strategy = RetryStrategy::none();

            $result = $this->resilientAgent->withRetry($strategy);

            expect($result)->toBe($this->resilientAgent);
            expect($this->resilientAgent->getRetryStrategy())->toBe($strategy);
        });

        it('accepts an array configuration', function () {
            $result = $this->resilientAgent->withRetry(['max_attempts' => 5]);

            expect($result)->toBe($this->resilientAgent);
            expect($this->resilientAgent->getRetryStrategy())->toBeInstanceOf(RetryStrategy::class);
        });

        it('disables retry via withoutRetry', function () {
            $this->resilientAgent->withoutRetry();

            $strategy = $this->resilientAgent->getRetryStrategy();
            $config = $strategy->toArray();
            expect($config['max_attempts'])->toBe(1);
        });
    });

    describe('circuit breaker', function () {

        it('returns null when no circuit breaker is configured', function () {
            expect($this->resilientAgent->getCircuitBreaker())->toBeNull();
        });

        it('accepts a CircuitBreaker instance', function () {
            $breaker = Mockery::mock(CircuitBreaker::class);

            $result = $this->resilientAgent->withCircuitBreaker($breaker);

            expect($result)->toBe($this->resilientAgent);
            expect($this->resilientAgent->getCircuitBreaker())->toBe($breaker);
        });

        it('removes circuit breaker via withoutCircuitBreaker', function () {
            $breaker = Mockery::mock(CircuitBreaker::class);
            $this->resilientAgent->withCircuitBreaker($breaker);

            $this->resilientAgent->withoutCircuitBreaker();

            expect($this->resilientAgent->getCircuitBreaker())->toBeNull();
        });
    });

    describe('fallback chain', function () {

        it('returns null when no fallback chain is configured', function () {
            expect($this->resilientAgent->getFallbackChain())->toBeNull();
        });

        it('accepts a ProviderFallbackChain instance', function () {
            $chain = Mockery::mock(ProviderFallbackChain::class);

            $result = $this->resilientAgent->withFallbacks($chain);

            expect($result)->toBe($this->resilientAgent);
            expect($this->resilientAgent->getFallbackChain())->toBe($chain);
        });
    });

    describe('isHealthy', function () {

        it('returns true when no circuit breaker is configured', function () {
            expect($this->resilientAgent->isHealthy())->toBeTrue();
        });

        it('returns true when circuit breaker is not open', function () {
            $breaker = Mockery::mock(CircuitBreaker::class);
            $breaker->shouldReceive('isOpen')->andReturn(false);

            $this->resilientAgent->withCircuitBreaker($breaker);

            expect($this->resilientAgent->isHealthy())->toBeTrue();
        });

        it('returns false when circuit breaker is open', function () {
            $breaker = Mockery::mock(CircuitBreaker::class);
            $breaker->shouldReceive('isOpen')->andReturn(true);

            $this->resilientAgent->withCircuitBreaker($breaker);

            expect($this->resilientAgent->isHealthy())->toBeFalse();
        });
    });

    describe('resetResilience', function () {

        it('resets circuit breaker and fallback chain', function () {
            $breaker = Mockery::mock(CircuitBreaker::class);
            $breaker->shouldReceive('reset')->once();

            $chain = Mockery::mock(ProviderFallbackChain::class);
            $chain->shouldReceive('reset')->once();

            $this->resilientAgent->withCircuitBreaker($breaker);
            $this->resilientAgent->withFallbacks($chain);

            $this->resilientAgent->resetResilience();
        });

        it('handles reset when nothing is configured', function () {
            // Should not throw
            $this->resilientAgent->resetResilience();

            expect(true)->toBeTrue();
        });
    });

    describe('getResilienceStats', function () {

        it('returns stats array with null values when not configured', function () {
            $stats = $this->resilientAgent->getResilienceStats();

            expect($stats)->toHaveKeys(['retry', 'circuit_breaker', 'fallback_chain']);
            expect($stats['circuit_breaker'])->toBeNull();
            expect($stats['fallback_chain'])->toBeNull();
        });

        it('returns retry stats when retry strategy exists', function () {
            $this->resilientAgent->withRetry(['max_attempts' => 5]);

            $stats = $this->resilientAgent->getResilienceStats();

            expect($stats['retry'])->toBeArray();
            expect($stats['retry']['max_attempts'])->toBe(5);
        });
    });

    describe('executeWithResilience', function () {

        it('executes callback directly without retry or circuit breaker', function () {
            $agent = new class
            {
                use HasResilience;

                protected function getProvider(): string
                {
                    return 'openai';
                }

                protected function getModel(): string
                {
                    return 'gpt-4o';
                }

                protected function getProviderConfig(): array
                {
                    return [];
                }

                public function getName(): string
                {
                    return 'test';
                }

                public function testExecute(Closure $callback): mixed
                {
                    return $this->executeWithResilience($callback);
                }
            };

            $result = $agent->testExecute(fn () => 'success');

            expect($result)->toBe('success');
        });
    });
});
