<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Evaluation;

use AgenticOrchestrator\Evaluation\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

#[CoversClass(TestCase::class)]
class TestCaseTest extends PHPUnitTestCase
{
    #[Test]
    public function it_creates_test_case(): void
    {
        $testCase = new TestCase(
            name: 'test-greeting',
            input: 'Hello',
            assertions: ['contains' => ['hello', 'hi']],
            metrics: ['relevance' => ['threshold' => 0.8]],
        );

        $this->assertSame('test-greeting', $testCase->name);
        $this->assertSame('Hello', $testCase->input);
        $this->assertTrue($testCase->hasAssertions());
        $this->assertTrue($testCase->hasMetrics());
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $testCase = TestCase::fromArray([
            'name' => 'test-1',
            'input' => 'Test input',
            'assertions' => ['contains' => 'expected'],
            'expected_output' => 'Expected response',
        ]);

        $this->assertSame('test-1', $testCase->name);
        $this->assertSame('Test input', $testCase->input);
        $this->assertTrue($testCase->hasExpectedOutput());
        $this->assertSame('Expected response', $testCase->expectedOutput);
    }

    #[Test]
    public function it_gets_specific_assertion(): void
    {
        $testCase = new TestCase(
            name: 'test-1',
            input: 'Input',
            assertions: [
                'contains' => ['word1', 'word2'],
                'matches' => '/pattern/',
            ],
        );

        $this->assertSame(['word1', 'word2'], $testCase->getAssertion('contains'));
        $this->assertSame('/pattern/', $testCase->getAssertion('matches'));
        $this->assertNull($testCase->getAssertion('unknown'));
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $testCase = new TestCase(
            name: 'test-1',
            input: 'Input',
            assertions: ['contains' => 'test'],
            timeout: 60,
        );

        $array = $testCase->toArray();

        $this->assertSame('test-1', $array['name']);
        $this->assertSame('Input', $array['input']);
        $this->assertSame(60, $array['timeout']);
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $testCase = new TestCase(name: 'test-1', input: 'Input');

        $json = json_encode($testCase);

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('test-1', $decoded['name']);
    }
}
