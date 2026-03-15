<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Patterns;

use AgenticOrchestrator\Contracts\StepInterface;
use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use Closure;
use Throwable;

/**
 * Map-Reduce Pattern - Maps over items in parallel, then reduces results.
 *
 * Applies a step/callback to each item in a collection (map phase),
 * then aggregates the results with a reducer function (reduce phase).
 */
class MapReducePattern implements StepInterface
{
    /**
     * Pattern name.
     */
    protected string $name = 'map_reduce';

    /**
     * The collection or context key to map over.
     *
     * @var string|Closure(WorkflowContext): iterable
     */
    protected string|Closure $collection;

    /**
     * The mapper step or callback.
     *
     * @var StepInterface|Closure(mixed, int, WorkflowContext): mixed
     */
    protected StepInterface|Closure $mapper;

    /**
     * The reducer callback.
     *
     * @var Closure(mixed, mixed, string|int): mixed
     */
    protected Closure $reducer;

    /**
     * Initial value for the reducer.
     */
    protected mixed $initialValue;

    /**
     * How many mapper failures are acceptable.
     */
    protected int $failureThreshold = 0;

    /**
     * Create a new map-reduce pattern.
     *
     * @param  string|Closure(WorkflowContext): iterable  $collection
     * @param  StepInterface|Closure(mixed, int, WorkflowContext): mixed  $mapper
     * @param  Closure(mixed, mixed, string|int): mixed  $reducer
     */
    public function __construct(
        string|Closure $collection,
        StepInterface|Closure $mapper,
        Closure $reducer,
        mixed $initialValue = null,
    ) {
        $this->collection = $collection;
        $this->mapper = $mapper;
        $this->reducer = $reducer;
        $this->initialValue = $initialValue;
    }

    /**
     * Create a map-reduce pattern.
     *
     * @param  string|Closure(WorkflowContext): iterable  $collection
     * @param  StepInterface|Closure(mixed, int, WorkflowContext): mixed  $mapper
     * @param  Closure(mixed, mixed, string|int): mixed  $reducer
     */
    public static function make(
        string|Closure $collection,
        StepInterface|Closure $mapper,
        Closure $reducer,
        mixed $initialValue = null,
    ): static {
        return new static($collection, $mapper, $reducer, $initialValue);
    }

    /**
     * Set the failure threshold.
     */
    public function allowFailures(int $count): static
    {
        $this->failureThreshold = $count;

        return $this;
    }

    /**
     * Execute the map-reduce pattern.
     */
    public function execute(WorkflowContext $context): StepResult
    {
        // Resolve collection
        $items = $this->resolveCollection($context);
        $mapResults = [];
        $failures = 0;

        // Map phase
        $index = 0;
        foreach ($items as $key => $item) {
            try {
                if ($this->mapper instanceof StepInterface) {
                    $context->set('map_item', $item);
                    $context->set('map_index', $index);
                    $context->set('map_key', $key);

                    $result = $this->mapper->execute($context);

                    if ($result->isSuccess()) {
                        $mapResults[$key] = $result->output;
                    } else {
                        $failures++;
                        if ($failures > $this->failureThreshold) {
                            return StepResult::failed(
                                "Map phase failed: too many failures ({$failures} > {$this->failureThreshold})",
                                metadata: ['map_results' => $mapResults, 'failures' => $failures]
                            );
                        }
                    }
                } else {
                    $mapResults[$key] = ($this->mapper)($item, $index, $context);
                }
            } catch (Throwable $e) {
                $failures++;
                if ($failures > $this->failureThreshold) {
                    return StepResult::failed(
                        "Map phase failed at key '{$key}': ".$e->getMessage(),
                        $e,
                        ['map_results' => $mapResults, 'failures' => $failures]
                    );
                }
            }

            $index++;
        }

        // Clean up map variables
        $context->forget('map_item');
        $context->forget('map_index');
        $context->forget('map_key');

        // Reduce phase
        try {
            $reduced = $this->initialValue;
            foreach ($mapResults as $key => $value) {
                $reduced = ($this->reducer)($reduced, $value, $key);
            }
        } catch (Throwable $e) {
            return StepResult::failed(
                'Reduce phase failed: '.$e->getMessage(),
                $e,
                ['map_results' => $mapResults]
            );
        }

        return StepResult::success($reduced, [
            'mapped_count' => count($mapResults),
            'failures' => $failures,
        ]);
    }

    /**
     * Resolve the collection from context.
     *
     * @return iterable<mixed>
     */
    protected function resolveCollection(WorkflowContext $context): iterable
    {
        if (is_string($this->collection)) {
            return $context->get($this->collection, []);
        }

        return ($this->collection)($context);
    }

    /**
     * Get the pattern name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the pattern name.
     */
    public function as(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getOutputKey(): ?string
    {
        return null;
    }

    public function isRetryable(): bool
    {
        return true;
    }

    public function getMaxRetries(): int
    {
        return 3;
    }

    public function getTimeout(): ?int
    {
        return null;
    }

    public function requiresHumanApproval(): bool
    {
        return false;
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return [];
    }
}
