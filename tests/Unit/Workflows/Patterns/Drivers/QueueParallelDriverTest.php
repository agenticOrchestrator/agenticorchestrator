<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows\Patterns\Drivers;

use AgenticOrchestrator\Tests\Fixtures\Workflows\StaticStep;
use AgenticOrchestrator\Tests\TestCase;
use AgenticOrchestrator\Workflows\Jobs\RunBranchStep;
use AgenticOrchestrator\Workflows\Patterns\Drivers\QueueParallelDriver;
use AgenticOrchestrator\Workflows\Patterns\ParallelOptions;
use AgenticOrchestrator\Workflows\WorkflowContext;
use AgenticOrchestrator\Workflows\WorkflowDefinition;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(QueueParallelDriver::class)]
class QueueParallelDriverTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Run branch jobs inline so the driver's await/collect can be exercised.
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.batching', [
            'driver' => 'database',
            'database' => 'testing',
            'table' => 'job_batches',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Bus::batch() needs the batch repository table.
        Schema::create('job_batches', function ($table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });
    }

    private function driver(): QueueParallelDriver
    {
        // timeout 5s, fast poll - sync jobs finish immediately anyway.
        return new QueueParallelDriver(timeout: 5, pollIntervalMs: 10);
    }

    #[Test]
    public function it_runs_all_branches_and_merges_outputs(): void
    {
        $context = new WorkflowContext;

        $result = $this->driver()->run([
            new StaticStep('one', ['n' => 1]),
            new StaticStep('two', ['n' => 2]),
            new StaticStep('three', ['n' => 3]),
        ], $context, new ParallelOptions(name: 'fan'));

        $this->assertTrue($result->isSuccess());
        $this->assertSame(['n' => 1], $result->output['one']);
        $this->assertSame(['n' => 2], $result->output['two']);
        $this->assertSame(['n' => 3], $result->output['three']);
    }

    #[Test]
    public function it_marks_branch_steps_completed_in_the_context(): void
    {
        $context = new WorkflowContext;

        $this->driver()->run([
            new StaticStep('one', ['n' => 1]),
            new StaticStep('two', ['n' => 2]),
        ], $context, new ParallelOptions(name: 'fan'));

        $this->assertTrue($context->isStepCompleted('one'));
        $this->assertTrue($context->isStepCompleted('two'));
    }

    #[Test]
    public function it_fails_when_too_many_branches_fail(): void
    {
        $context = new WorkflowContext;

        $result = $this->driver()->run([
            new StaticStep('one', shouldFail: true),
            new StaticStep('two', ['n' => 2]),
        ], $context, new ParallelOptions(name: 'fan'));

        $this->assertTrue($result->isFailed());
    }

    #[Test]
    public function it_allows_configured_failures(): void
    {
        $context = new WorkflowContext;

        $result = $this->driver()->run([
            new StaticStep('one', shouldFail: true),
            new StaticStep('two', ['n' => 2]),
            new StaticStep('three', ['n' => 3]),
        ], $context, new ParallelOptions(name: 'fan', failureThreshold: 1));

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('two', $result->output);
        $this->assertArrayNotHasKey('one', $result->output);
    }

    #[Test]
    public function it_returns_first_success_in_race_mode(): void
    {
        $context = new WorkflowContext;

        $result = $this->driver()->run([
            new StaticStep('fast', ['winner' => true]),
            new StaticStep('slow', ['winner' => false]),
        ], $context, new ParallelOptions(name: 'race', waitForAll: false));

        $this->assertTrue($result->isSuccess());
        $this->assertSame('fast', $result->getMeta('winner'));
    }

    #[Test]
    public function it_returns_empty_success_with_no_steps(): void
    {
        $result = $this->driver()->run([], new WorkflowContext, new ParallelOptions);

        $this->assertTrue($result->isSuccess());
        $this->assertSame([], $result->output);
    }

    #[Test]
    public function it_skips_already_completed_branches_on_resumption(): void
    {
        Bus::fake();

        $context = new WorkflowContext;
        $context->markStepCompleted('one');
        $context->markStepCompleted('two');

        $result = $this->driver()->run([
            new StaticStep('one', ['n' => 1]),
            new StaticStep('two', ['n' => 2]),
        ], $context, new ParallelOptions(name: 'fan'));

        $this->assertTrue($result->isSuccess());
        Bus::assertNothingBatched();
    }

    #[Test]
    public function it_dispatches_one_job_per_pending_branch(): void
    {
        Bus::fake();

        // Faked jobs never run, so don't let await() poll - timeout 0 = one pass.
        $driver = new QueueParallelDriver(timeout: 0, pollIntervalMs: 1);

        $driver->run([
            new StaticStep('one', ['n' => 1]),
            new StaticStep('two', ['n' => 2]),
        ], new WorkflowContext, new ParallelOptions(name: 'fan'));

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 2
                && $batch->jobs->every(fn ($job) => $job instanceof RunBranchStep);
        });
    }

    #[Test]
    public function parallel_queued_definition_runs_branches_through_the_queue_driver(): void
    {
        $definition = WorkflowDefinition::create()
            ->name('queued')
            ->parallelQueued('fan', [
                new StaticStep('one', ['n' => 1]),
                new StaticStep('two', ['n' => 2]),
            ]);

        $result = $definition->getStep('fan')->execute(new WorkflowContext);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('queue', $result->getMeta('driver'));
        $this->assertSame(['n' => 1], $result->output['one']);
        $this->assertSame(['n' => 2], $result->output['two']);
    }
}
