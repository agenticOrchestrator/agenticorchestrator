<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows\Steps;

use AgenticOrchestrator\Workflows\Steps\DelayStep;
use AgenticOrchestrator\Workflows\WorkflowContext;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DelayStep::class)]
class DelayStepTest extends TestCase
{
    #[Test]
    public function it_delays_for_specified_seconds(): void
    {
        $sleptFor = null;

        $step = DelayStep::forSeconds(5)
            ->useSleepFunction(function (int $seconds) use (&$sleptFor) {
                $sleptFor = $seconds;
            });

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(5, $sleptFor);
        $this->assertSame(5, $result->getMeta('delayed_seconds'));
    }

    #[Test]
    public function it_delays_until_timestamp(): void
    {
        $sleptFor = null;
        $futureTime = new DateTimeImmutable('+10 seconds');

        $step = DelayStep::until($futureTime)
            ->useSleepFunction(function (int $seconds) use (&$sleptFor) {
                $sleptFor = $seconds;
            });

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertNotNull($sleptFor);
        $this->assertGreaterThanOrEqual(9, $sleptFor);
        $this->assertLessThanOrEqual(11, $sleptFor);
    }

    #[Test]
    public function it_does_not_sleep_for_past_timestamp(): void
    {
        $sleptFor = null;
        $pastTime = new DateTimeImmutable('-10 seconds');

        $step = DelayStep::until($pastTime)
            ->useSleepFunction(function (int $seconds) use (&$sleptFor) {
                $sleptFor = $seconds;
            });

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertNull($sleptFor);
    }

    #[Test]
    public function it_handles_zero_seconds(): void
    {
        $sleptFor = null;

        $step = DelayStep::forSeconds(0)
            ->useSleepFunction(function (int $seconds) use (&$sleptFor) {
                $sleptFor = $seconds;
            });

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertNull($sleptFor); // Should not call sleep for 0
    }

    #[Test]
    public function it_has_correct_name(): void
    {
        $step = DelayStep::forSeconds(1);

        $this->assertSame('delay', $step->getName());
    }
}
