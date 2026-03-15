<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\Concerns\HasTools;
use AgenticOrchestrator\Tools\Attributes\Tool;
use AgenticOrchestrator\Tools\ToolResult;
use Illuminate\Support\Collection;

describe('HasTools', function () {

    beforeEach(function () {
        $this->toolAgent = new class
        {
            use HasTools;

            protected array $tools = [];

            #[Tool('Look up a customer by ID')]
            public function lookupCustomer(string $customerId): array
            {
                return ['id' => $customerId, 'name' => 'John Doe'];
            }

            #[Tool('Calculate the total price', name: 'calculate_price')]
            public function calculatePrice(float $price, float $taxRate = 0.1): float
            {
                return $price * (1 + $taxRate);
            }

            #[Tool('Hidden tool', hidden: true)]
            public function hiddenTool(): string
            {
                return 'hidden';
            }
        };
    });

    describe('getTools', function () {
        it('discovers attribute-based tools', function () {
            $tools = $this->toolAgent->getTools();

            expect($tools)->toBeInstanceOf(Collection::class);
            expect($tools->count())->toBe(2);
        });

        it('includes tool names and descriptions', function () {
            $tools = $this->toolAgent->getTools();

            $names = $tools->pluck('name')->toArray();

            expect($names)->toContain('lookupCustomer');
            expect($names)->toContain('calculate_price');
        });

        it('excludes hidden tools', function () {
            $tools = $this->toolAgent->getTools();

            $names = $tools->pluck('name')->toArray();

            expect($names)->not->toContain('hiddenTool');
        });
    });

    describe('getToolSchemas', function () {
        it('returns array of tool schemas', function () {
            $schemas = $this->toolAgent->getToolSchemas();

            expect($schemas)->toBeArray();
            expect(count($schemas))->toBe(2);
        });
    });

    describe('hasTool', function () {
        it('returns true for discovered tools', function () {
            expect($this->toolAgent->hasTool('lookupCustomer'))->toBeTrue();
            expect($this->toolAgent->hasTool('calculate_price'))->toBeTrue();
        });

        it('returns false for non-existent tools', function () {
            expect($this->toolAgent->hasTool('nonExistent'))->toBeFalse();
        });

        it('returns false for hidden tools', function () {
            expect($this->toolAgent->hasTool('hiddenTool'))->toBeFalse();
        });
    });

    describe('executeTool', function () {
        it('executes a discovered tool successfully', function () {
            $result = $this->toolAgent->executeTool('tc1', 'lookupCustomer', ['customerId' => 'C123']);

            expect($result)->toBeInstanceOf(ToolResult::class);
            expect($result->isSuccess())->toBeTrue();
            expect($result->result)->toBe(['id' => 'C123', 'name' => 'John Doe']);
            expect($result->toolCallId)->toBe('tc1');
            expect($result->name)->toBe('lookupCustomer');
        });

        it('uses default parameter values', function () {
            $result = $this->toolAgent->executeTool('tc2', 'calculate_price', ['price' => 100.0]);

            expect($result->isSuccess())->toBeTrue();
            expect($result->result)->toEqualWithDelta(110.0, 0.001);
        });

        it('overrides default parameter values', function () {
            $result = $this->toolAgent->executeTool('tc3', 'calculate_price', [
                'price' => 100.0,
                'taxRate' => 0.2,
            ]);

            expect($result->isSuccess())->toBeTrue();
            expect($result->result)->toEqualWithDelta(120.0, 0.001);
        });

        it('returns failure for non-existent tool', function () {
            $result = $this->toolAgent->executeTool('tc4', 'nonExistent', []);

            expect($result->isFailure())->toBeTrue();
            expect($result->error)->toContain("Tool 'nonExistent' not found");
        });

        it('returns failure when tool throws exception', function () {
            $agent = new class
            {
                use HasTools;

                protected array $tools = [];

                #[Tool('Failing tool')]
                public function failingTool(): never
                {
                    throw new RuntimeException('Something went wrong');
                }
            };

            $result = $agent->executeTool('tc5', 'failingTool', []);

            expect($result->isFailure())->toBeTrue();
            expect($result->error)->toBe('Something went wrong');
        });

        it('records execution duration', function () {
            $result = $this->toolAgent->executeTool('tc6', 'lookupCustomer', ['customerId' => 'C1']);

            expect($result->duration)->not->toBeNull();
            expect($result->duration)->toBeGreaterThanOrEqual(0.0);
        });
    });

    describe('executeToolCalls', function () {
        it('executes multiple tool calls', function () {
            $toolCalls = [
                [
                    'id' => 'tc1',
                    'function' => [
                        'name' => 'lookupCustomer',
                        'arguments' => '{"customerId": "C1"}',
                    ],
                ],
                [
                    'id' => 'tc2',
                    'function' => [
                        'name' => 'calculate_price',
                        'arguments' => '{"price": 50.0}',
                    ],
                ],
            ];

            $results = $this->toolAgent->executeToolCalls($toolCalls);

            expect($results)->toBeArray();
            expect(count($results))->toBe(2);
            expect($results[0]->isSuccess())->toBeTrue();
            expect($results[1]->isSuccess())->toBeTrue();
        });

        it('handles empty arguments gracefully', function () {
            $toolCalls = [
                [
                    'id' => 'tc1',
                    'function' => [
                        'name' => 'lookupCustomer',
                        'arguments' => '{}',
                    ],
                ],
            ];

            $results = $this->toolAgent->executeToolCalls($toolCalls);

            // Should return a failure since customerId is required
            expect($results[0]->isFailure())->toBeTrue();
        });
    });

    describe('invokeToolMethod missing required argument', function () {
        it('returns failure when required argument is missing', function () {
            $result = $this->toolAgent->executeTool('tc7', 'lookupCustomer', []);

            expect($result->isFailure())->toBeTrue();
            expect($result->error)->toContain('Missing required argument');
        });
    });
});
