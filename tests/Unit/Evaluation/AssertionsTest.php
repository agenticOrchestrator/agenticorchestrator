<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Evaluation;

use AgenticOrchestrator\Evaluation\Assertions\ContainsAssertion;
use AgenticOrchestrator\Evaluation\Assertions\JsonAssertion;
use AgenticOrchestrator\Evaluation\Assertions\LengthAssertion;
use AgenticOrchestrator\Evaluation\Assertions\MatchesPatternAssertion;
use AgenticOrchestrator\Evaluation\Assertions\NotContainsAssertion;
use AgenticOrchestrator\Evaluation\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class AssertionsTest extends PHPUnitTestCase
{
    private TestCase $testCase;

    protected function setUp(): void
    {
        $this->testCase = new TestCase(name: 'test', input: 'test input');
    }

    #[Test]
    public function contains_assertion_passes_when_string_found(): void
    {
        $assertion = new ContainsAssertion;

        $result = $assertion->evaluate('Hello world', 'hello', $this->testCase);

        $this->assertTrue($result->passed);
    }

    #[Test]
    public function contains_assertion_fails_when_string_missing(): void
    {
        $assertion = new ContainsAssertion;

        $result = $assertion->evaluate('Hello world', 'goodbye', $this->testCase);

        $this->assertFalse($result->passed);
    }

    #[Test]
    public function contains_assertion_handles_array_of_strings(): void
    {
        $assertion = new ContainsAssertion;

        $result = $assertion->evaluate('Hello beautiful world', ['hello', 'world'], $this->testCase);

        $this->assertTrue($result->passed);
    }

    #[Test]
    public function not_contains_assertion_passes_when_string_missing(): void
    {
        $assertion = new NotContainsAssertion;

        $result = $assertion->evaluate('Hello world', 'goodbye', $this->testCase);

        $this->assertTrue($result->passed);
    }

    #[Test]
    public function not_contains_assertion_fails_when_string_found(): void
    {
        $assertion = new NotContainsAssertion;

        $result = $assertion->evaluate('Hello world', 'hello', $this->testCase);

        $this->assertFalse($result->passed);
    }

    #[Test]
    public function matches_pattern_passes_when_pattern_matches(): void
    {
        $assertion = new MatchesPatternAssertion;

        $result = $assertion->evaluate('Order #12345', '/Order #\d+/', $this->testCase);

        $this->assertTrue($result->passed);
    }

    #[Test]
    public function matches_pattern_fails_when_pattern_not_matches(): void
    {
        $assertion = new MatchesPatternAssertion;

        $result = $assertion->evaluate('No order here', '/Order #\d+/', $this->testCase);

        $this->assertFalse($result->passed);
    }

    #[Test]
    public function length_assertion_passes_for_exact_length(): void
    {
        $assertion = new LengthAssertion;

        $result = $assertion->evaluate('Hello', 5, $this->testCase);

        $this->assertTrue($result->passed);
    }

    #[Test]
    public function length_assertion_passes_for_range(): void
    {
        $assertion = new LengthAssertion;

        $result = $assertion->evaluate('Hello world', ['min' => 5, 'max' => 20], $this->testCase);

        $this->assertTrue($result->passed);
    }

    #[Test]
    public function length_assertion_fails_when_too_short(): void
    {
        $assertion = new LengthAssertion;

        $result = $assertion->evaluate('Hi', ['min' => 5], $this->testCase);

        $this->assertFalse($result->passed);
    }

    #[Test]
    public function json_assertion_passes_for_valid_json(): void
    {
        $assertion = new JsonAssertion;

        $result = $assertion->evaluate('{"name": "John"}', true, $this->testCase);

        $this->assertTrue($result->passed);
    }

    #[Test]
    public function json_assertion_fails_for_invalid_json(): void
    {
        $assertion = new JsonAssertion;

        $result = $assertion->evaluate('not json', true, $this->testCase);

        $this->assertFalse($result->passed);
    }

    #[Test]
    public function json_assertion_validates_required_keys(): void
    {
        $assertion = new JsonAssertion;

        $result = $assertion->evaluate(
            '{"name": "John", "age": 30}',
            ['has_keys' => ['name', 'age']],
            $this->testCase
        );

        $this->assertTrue($result->passed);
    }

    #[Test]
    public function json_assertion_fails_for_missing_keys(): void
    {
        $assertion = new JsonAssertion;

        $result = $assertion->evaluate(
            '{"name": "John"}',
            ['has_keys' => ['name', 'email']],
            $this->testCase
        );

        $this->assertFalse($result->passed);
    }

    #[Test]
    public function json_assertion_extracts_from_markdown(): void
    {
        $assertion = new JsonAssertion;

        $output = "Here is the response:\n```json\n{\"status\": \"ok\"}\n```\nDone.";
        $result = $assertion->evaluate($output, true, $this->testCase);

        $this->assertTrue($result->passed);
    }
}
