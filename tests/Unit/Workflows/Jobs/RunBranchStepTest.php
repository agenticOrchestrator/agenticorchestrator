<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows\Jobs;

use AgenticOrchestrator\Tests\Fixtures\Workflows\StaticStep;
use AgenticOrchestrator\Tests\TestCase;
use AgenticOrchestrator\Workflows\Jobs\RunBranchStep;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(RunBranchStep::class)]
class RunBranchStepTest extends TestCase
{
    #[Test]
    public function it_writes_a_successful_result_to_the_cache(): void
    {
        $job = new RunBranchStep(
            runKey: 'run-1',
            branchName: 'alpha',
            step: new StaticStep('alpha', ['value' => 42]),
            contextState: (new WorkflowContext)->getState(),
        );

        $job->handle();

        $cached = Cache::get(RunBranchStep::cacheKey('run-1', 'alpha'));

        $this->assertSame(StepResult::STATUS_SUCCESS, $cached['status']);
        $this->assertSame(['value' => 42], $cached['output']);
    }

    #[Test]
    public function it_writes_a_failed_result_to_the_cache(): void
    {
        $job = new RunBranchStep(
            runKey: 'run-2',
            branchName: 'beta',
            step: new StaticStep('beta', shouldFail: true),
            contextState: (new WorkflowContext)->getState(),
        );

        $job->handle();

        $cached = Cache::get(RunBranchStep::cacheKey('run-2', 'beta'));

        $this->assertSame(StepResult::STATUS_FAILED, $cached['status']);
        $this->assertSame('beta failed', $cached['message']);
    }

    #[Test]
    public function it_builds_a_namespaced_cache_key(): void
    {
        $this->assertSame(
            'agent-orchestrator:parallel:run-1:alpha',
            RunBranchStep::cacheKey('run-1', 'alpha'),
        );
    }
}
