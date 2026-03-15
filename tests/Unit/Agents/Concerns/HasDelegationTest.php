<?php

declare(strict_types=1);

use AgenticOrchestrator\Agents\Concerns\HasDelegation;
use AgenticOrchestrator\Agents\Concerns\HasTeamScope;
use AgenticOrchestrator\Contracts\AgentInterface;

describe('HasDelegation', function () {

    beforeEach(function () {
        $this->delegatingAgent = new class
        {
            use HasDelegation;
            use HasTeamScope;

            public bool $isSystem = false;

            protected array $capabilities = [
                'can_be_delegate' => true,
            ];

            public function getId(): string
            {
                return 'test-delegator';
            }

            public function getName(): string
            {
                return 'Test Delegator';
            }

            public function respond(string $message, array $context = []): object
            {
                return (object) ['content' => 'response'];
            }
        };
    });

    describe('canDelegate', function () {
        it('returns true by default when delegation is enabled and depth is zero', function () {
            expect($this->delegatingAgent->canDelegate())->toBeTrue();
        });

        it('returns false when delegation is disabled', function () {
            $this->delegatingAgent->disableDelegation();

            expect($this->delegatingAgent->canDelegate())->toBeFalse();
        });

        it('returns false when at max delegation depth', function () {
            $parent = Mockery::mock(AgentInterface::class);
            $this->delegatingAgent->setDelegationContext($parent, 5);
            $this->delegatingAgent->maxDelegationDepth(5);

            expect($this->delegatingAgent->canDelegate())->toBeFalse();
        });
    });

    describe('canBeDelegate', function () {
        it('returns true by default', function () {
            expect($this->delegatingAgent->canBeDelegate())->toBeTrue();
        });
    });

    describe('enableDelegation / disableDelegation', function () {
        it('enables delegation with fluent return', function () {
            $this->delegatingAgent->disableDelegation();
            $result = $this->delegatingAgent->enableDelegation();

            expect($result)->toBe($this->delegatingAgent);
            expect($this->delegatingAgent->canDelegate())->toBeTrue();
        });

        it('disables delegation with fluent return', function () {
            $result = $this->delegatingAgent->disableDelegation();

            expect($result)->toBe($this->delegatingAgent);
            expect($this->delegatingAgent->canDelegate())->toBeFalse();
        });
    });

    describe('maxDelegationDepth', function () {
        it('sets max depth with fluent return', function () {
            $result = $this->delegatingAgent->maxDelegationDepth(10);

            expect($result)->toBe($this->delegatingAgent);
        });
    });

    describe('setDelegationContext', function () {
        it('sets parent agent and depth', function () {
            $parent = Mockery::mock(AgentInterface::class);

            $this->delegatingAgent->setDelegationContext($parent, 3);

            expect($this->delegatingAgent->getParentAgent())->toBe($parent);
            expect($this->delegatingAgent->getDelegationDepth())->toBe(3);
        });
    });

    describe('getParentAgent', function () {
        it('returns null when not delegated', function () {
            expect($this->delegatingAgent->getParentAgent())->toBeNull();
        });

        it('returns the parent agent when delegated', function () {
            $parent = Mockery::mock(AgentInterface::class);
            $this->delegatingAgent->setDelegationContext($parent, 1);

            expect($this->delegatingAgent->getParentAgent())->toBe($parent);
        });
    });

    describe('getDelegationDepth', function () {
        it('returns zero by default', function () {
            expect($this->delegatingAgent->getDelegationDepth())->toBe(0);
        });

        it('returns the set depth', function () {
            $parent = Mockery::mock(AgentInterface::class);
            $this->delegatingAgent->setDelegationContext($parent, 4);

            expect($this->delegatingAgent->getDelegationDepth())->toBe(4);
        });
    });

    describe('isDelegated', function () {
        it('returns false when no parent is set', function () {
            expect($this->delegatingAgent->isDelegated())->toBeFalse();
        });

        it('returns true when parent is set', function () {
            $parent = Mockery::mock(AgentInterface::class);
            $this->delegatingAgent->setDelegationContext($parent, 1);

            expect($this->delegatingAgent->isDelegated())->toBeTrue();
        });
    });

    describe('getDelegationHistory', function () {
        it('returns empty array initially', function () {
            expect($this->delegatingAgent->getDelegationHistory())->toBe([]);
        });
    });

    describe('clearDelegationHistory', function () {
        it('clears the delegation history', function () {
            $this->delegatingAgent->clearDelegationHistory();

            expect($this->delegatingAgent->getDelegationHistory())->toBe([]);
        });
    });

    describe('delegate', function () {
        it('throws RuntimeException when delegation is disabled', function () {
            $this->delegatingAgent->disableDelegation();
            $target = Mockery::mock(AgentInterface::class);

            expect(fn () => $this->delegatingAgent->delegate($target, 'test'))
                ->toThrow(RuntimeException::class, 'Delegation is disabled');
        });

        it('throws RuntimeException when max depth exceeded', function () {
            $parent = Mockery::mock(AgentInterface::class);
            $this->delegatingAgent->setDelegationContext($parent, 5);
            $this->delegatingAgent->maxDelegationDepth(5);

            $target = Mockery::mock(AgentInterface::class);

            expect(fn () => $this->delegatingAgent->delegate($target, 'test'))
                ->toThrow(RuntimeException::class, 'Maximum delegation depth');
        });
    });

    describe('delegateParallel', function () {
        it('throws when agent count does not match message count', function () {
            $agent1 = Mockery::mock(AgentInterface::class);
            $agent2 = Mockery::mock(AgentInterface::class);

            expect(fn () => $this->delegatingAgent->delegateParallel(
                [$agent1, $agent2],
                ['msg1', 'msg2', 'msg3'],
            ))->toThrow(RuntimeException::class, 'Number of agents must match');
        });
    });
});
