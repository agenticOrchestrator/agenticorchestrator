<?php

declare(strict_types=1);

use AgenticOrchestrator\MultiTenancy\Contracts\TenantInterface;
use AgenticOrchestrator\Workflows\WorkflowContext;

describe('WorkflowContext - Extended Coverage', function () {

    describe('has', function () {
        it('returns true for keys in data', function () {
            $context = new WorkflowContext;
            $context->set('output_key', 'value');

            expect($context->has('output_key'))->toBeTrue();
        });

        it('returns true for keys in input', function () {
            $context = new WorkflowContext(['input_key' => 'value']);

            expect($context->has('input_key'))->toBeTrue();
        });

        it('returns false for non-existent keys', function () {
            $context = new WorkflowContext;

            expect($context->has('missing'))->toBeFalse();
        });
    });

    describe('get priority', function () {
        it('returns data value over input value when both exist', function () {
            $context = new WorkflowContext(['key' => 'from_input']);
            $context->set('key', 'from_data');

            expect($context->get('key'))->toBe('from_data');
        });

        it('returns input value when key only exists in input', function () {
            $context = new WorkflowContext(['key' => 'from_input']);

            expect($context->get('key'))->toBe('from_input');
        });

        it('returns default when key exists in neither', function () {
            $context = new WorkflowContext;

            expect($context->get('missing', 'fallback'))->toBe('fallback');
        });

        it('returns null as default when no default specified', function () {
            $context = new WorkflowContext;

            expect($context->get('missing'))->toBeNull();
        });
    });

    describe('forget', function () {
        it('removes a key from data', function () {
            $context = new WorkflowContext;
            $context->set('key', 'value');

            expect($context->has('key'))->toBeTrue();

            $result = $context->forget('key');

            expect($context->get('key'))->toBeNull()
                ->and($result)->toBeInstanceOf(WorkflowContext::class);
        });

        it('does not affect input data', function () {
            $context = new WorkflowContext(['key' => 'input_value']);
            $context->set('key', 'data_value');

            $context->forget('key');

            // After forgetting from data, the input value is still accessible
            expect($context->get('key'))->toBe('input_value');
        });

        it('does nothing for non-existent key', function () {
            $context = new WorkflowContext;

            $result = $context->forget('nonexistent');

            expect($result)->toBeInstanceOf(WorkflowContext::class);
        });
    });

    describe('getInput', function () {
        it('returns the original input data', function () {
            $input = ['a' => 1, 'b' => 2];
            $context = new WorkflowContext($input);

            expect($context->getInput())->toBe($input);
        });

        it('returns empty array for no input', function () {
            $context = new WorkflowContext;

            expect($context->getInput())->toBe([]);
        });
    });

    describe('getData', function () {
        it('returns merged input and data', function () {
            $context = new WorkflowContext(['a' => 1]);
            $context->set('b', 2);

            $data = $context->getData();

            expect($data)->toHaveKey('a', 1)
                ->and($data)->toHaveKey('b', 2);
        });

        it('data overrides input with same key', function () {
            $context = new WorkflowContext(['key' => 'original']);
            $context->set('key', 'overridden');

            $data = $context->getData();

            expect($data['key'])->toBe('overridden');
        });
    });

    describe('getOutputs', function () {
        it('returns only step output data, not input', function () {
            $context = new WorkflowContext(['input_key' => 'input_val']);
            $context->set('output_key', 'output_val');

            $outputs = $context->getOutputs();

            expect($outputs)->toHaveKey('output_key', 'output_val')
                ->and($outputs)->not->toHaveKey('input_key');
        });
    });

    describe('metadata operations', function () {
        it('sets and gets metadata', function () {
            $context = new WorkflowContext;
            $result = $context->setMeta('workflow_id', 'wf-123');

            expect($context->getMeta('workflow_id'))->toBe('wf-123')
                ->and($result)->toBeInstanceOf(WorkflowContext::class);
        });

        it('returns default for missing metadata key', function () {
            $context = new WorkflowContext;

            expect($context->getMeta('missing', 'default'))->toBe('default');
        });

        it('returns all metadata', function () {
            $meta = ['exec_id' => '123', 'retry' => 0];
            $context = new WorkflowContext([], $meta);

            expect($context->getMetadata())->toBe($meta);
        });
    });

    describe('step completion tracking', function () {
        it('does not duplicate completed step names', function () {
            $context = new WorkflowContext;
            $context->markStepCompleted('step1');
            $context->markStepCompleted('step1');

            expect($context->getCompletedSteps())->toBe(['step1']);
        });

        it('tracks multiple completed steps', function () {
            $context = new WorkflowContext;
            $context->markStepCompleted('step1');
            $context->markStepCompleted('step2');
            $context->markStepCompleted('step3');

            expect($context->getCompletedSteps())->toBe(['step1', 'step2', 'step3']);
        });
    });

    describe('step failure tracking', function () {
        it('checks if a step has failed', function () {
            $context = new WorkflowContext;
            $context->markStepFailed('step1', 'Error occurred');

            expect($context->isStepFailed('step1'))->toBeTrue()
                ->and($context->isStepFailed('step2'))->toBeFalse();
        });

        it('stores failure without exception class', function () {
            $context = new WorkflowContext;
            $context->markStepFailed('step1', 'Timeout');

            $failed = $context->getFailedSteps();

            expect($failed['step1']['message'])->toBe('Timeout')
                ->and($failed['step1']['exception'])->toBeNull();
        });

        it('stores failure with exception class', function () {
            $context = new WorkflowContext;
            $context->markStepFailed('step1', 'Connection refused', 'RuntimeException');

            $failed = $context->getFailedSteps();

            expect($failed['step1']['message'])->toBe('Connection refused')
                ->and($failed['step1']['exception'])->toBe('RuntimeException');
        });
    });

    describe('tenant scope', function () {
        it('sets and gets tenant', function () {
            $tenant = Mockery::mock(TenantInterface::class);
            $context = new WorkflowContext;

            $result = $context->setTenant($tenant);

            expect($context->getTenant())->toBe($tenant)
                ->and($result)->toBeInstanceOf(WorkflowContext::class);
        });

        it('returns null when no tenant set', function () {
            $context = new WorkflowContext;

            expect($context->getTenant())->toBeNull();
        });

        it('clears tenant when set to null', function () {
            $tenant = Mockery::mock(TenantInterface::class);
            $context = new WorkflowContext;
            $context->setTenant($tenant);
            $context->setTenant(null);

            expect($context->getTenant())->toBeNull();
        });
    });

    describe('user scope', function () {
        it('sets and gets user', function () {
            $user = (object) ['id' => 42, 'name' => 'John'];
            $context = new WorkflowContext;

            $result = $context->setUser($user);

            expect($context->getUser())->toBe($user)
                ->and($result)->toBeInstanceOf(WorkflowContext::class);
        });

        it('returns null when no user set', function () {
            $context = new WorkflowContext;

            expect($context->getUser())->toBeNull();
        });

        it('clears user when set to null', function () {
            $user = (object) ['id' => 1];
            $context = new WorkflowContext;
            $context->setUser($user);
            $context->setUser(null);

            expect($context->getUser())->toBeNull();
        });
    });

    describe('getState', function () {
        it('includes all state components', function () {
            $tenant = Mockery::mock(TenantInterface::class);
            $tenant->shouldReceive('getTenantKey')->andReturn(99);

            $user = (object) ['id' => 42];

            $context = new WorkflowContext(['input' => 'val'], ['meta' => 'data']);
            $context->set('output', 'result');
            $context->markStepCompleted('step1');
            $context->markStepFailed('step2', 'Error', 'Exception');
            $context->setTenant($tenant);
            $context->setUser($user);

            $state = $context->getState();

            expect($state['input'])->toBe(['input' => 'val'])
                ->and($state['data'])->toBe(['output' => 'result'])
                ->and($state['metadata'])->toBe(['meta' => 'data'])
                ->and($state['completed_steps'])->toBe(['step1'])
                ->and($state['failed_steps'])->toHaveKey('step2')
                ->and($state['tenant_id'])->toBe(99)
                ->and($state['user_id'])->toBe(42);
        });

        it('returns null for tenant_id and user_id when not set', function () {
            $context = new WorkflowContext;

            $state = $context->getState();

            expect($state['tenant_id'])->toBeNull()
                ->and($state['user_id'])->toBeNull();
        });
    });

    describe('fromState', function () {
        it('restores full state including failed steps', function () {
            $state = [
                'input' => ['key' => 'value'],
                'data' => ['output' => 'result'],
                'metadata' => ['exec_id' => '123'],
                'completed_steps' => ['step1', 'step2'],
                'failed_steps' => ['step3' => ['message' => 'Error', 'exception' => 'RuntimeException']],
            ];

            $context = WorkflowContext::fromState($state);

            expect($context->get('key'))->toBe('value')
                ->and($context->get('output'))->toBe('result')
                ->and($context->getMeta('exec_id'))->toBe('123')
                ->and($context->isStepCompleted('step1'))->toBeTrue()
                ->and($context->isStepCompleted('step2'))->toBeTrue()
                ->and($context->isStepFailed('step3'))->toBeTrue()
                ->and($context->getFailedSteps()['step3']['message'])->toBe('Error');
        });

        it('handles empty state with defaults', function () {
            $context = WorkflowContext::fromState([]);

            expect($context->getInput())->toBe([])
                ->and($context->getOutputs())->toBe([])
                ->and($context->getMetadata())->toBe([])
                ->and($context->getCompletedSteps())->toBe([])
                ->and($context->getFailedSteps())->toBe([]);
        });
    });

    describe('merge', function () {
        it('merges additional data into existing data', function () {
            $context = new WorkflowContext;
            $context->set('a', 1);
            $result = $context->merge(['b' => 2, 'c' => 3]);

            expect($context->get('a'))->toBe(1)
                ->and($context->get('b'))->toBe(2)
                ->and($context->get('c'))->toBe(3)
                ->and($result)->toBeInstanceOf(WorkflowContext::class);
        });

        it('overwrites existing data keys', function () {
            $context = new WorkflowContext;
            $context->set('key', 'old');
            $context->merge(['key' => 'new']);

            expect($context->get('key'))->toBe('new');
        });
    });

    describe('with (immutable clone)', function () {
        it('creates a new instance with additional data', function () {
            $original = new WorkflowContext(['input' => 'val']);
            $original->set('a', 1);

            $clone = $original->with(['b' => 2]);

            expect($original->get('b'))->toBeNull()
                ->and($clone->get('a'))->toBe(1)
                ->and($clone->get('b'))->toBe(2)
                ->and($clone->get('input'))->toBe('val');
        });

        it('does not affect original when modifying clone', function () {
            $original = new WorkflowContext;
            $original->set('key', 'original');

            $clone = $original->with(['extra' => 'data']);
            $clone->set('key', 'modified');

            expect($original->get('key'))->toBe('original')
                ->and($clone->get('key'))->toBe('modified');
        });
    });

    describe('ArrayAccess implementation', function () {
        it('checks existence via offsetExists', function () {
            $context = new WorkflowContext(['key' => 'value']);

            expect(isset($context['key']))->toBeTrue()
                ->and(isset($context['missing']))->toBeFalse();
        });

        it('gets values via offsetGet', function () {
            $context = new WorkflowContext(['key' => 'value']);

            expect($context['key'])->toBe('value')
                ->and($context['missing'])->toBeNull();
        });

        it('sets values via offsetSet', function () {
            $context = new WorkflowContext;
            $context['key'] = 'value';

            expect($context->get('key'))->toBe('value');
        });

        it('unsets values via offsetUnset', function () {
            $context = new WorkflowContext;
            $context->set('key', 'value');
            unset($context['key']);

            expect($context->get('key'))->toBeNull();
        });
    });

    describe('JsonSerializable implementation', function () {
        it('returns getState from jsonSerialize', function () {
            $context = new WorkflowContext(['input' => 'val'], ['meta' => 'data']);
            $context->set('output', 'result');

            $json = $context->jsonSerialize();
            $state = $context->getState();

            expect($json)->toBe($state);
        });

        it('produces valid JSON', function () {
            $context = new WorkflowContext(['key' => 'value']);
            $encoded = json_encode($context);

            expect($encoded)->toBeString();

            $decoded = json_decode($encoded, true);
            expect($decoded)->toHaveKey('input')
                ->and($decoded['input']['key'])->toBe('value');
        });
    });

    describe('toArray (Arrayable)', function () {
        it('returns getData result', function () {
            $context = new WorkflowContext(['a' => 1]);
            $context->set('b', 2);

            expect($context->toArray())->toBe($context->getData());
        });
    });
});
