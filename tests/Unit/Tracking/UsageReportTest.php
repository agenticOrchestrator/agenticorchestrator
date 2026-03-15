<?php

declare(strict_types=1);

use AgenticOrchestrator\Tracking\UsageReport;

describe('UsageReport', function () {
    it('creates an instance via make', function () {
        $report = UsageReport::make();

        expect($report)->toBeInstanceOf(UsageReport::class);
    });

    it('creates an instance for a team using an integer', function () {
        $report = UsageReport::forTeam(42);

        expect($report)->toBeInstanceOf(UsageReport::class);
    });

    it('creates an instance for a team using an object', function () {
        $team = new stdClass;
        $team->id = 99;

        $report = UsageReport::forTeam($team);

        expect($report)->toBeInstanceOf(UsageReport::class);
    });

    it('creates an instance for an agent class', function () {
        $report = UsageReport::forAgent('App\\Agents\\WriterAgent');

        expect($report)->toBeInstanceOf(UsageReport::class);
    });

    it('supports fluent date range configuration', function () {
        $from = new DateTimeImmutable('2026-01-01');
        $to = new DateTimeImmutable('2026-01-31');

        $report = UsageReport::make()
            ->dateRange($from, $to);

        expect($report)->toBeInstanceOf(UsageReport::class);
    });

    it('supports fluent from and to methods', function () {
        $report = UsageReport::make()
            ->from(new DateTimeImmutable('2026-01-01'))
            ->to(new DateTimeImmutable('2026-01-31'));

        expect($report)->toBeInstanceOf(UsageReport::class);
    });

    it('supports groupBy configuration', function () {
        $report = UsageReport::make()->groupBy('month');

        expect($report)->toBeInstanceOf(UsageReport::class);
    });

    it('supports daily grouping shorthand', function () {
        $report = UsageReport::make()->daily();

        expect($report)->toBeInstanceOf(UsageReport::class);
    });

    it('supports weekly grouping shorthand', function () {
        $report = UsageReport::make()->weekly();

        expect($report)->toBeInstanceOf(UsageReport::class);
    });

    it('supports monthly grouping shorthand', function () {
        $report = UsageReport::make()->monthly();

        expect($report)->toBeInstanceOf(UsageReport::class);
    });

    it('returns default values for accessors before generate', function () {
        $report = UsageReport::make();

        expect($report->totalCost())->toBe(0.0)
            ->and($report->totalTokens())->toBe(0)
            ->and($report->totalRequests())->toBe(0)
            ->and($report->summary())->toBe([])
            ->and($report->byAgent())->toBe([])
            ->and($report->byModel())->toBe([])
            ->and($report->timeline())->toBe([]);
    });

    it('implements JsonSerializable', function () {
        $report = UsageReport::make();

        expect($report)->toBeInstanceOf(JsonSerializable::class);
    });

    it('supports full fluent chaining', function () {
        $report = UsageReport::forTeam(1)
            ->from(new DateTimeImmutable('2026-01-01'))
            ->to(new DateTimeImmutable('2026-12-31'))
            ->monthly();

        expect($report)->toBeInstanceOf(UsageReport::class);
    });
});
