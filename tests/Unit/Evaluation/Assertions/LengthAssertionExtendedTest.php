<?php

declare(strict_types=1);

use AgenticOrchestrator\Evaluation\Assertions\LengthAssertion;
use AgenticOrchestrator\Evaluation\TestCase as EvalTestCase;

describe('LengthAssertion', function () {
    beforeEach(function () {
        $this->assertion = new LengthAssertion;
        $this->testCase = new EvalTestCase(name: 'length-test', input: 'test input');
    });

    describe('name', function () {
        it('returns length as the assertion name', function () {
            expect($this->assertion->name())->toBe('length');
        });
    });

    describe('exact integer config', function () {
        it('passes when output length matches exact integer', function () {
            $result = $this->assertion->evaluate('Hello', 5, $this->testCase);

            expect($result->passed)->toBeTrue()
                ->and($result->name)->toBe('length')
                ->and($result->expected)->toBe(5)
                ->and($result->actual)->toBe(5)
                ->and($result->message)->toContain('exactly 5 characters');
        });

        it('fails when output length does not match exact integer', function () {
            $result = $this->assertion->evaluate('Hello World', 5, $this->testCase);

            expect($result->passed)->toBeFalse()
                ->and($result->expected)->toBe(5)
                ->and($result->actual)->toBe(11)
                ->and($result->message)->toContain('Expected length 5, got 11');
        });

        it('passes for empty string with exact zero', function () {
            $result = $this->assertion->evaluate('', 0, $this->testCase);

            expect($result->passed)->toBeTrue()
                ->and($result->actual)->toBe(0);
        });

        it('handles multibyte characters correctly with exact length', function () {
            // 3 multibyte characters
            $result = $this->assertion->evaluate("\u{00E9}\u{00E9}\u{00E9}", 3, $this->testCase);

            expect($result->passed)->toBeTrue()
                ->and($result->actual)->toBe(3);
        });
    });

    describe('array config with named keys', function () {
        it('passes when length is within min and max bounds', function () {
            $result = $this->assertion->evaluate('Hello World', ['min' => 5, 'max' => 20], $this->testCase);

            expect($result->passed)->toBeTrue()
                ->and($result->actual)->toBe(11)
                ->and($result->message)->toContain('within bounds');
        });

        it('fails when length is below minimum', function () {
            $result = $this->assertion->evaluate('Hi', ['min' => 5, 'max' => 20], $this->testCase);

            expect($result->passed)->toBeFalse()
                ->and($result->actual)->toBe(2)
                ->and($result->message)->toContain('less than minimum 5');
        });

        it('fails when length exceeds maximum', function () {
            $result = $this->assertion->evaluate('This is a very long string', ['min' => 1, 'max' => 10], $this->testCase);

            expect($result->passed)->toBeFalse()
                ->and($result->message)->toContain('exceeds maximum 10');
        });

        it('passes with only min specified when above minimum', function () {
            $result = $this->assertion->evaluate('Hello World', ['min' => 5], $this->testCase);

            expect($result->passed)->toBeTrue()
                ->and($result->expected)->toBe(['min' => 5, 'max' => null]);
        });

        it('passes with only max specified when below maximum', function () {
            $result = $this->assertion->evaluate('Hi', ['max' => 10], $this->testCase);

            expect($result->passed)->toBeTrue()
                ->and($result->expected)->toBe(['min' => null, 'max' => 10]);
        });

        it('fails with only min specified when below minimum', function () {
            $result = $this->assertion->evaluate('Hi', ['min' => 10], $this->testCase);

            expect($result->passed)->toBeFalse();
        });

        it('fails with only max specified when above maximum', function () {
            $result = $this->assertion->evaluate('Hello World', ['max' => 5], $this->testCase);

            expect($result->passed)->toBeFalse();
        });

        it('passes when length equals exactly the min boundary', function () {
            $result = $this->assertion->evaluate('Hello', ['min' => 5, 'max' => 10], $this->testCase);

            expect($result->passed)->toBeTrue();
        });

        it('passes when length equals exactly the max boundary', function () {
            $result = $this->assertion->evaluate('HelloWorld', ['min' => 5, 'max' => 10], $this->testCase);

            expect($result->passed)->toBeTrue();
        });
    });

    describe('array config with positional keys', function () {
        it('passes when length is within positional min and max', function () {
            $result = $this->assertion->evaluate('Hello', [3, 10], $this->testCase);

            expect($result->passed)->toBeTrue()
                ->and($result->actual)->toBe(5);
        });

        it('fails when length is below positional min', function () {
            $result = $this->assertion->evaluate('Hi', [5, 10], $this->testCase);

            expect($result->passed)->toBeFalse()
                ->and($result->message)->toContain('less than minimum 5');
        });

        it('fails when length exceeds positional max', function () {
            $result = $this->assertion->evaluate('Hello World!', [1, 5], $this->testCase);

            expect($result->passed)->toBeFalse()
                ->and($result->message)->toContain('exceeds maximum 5');
        });
    });

    describe('invalid config', function () {
        it('fails with invalid config type (string)', function () {
            $result = $this->assertion->evaluate('Hello', 'invalid', $this->testCase);

            expect($result->passed)->toBeFalse()
                ->and($result->message)->toBe('Invalid length configuration');
        });

        it('fails with invalid config type (float)', function () {
            $result = $this->assertion->evaluate('Hello', 5.5, $this->testCase);

            expect($result->passed)->toBeFalse()
                ->and($result->message)->toBe('Invalid length configuration');
        });

        it('fails with invalid config type (bool)', function () {
            $result = $this->assertion->evaluate('Hello', true, $this->testCase);

            expect($result->passed)->toBeFalse()
                ->and($result->message)->toBe('Invalid length configuration');
        });

        it('fails with null config', function () {
            $result = $this->assertion->evaluate('Hello', null, $this->testCase);

            expect($result->passed)->toBeFalse()
                ->and($result->message)->toBe('Invalid length configuration');
        });
    });

    describe('edge cases', function () {
        it('passes with empty array config (no min or max)', function () {
            $result = $this->assertion->evaluate('anything', [], $this->testCase);

            expect($result->passed)->toBeTrue()
                ->and($result->expected)->toBe(['min' => null, 'max' => null]);
        });

        it('handles very long strings', function () {
            $longString = str_repeat('a', 10000);
            $result = $this->assertion->evaluate($longString, ['min' => 9999, 'max' => 10001], $this->testCase);

            expect($result->passed)->toBeTrue()
                ->and($result->actual)->toBe(10000);
        });
    });
});
