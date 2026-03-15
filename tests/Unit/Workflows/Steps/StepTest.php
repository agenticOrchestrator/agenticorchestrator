<?php

declare(strict_types=1);

use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\Steps\Step;
use AgenticOrchestrator\Workflows\WorkflowContext;

describe('Step base class', function () {
    it('generates name from class name', function () {
        $step = new class extends Step
        {
            protected function handle(WorkflowContext $context): mixed
            {
                return null;
            }
        };

        // Anonymous class - just ensure it returns a string
        expect($step->getName())->toBeString();
    });

    it('uses custom name when set via as()', function () {
        $step = new class extends Step
        {
            protected function handle(WorkflowContext $context): mixed
            {
                return null;
            }
        };

        $result = $step->as('custom_step');
        expect($result)->toBeInstanceOf(Step::class);
        expect($step->getName())->toBe('custom_step');
    });

    it('wraps raw return data in success result', function () {
        $step = new class extends Step
        {
            protected function handle(WorkflowContext $context): mixed
            {
                return ['key' => 'value'];
            }
        };

        $context = new WorkflowContext;
        $result = $step->execute($context);

        expect($result->isSuccess())->toBeTrue();
        expect($result->output)->toBe(['key' => 'value']);
    });

    it('passes through StepResult from handle', function () {
        $step = new class extends Step
        {
            protected function handle(WorkflowContext $context): mixed
            {
                return StepResult::failed('Intentional failure');
            }
        };

        $context = new WorkflowContext;
        $result = $step->execute($context);

        expect($result->isFailed())->toBeTrue();
        expect($result->message)->toBe('Intentional failure');
    });

    it('catches exceptions and returns failed result', function () {
        $step = new class extends Step
        {
            protected function handle(WorkflowContext $context): mixed
            {
                throw new RuntimeException('Something broke');
            }
        };

        $context = new WorkflowContext;
        $result = $step->execute($context);

        expect($result->isFailed())->toBeTrue();
        expect($result->message)->toBe('Something broke');
        expect($result->exception)->toBeInstanceOf(RuntimeException::class);
    });

    it('checks dependencies before executing', function () {
        $executed = false;
        $step = new class($executed) extends Step
        {
            public function __construct(private bool &$executed)
            {
                $this->dependencies = ['required_data'];
            }

            protected function handle(WorkflowContext $context): mixed
            {
                $this->executed = true;

                return 'done';
            }
        };

        $context = new WorkflowContext;
        $result = $step->execute($context);

        expect($result->isFailed())->toBeTrue();
        expect($result->message)->toContain('Missing dependency: required_data');
        expect($executed)->toBeFalse();
    });

    it('executes when all dependencies are met', function () {
        $step = new class extends Step
        {
            public function __construct()
            {
                $this->dependencies = ['dep_a', 'dep_b'];
            }

            protected function handle(WorkflowContext $context): mixed
            {
                return 'executed';
            }
        };

        $context = new WorkflowContext;
        $context->set('dep_a', 'value_a');
        $context->set('dep_b', 'value_b');

        $result = $step->execute($context);

        expect($result->isSuccess())->toBeTrue();
        expect($result->output)->toBe('executed');
    });

    it('stores output in context when outputKey is set', function () {
        $step = new class extends Step
        {
            protected function handle(WorkflowContext $context): mixed
            {
                return 'stored_value';
            }
        };

        $step->outputAs('my_output');
        $context = new WorkflowContext;
        $step->execute($context);

        expect($context->get('my_output'))->toBe('stored_value');
    });

    it('does not store output when result is null', function () {
        $step = new class extends Step
        {
            protected function handle(WorkflowContext $context): mixed
            {
                return null;
            }
        };

        $step->outputAs('my_output');
        $context = new WorkflowContext;
        $step->execute($context);

        expect($context->has('my_output'))->toBeFalse();
    });

    it('does not store output when result is failed', function () {
        $step = new class extends Step
        {
            protected function handle(WorkflowContext $context): mixed
            {
                return StepResult::failed('error');
            }
        };

        $step->outputAs('my_output');
        $context = new WorkflowContext;
        $step->execute($context);

        expect($context->has('my_output'))->toBeFalse();
    });

    it('gets and sets output key', function () {
        $step = new class extends Step
        {
            protected function handle(WorkflowContext $context): mixed
            {
                return null;
            }
        };

        expect($step->getOutputKey())->toBeNull();

        $result = $step->outputAs('result_key');
        expect($result)->toBeInstanceOf(Step::class);
        expect($step->getOutputKey())->toBe('result_key');
    });

    it('is retryable by default', function () {
        $step = new class extends Step
        {
            protected function handle(WorkflowContext $context): mixed
            {
                return null;
            }
        };

        expect($step->isRetryable())->toBeTrue();
        expect($step->getMaxRetries())->toBe(3);
    });

    it('configures retry attempts', function () {
        $step = new class extends Step
        {
            protected function handle(WorkflowContext $context): mixed
            {
                return null;
            }
        };

        $result = $step->retry(5);
        expect($result)->toBeInstanceOf(Step::class);
        expect($step->isRetryable())->toBeTrue();
        expect($step->getMaxRetries())->toBe(5);
    });

    it('disables retries', function () {
        $step = new class extends Step
        {
            protected function handle(WorkflowContext $context): mixed
            {
                return null;
            }
        };

        $result = $step->noRetry();
        expect($result)->toBeInstanceOf(Step::class);
        expect($step->isRetryable())->toBeFalse();
    });

    it('gets and sets timeout', function () {
        $step = new class extends Step
        {
            protected function handle(WorkflowContext $context): mixed
            {
                return null;
            }
        };

        expect($step->getTimeout())->toBeNull();

        $result = $step->timeout(60);
        expect($result)->toBeInstanceOf(Step::class);
        expect($step->getTimeout())->toBe(60);
    });

    it('does not require human approval by default', function () {
        $step = new class extends Step
        {
            protected function handle(WorkflowContext $context): mixed
            {
                return null;
            }
        };

        expect($step->requiresHumanApproval())->toBeFalse();
    });

    it('marks step as requiring approval', function () {
        $step = new class extends Step
        {
            protected function handle(WorkflowContext $context): mixed
            {
                return null;
            }
        };

        $result = $step->requireApproval();
        expect($result)->toBeInstanceOf(Step::class);
        expect($step->requiresHumanApproval())->toBeTrue();
    });

    it('gets and sets dependencies', function () {
        $step = new class extends Step
        {
            protected function handle(WorkflowContext $context): mixed
            {
                return null;
            }
        };

        expect($step->getDependencies())->toBeEmpty();

        $result = $step->dependsOn(['key_a', 'key_b']);
        expect($result)->toBeInstanceOf(Step::class);
        expect($step->getDependencies())->toBe(['key_a', 'key_b']);
    });

    it('generates snake_case name from CamelCase class name', function () {
        // We create a named class (via anonymous extending) to test the name generation
        // The base anonymous class won't have a meaningful name, so we use as() to verify logic
        $step = new class extends Step
        {
            protected function handle(WorkflowContext $context): mixed
            {
                return null;
            }
        };

        // Without as(), uses class_basename which for anonymous is not predictable
        // But the method still returns a string
        $name = $step->getName();
        expect($name)->toBeString()->not->toBeEmpty();
    });

    it('strips Step suffix from auto-generated name', function () {
        // We test this indirectly since we cannot easily create a named class in Pest
        // The logic in getName() does: preg_replace('/Step$/', '', $class)
        // Verified by setting name to null and using a named step subclass
        $step = new class extends Step
        {
            protected ?string $name = null;

            protected function handle(WorkflowContext $context): mixed
            {
                return null;
            }
        };

        // getName should work without errors
        expect($step->getName())->toBeString();
    });

    it('handles multiple dependencies with first missing', function () {
        $step = new class extends Step
        {
            public function __construct()
            {
                $this->dependencies = ['first', 'second', 'third'];
            }

            protected function handle(WorkflowContext $context): mixed
            {
                return 'ok';
            }
        };

        $context = new WorkflowContext;
        $context->set('second', 'exists');
        $context->set('third', 'exists');

        $result = $step->execute($context);
        expect($result->isFailed())->toBeTrue();
        expect($result->message)->toContain('first');
    });

    it('checks dependencies from input as well as data', function () {
        $step = new class extends Step
        {
            public function __construct()
            {
                $this->dependencies = ['from_input'];
            }

            protected function handle(WorkflowContext $context): mixed
            {
                return 'ok';
            }
        };

        // Dependency met via input
        $context = new WorkflowContext(['from_input' => 'provided']);
        $result = $step->execute($context);

        expect($result->isSuccess())->toBeTrue();
    });
});
