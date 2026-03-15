<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Tracking;

use AgenticOrchestrator\Tracking\LatencyRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LatencyRecorder::class)]
class LatencyRecorderTest extends TestCase
{
    #[Test]
    public function it_records_latency(): void
    {
        $recorder = LatencyRecorder::make();

        $recorder->record('llm_call', 150.5);
        $recorder->record('llm_call', 200.0);

        $stats = $recorder->stats('llm_call');

        $this->assertSame(2, $stats['count']);
        $this->assertSame(150.5, $stats['min']);
        $this->assertSame(200.0, $stats['max']);
    }

    #[Test]
    public function it_calculates_average(): void
    {
        $recorder = LatencyRecorder::make();

        $recorder->record('api', 100.0);
        $recorder->record('api', 200.0);
        $recorder->record('api', 300.0);

        $stats = $recorder->stats('api');

        $this->assertEqualsWithDelta(200.0, $stats['avg'], 0.01);
    }

    #[Test]
    public function it_calculates_percentiles(): void
    {
        $recorder = LatencyRecorder::make();

        for ($i = 1; $i <= 100; $i++) {
            $recorder->record('test', (float) $i);
        }

        $stats = $recorder->stats('test');

        $this->assertEqualsWithDelta(50.0, $stats['median'], 1.0);
        $this->assertEqualsWithDelta(95.0, $stats['p95'], 1.0);
        $this->assertEqualsWithDelta(99.0, $stats['p99'], 1.0);
    }

    #[Test]
    public function it_measures_callback(): void
    {
        $recorder = LatencyRecorder::make();

        $measurement = $recorder->measure('operation', function () {
            usleep(10000); // 10ms

            return 'result';
        });

        $this->assertSame('result', $measurement['result']);
        $this->assertGreaterThan(5, $measurement['latency_ms']);
    }

    #[Test]
    public function it_returns_empty_stats_for_unknown_category(): void
    {
        $recorder = LatencyRecorder::make();

        $stats = $recorder->stats('unknown');

        $this->assertSame(0, $stats['count']);
        $this->assertSame(0, $stats['avg']);
    }

    #[Test]
    public function it_clears_recordings(): void
    {
        $recorder = LatencyRecorder::make();

        $recorder->record('test', 100.0);
        $recorder->clear('test');

        $this->assertEmpty($recorder->get('test'));
    }

    #[Test]
    public function it_limits_recordings(): void
    {
        $recorder = LatencyRecorder::make(maxRecordings: 5);

        for ($i = 0; $i < 10; $i++) {
            $recorder->record('test', (float) $i);
        }

        $recordings = $recorder->get('test');

        $this->assertCount(5, $recordings);
        $this->assertSame(5.0, $recordings[0]); // Oldest kept
    }

    #[Test]
    public function it_exports_to_array(): void
    {
        $recorder = LatencyRecorder::make();

        $recorder->record('cat1', 100.0);
        $recorder->record('cat2', 200.0);

        $export = $recorder->toArray();

        $this->assertArrayHasKey('recordings', $export);
        $this->assertArrayHasKey('stats', $export);
        $this->assertArrayHasKey('cat1', $export['stats']);
        $this->assertArrayHasKey('cat2', $export['stats']);
    }
}
