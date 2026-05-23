<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Patterns;

/**
 * Parallel Options - Immutable execution settings for a ParallelPattern.
 *
 * Passed from the pattern to its driver so the driver stays
 * independent of how the pattern was configured.
 */
final readonly class ParallelOptions
{
    /**
     * @param  string  $name  The pattern name (used for context keys and logging)
     * @param  int  $failureThreshold  How many branch failures are tolerated
     * @param  bool  $waitForAll  Wait for every branch, or return on first success (race)
     * @param  int  $maxConcurrency  Hint for the maximum number of concurrent branches
     */
    public function __construct(
        public string $name = 'parallel',
        public int $failureThreshold = 0,
        public bool $waitForAll = true,
        public int $maxConcurrency = 10,
    ) {}
}
