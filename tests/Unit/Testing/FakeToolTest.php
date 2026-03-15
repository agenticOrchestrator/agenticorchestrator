<?php

declare(strict_types=1);

use AgenticOrchestrator\Contracts\ToolInterface;
use AgenticOrchestrator\Testing\FakeTool;
use AgenticOrchestrator\Tools\ToolResult;
use PHPUnit\Framework\AssertionFailedError;

covers(FakeTool::class);

describe('FakeTool', function () {

    it('creates via constructor with default name', function () {
        $tool = new FakeTool;

        expect($tool->getName())->toBe('fake_tool')
            ->and($tool)->toBeInstanceOf(ToolInterface::class);
    });

    it('creates via constructor with custom name', function () {
        $tool = new FakeTool('custom_tool');

        expect($tool->getName())->toBe('custom_tool');
    });

    it('creates via static make with default name', function () {
        $tool = FakeTool::make();

        expect($tool->getName())->toBe('fake_tool');
    });

    it('creates via static make with custom name', function () {
        $tool = FakeTool::make('my_tool');

        expect($tool->getName())->toBe('my_tool');
    });

    it('sets name via named()', function () {
        $tool = FakeTool::make()->named('renamed');

        expect($tool->getName())->toBe('renamed');
    });

    it('returns default description', function () {
        $tool = FakeTool::make();

        expect($tool->getDescription())->toBe('Fake tool for testing');
    });

    it('sets description via describedAs()', function () {
        $tool = FakeTool::make()->describedAs('A custom description');

        expect($tool->getDescription())->toBe('A custom description');
    });

    it('returns default schema', function () {
        $tool = FakeTool::make('test_tool');

        $schema = $tool->toSchema();

        expect($schema)->toBe([
            'type' => 'function',
            'function' => [
                'name' => 'test_tool',
                'description' => 'Fake tool for testing',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
        ]);
    });

    it('reports isParallel as true', function () {
        expect(FakeTool::make()->isParallel())->toBeTrue();
    });

    it('validates any arguments as true', function () {
        expect(FakeTool::make()->validate(['any' => 'args']))->toBeTrue();
    });

    it('returns empty parameters', function () {
        expect(FakeTool::make()->getParameters())->toBe([]);
    });

    it('is not cacheable', function () {
        expect(FakeTool::make()->isCacheable())->toBeFalse();
    });

    it('has zero cache TTL', function () {
        expect(FakeTool::make()->getCacheTtl())->toBe(0);
    });

    describe('execute', function () {
        it('returns default success result when no results configured', function () {
            $tool = FakeTool::make('my_tool');

            $result = $tool->execute(['input' => 'test']);

            expect($result)->toBeInstanceOf(ToolResult::class)
                ->and($result->isSuccess())->toBeTrue()
                ->and($result->result)->toBe(['fake' => 'result'])
                ->and($result->name)->toBe('my_tool');
        });

        it('returns configured array result', function () {
            $tool = FakeTool::make()->returns(['status' => 'ok']);

            $result = $tool->execute([]);

            expect($result->isSuccess())->toBeTrue()
                ->and($result->result)->toBe(['status' => 'ok']);
        });

        it('returns configured ToolResult directly', function () {
            $expected = ToolResult::success(
                toolCallId: 'custom-id',
                name: 'tool',
                arguments: [],
                result: ['custom' => true],
            );
            $tool = FakeTool::make()->returns($expected);

            $result = $tool->execute([]);

            expect($result)->toBe($expected);
        });

        it('returns result from closure with array value', function () {
            $tool = FakeTool::make()->returns(
                fn (array $args) => ['doubled' => $args['num'] * 2]
            );

            $result = $tool->execute(['num' => 5]);

            expect($result->isSuccess())->toBeTrue()
                ->and($result->result)->toBe(['doubled' => 10]);
        });

        it('returns result from closure returning ToolResult', function () {
            $tool = FakeTool::make()->returns(
                fn (array $args) => ToolResult::success(
                    toolCallId: 'from-closure',
                    name: 'tool',
                    arguments: $args,
                    result: ['from' => 'closure'],
                )
            );

            $result = $tool->execute(['x' => 1]);

            expect($result->toolCallId)->toBe('from-closure')
                ->and($result->result)->toBe(['from' => 'closure']);
        });

        it('returns sequence of array results', function () {
            $tool = FakeTool::make()->returns([
                ['step' => 1],
                ['step' => 2],
                ['step' => 3],
            ]);

            expect($tool->execute([])->result)->toBe(['step' => 1])
                ->and($tool->execute([])->result)->toBe(['step' => 2])
                ->and($tool->execute([])->result)->toBe(['step' => 3]);
        });

        it('returns sequence of ToolResult objects', function () {
            $r1 = ToolResult::success(toolCallId: 'r1', name: 't', arguments: [], result: ['a' => 1]);
            $r2 = ToolResult::success(toolCallId: 'r2', name: 't', arguments: [], result: ['a' => 2]);

            $tool = FakeTool::make()->returns([$r1, $r2]);

            expect($tool->execute([]))->toBe($r1)
                ->and($tool->execute([]))->toBe($r2);
        });

        it('returns sequence of closures', function () {
            $tool = FakeTool::make()->returns([
                fn () => ['call' => 1],
                fn () => ['call' => 2],
            ]);

            expect($tool->execute([])->result)->toBe(['call' => 1])
                ->and($tool->execute([])->result)->toBe(['call' => 2]);
        });

        it('repeats last result when sequence exhausted', function () {
            $tool = FakeTool::make()->returns([
                ['first' => true],
                ['last' => true],
            ]);

            $tool->execute([]);
            $tool->execute([]);
            $result = $tool->execute([]);

            expect($result->result)->toBe(['last' => true]);
        });

        it('returns failure when shouldFail is set', function () {
            $tool = FakeTool::make('broken')
                ->shouldFail('Broken!');

            $result = $tool->execute(['arg' => 'val']);

            expect($result->isSuccess())->toBeFalse()
                ->and($result->error)->toBe('Broken!')
                ->and($result->name)->toBe('broken');
        });

        it('returns failure with default message', function () {
            $tool = FakeTool::make()->shouldFail();

            $result = $tool->execute([]);

            expect($result->error)->toBe('Tool execution failed');
        });
    });

    describe('call tracking', function () {
        it('tracks all calls', function () {
            $tool = FakeTool::make()->returns([]);

            $tool->execute(['a' => 1]);
            $tool->execute(['b' => 2]);
            $tool->execute(['c' => 3]);

            $calls = $tool->getCalls();
            expect($calls)->toHaveCount(3)
                ->and($calls[0]['arguments'])->toBe(['a' => 1])
                ->and($calls[2]['arguments'])->toBe(['c' => 3]);
        });

        it('returns null for getLastCallArguments when no calls', function () {
            $tool = FakeTool::make();

            expect($tool->getLastCallArguments())->toBeNull();
        });

        it('returns last call arguments', function () {
            $tool = FakeTool::make()->returns([]);

            $tool->execute(['first' => true]);
            $tool->execute(['last' => true]);

            expect($tool->getLastCallArguments())->toBe(['last' => true]);
        });
    });

    describe('assertions', function () {
        it('assertCalled passes when called', function () {
            $tool = FakeTool::make()->returns([]);
            $tool->execute([]);

            $tool->assertCalled();
            expect(true)->toBeTrue();
        });

        it('assertCalled throws when not called', function () {
            $tool = FakeTool::make('unused_tool');

            expect(fn () => $tool->assertCalled())->toThrow(
                AssertionFailedError::class,
                'Expected tool "unused_tool" to be called, but it was not.'
            );
        });

        it('assertNotCalled passes when not called', function () {
            $tool = FakeTool::make();

            $tool->assertNotCalled();
            expect(true)->toBeTrue();
        });

        it('assertNotCalled throws when called', function () {
            $tool = FakeTool::make('used_tool')->returns([]);
            $tool->execute([]);

            expect(fn () => $tool->assertNotCalled())->toThrow(
                AssertionFailedError::class,
                'Expected tool "used_tool" not to be called, but it was called 1 time(s).'
            );
        });

        it('assertCalledTimes passes with correct count', function () {
            $tool = FakeTool::make()->returns([]);
            $tool->execute([]);
            $tool->execute([]);

            $tool->assertCalledTimes(2);
            expect(true)->toBeTrue();
        });

        it('assertCalledTimes throws with wrong count', function () {
            $tool = FakeTool::make('my_tool')->returns([]);
            $tool->execute([]);

            expect(fn () => $tool->assertCalledTimes(3))->toThrow(
                AssertionFailedError::class,
                'Expected tool "my_tool" to be called 3 time(s), but it was called 1 time(s).'
            );
        });

        it('assertCalledWith passes for matching arguments', function () {
            $tool = FakeTool::make()->returns([]);
            $tool->execute(['x' => 1, 'y' => 2]);

            $tool->assertCalledWith(['x' => 1, 'y' => 2]);
            expect(true)->toBeTrue();
        });

        it('assertCalledWith throws for no matching arguments', function () {
            $tool = FakeTool::make('t')->returns([]);
            $tool->execute(['a' => 1]);

            expect(fn () => $tool->assertCalledWith(['b' => 2]))->toThrow(
                AssertionFailedError::class,
            );
        });

        it('assertCalledWithKey passes when key exists', function () {
            $tool = FakeTool::make()->returns([]);
            $tool->execute(['search' => 'query', 'limit' => 10]);

            $tool->assertCalledWithKey('search');
            $tool->assertCalledWithKey('limit');
            expect(true)->toBeTrue();
        });

        it('assertCalledWithKey throws when key missing', function () {
            $tool = FakeTool::make('t')->returns([]);
            $tool->execute(['a' => 1]);

            expect(fn () => $tool->assertCalledWithKey('missing'))->toThrow(
                AssertionFailedError::class,
                'Expected tool "t" to be called with argument key "missing", but it was not.'
            );
        });
    });

    it('resets calls and result index', function () {
        $tool = FakeTool::make()->returns([['a' => 1], ['b' => 2]]);

        $tool->execute([]);
        $tool->execute([]);
        $result = $tool->reset();

        expect($result)->toBe($tool) // fluent
            ->and($tool->getCalls())->toBeEmpty()
            ->and($tool->execute([])->result)->toBe(['a' => 1]); // index reset
    });
});
