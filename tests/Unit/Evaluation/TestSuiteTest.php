<?php

declare(strict_types=1);

use AgenticOrchestrator\Evaluation\Contracts\AssertionInterface;
use AgenticOrchestrator\Evaluation\LlmJudge;
use AgenticOrchestrator\Evaluation\TestSuite;

describe('TestSuite', function () {
    it('registers default assertions on construction', function () {
        $suite = new class extends TestSuite
        {
            protected string $agent = 'App\\Agents\\TestAgent';

            public function testCases(): array
            {
                return [];
            }

            public function getAssertions(): array
            {
                return $this->assertions;
            }
        };

        $assertions = $suite->getAssertions();

        expect($assertions)->toHaveCount(5);
        expect(array_keys($assertions))->toBe(['contains', 'not_contains', 'matches', 'length', 'json']);
    });

    it('creates instance via static make method', function () {
        $suite = (new class extends TestSuite
        {
            protected string $agent = 'App\\Agents\\TestAgent';

            public function testCases(): array
            {
                return [];
            }
        })::make();

        expect($suite)->toBeInstanceOf(TestSuite::class);
    });

    it('sets team via forTeam method', function () {
        $suite = new class extends TestSuite
        {
            protected string $agent = 'App\\Agents\\TestAgent';

            public function testCases(): array
            {
                return [];
            }

            public function getTeam(): int|string|object|null
            {
                return $this->team;
            }
        };

        $result = $suite->forTeam(42);

        expect($result)->toBe($suite);
        expect($suite->getTeam())->toBe(42);
    });

    it('sets team with string', function () {
        $suite = new class extends TestSuite
        {
            protected string $agent = 'App\\Agents\\TestAgent';

            public function testCases(): array
            {
                return [];
            }

            public function getTeam(): int|string|object|null
            {
                return $this->team;
            }
        };

        $suite->forTeam('team-alpha');

        expect($suite->getTeam())->toBe('team-alpha');
    });

    it('sets LLM judge', function () {
        $judge = Mockery::mock(LlmJudge::class);

        $suite = new class extends TestSuite
        {
            protected string $agent = 'App\\Agents\\TestAgent';

            public function testCases(): array
            {
                return [];
            }

            public function getJudgeInstance(): ?LlmJudge
            {
                return $this->judge;
            }
        };

        $result = $suite->withJudge($judge);

        expect($result)->toBe($suite);
        expect($suite->getJudgeInstance())->toBe($judge);
    });

    it('disables metrics evaluation', function () {
        $suite = new class extends TestSuite
        {
            protected string $agent = 'App\\Agents\\TestAgent';

            public function testCases(): array
            {
                return [];
            }

            public function isMetricsEnabled(): bool
            {
                return $this->evaluateMetrics;
            }
        };

        expect($suite->isMetricsEnabled())->toBeTrue();

        $result = $suite->withoutMetrics();

        expect($result)->toBe($suite);
        expect($suite->isMetricsEnabled())->toBeFalse();
    });

    it('registers custom assertion', function () {
        $assertion = Mockery::mock(AssertionInterface::class);
        $assertion->shouldReceive('name')->andReturn('custom_check');

        $suite = new class extends TestSuite
        {
            protected string $agent = 'App\\Agents\\TestAgent';

            public function testCases(): array
            {
                return [];
            }

            public function getAssertions(): array
            {
                return $this->assertions;
            }
        };

        $result = $suite->registerAssertion($assertion);

        expect($result)->toBe($suite);
        $assertions = $suite->getAssertions();
        expect($assertions)->toHaveKey('custom_check');
        expect($assertions['custom_check'])->toBe($assertion);
    });

    it('returns agent class', function () {
        $suite = new class extends TestSuite
        {
            protected string $agent = 'App\\Agents\\MyCustomAgent';

            public function testCases(): array
            {
                return [];
            }
        };

        expect($suite->getAgentClass())->toBe('App\\Agents\\MyCustomAgent');
    });

    it('supports fluent chaining', function () {
        $judge = Mockery::mock(LlmJudge::class);

        $suite = new class extends TestSuite
        {
            protected string $agent = 'App\\Agents\\TestAgent';

            public function testCases(): array
            {
                return [];
            }
        };

        $result = $suite->forTeam(1)->withJudge($judge)->withoutMetrics();

        expect($result)->toBeInstanceOf(TestSuite::class);
    });
});
