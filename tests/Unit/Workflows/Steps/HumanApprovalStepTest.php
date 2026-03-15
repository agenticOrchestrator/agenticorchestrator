<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Tests\Unit\Workflows\Steps;

use AgenticOrchestrator\Workflows\Steps\HumanApprovalStep;
use AgenticOrchestrator\Workflows\WorkflowContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HumanApprovalStep::class)]
class HumanApprovalStepTest extends TestCase
{
    #[Test]
    public function it_returns_waiting_result_when_no_approval_exists(): void
    {
        $step = HumanApprovalStep::make('Please approve this action')
            ->as('approval_step');

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isWaiting());
        $this->assertSame('Please approve this action', $result->message);
    }

    #[Test]
    public function it_includes_approval_data_in_waiting_result(): void
    {
        $step = HumanApprovalStep::make('Approve deployment')
            ->as('deploy_approval')
            ->allowActions(['approve', 'reject', 'defer'])
            ->timeoutAfter(3600)
            ->onTimeout('reject')
            ->notifyVia(['slack', 'email'])
            ->notifyUsers([1, 2, 3]);

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertTrue($result->isWaiting());

        $approvalData = $result->output;
        $this->assertSame('Approve deployment', $approvalData['prompt']);
        $this->assertSame(['approve', 'reject', 'defer'], $approvalData['actions']);
        $this->assertSame(3600, $approvalData['timeout']);
        $this->assertSame('reject', $approvalData['timeout_action']);
        $this->assertSame(['slack', 'email'], $approvalData['notify_channels']);
        $this->assertSame([1, 2, 3], $approvalData['notify_users']);
        $this->assertSame('approval_deploy_approval', $approvalData['approval_key']);
    }

    #[Test]
    public function it_returns_success_when_approved(): void
    {
        $step = HumanApprovalStep::make('Approve this')
            ->as('test_approval');

        $context = new WorkflowContext;
        $context->set('approval_test_approval', [
            'action' => 'approve',
            'user_id' => 42,
            'timestamp' => '2026-01-01T00:00:00Z',
            'notes' => 'Looks good',
        ]);

        $result = $step->execute($context);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->output['approved']);
        $this->assertSame(42, $result->output['approved_by']);
        $this->assertSame('2026-01-01T00:00:00Z', $result->output['approved_at']);
        $this->assertSame('Looks good', $result->output['notes']);
    }

    #[Test]
    public function it_returns_failed_when_rejected(): void
    {
        $step = HumanApprovalStep::make('Approve this')
            ->as('test_approval');

        $context = new WorkflowContext;
        $context->set('approval_test_approval', [
            'action' => 'reject',
            'user_id' => 99,
            'reason' => 'Not ready yet',
            'timestamp' => '2026-01-01T00:00:00Z',
        ]);

        $result = $step->execute($context);

        $this->assertTrue($result->isFailed());
        $this->assertSame('Not ready yet', $result->message);
        $this->assertSame(99, $result->getMeta('rejected_by'));
        $this->assertSame('2026-01-01T00:00:00Z', $result->getMeta('rejected_at'));
    }

    #[Test]
    public function it_uses_default_rejection_message_when_no_reason_provided(): void
    {
        $step = HumanApprovalStep::make('Approve')
            ->as('test_approval');

        $context = new WorkflowContext;
        $context->set('approval_test_approval', [
            'action' => 'reject',
        ]);

        $result = $step->execute($context);

        $this->assertTrue($result->isFailed());
        $this->assertSame('Approval rejected', $result->message);
    }

    #[Test]
    public function it_substitutes_context_variables_in_prompt(): void
    {
        $step = HumanApprovalStep::make('Deploy {app_name} to {environment}?')
            ->as('deploy');

        $context = new WorkflowContext;
        $context->set('app_name', 'MyApp');
        $context->set('environment', 'production');

        $result = $step->execute($context);

        $this->assertTrue($result->isWaiting());
        $this->assertSame('Deploy MyApp to production?', $result->message);
    }

    #[Test]
    public function it_supports_closure_prompt(): void
    {
        $step = HumanApprovalStep::make(function (WorkflowContext $ctx) {
            return 'Approve order #'.$ctx->get('order_id');
        })->as('order_approval');

        $context = new WorkflowContext;
        $context->set('order_id', '12345');

        $result = $step->execute($context);

        $this->assertTrue($result->isWaiting());
        $this->assertSame('Approve order #12345', $result->message);
    }

    #[Test]
    public function it_supports_closure_review_data(): void
    {
        $step = HumanApprovalStep::make('Review')
            ->as('review')
            ->withReviewData(fn (WorkflowContext $ctx) => [
                'amount' => $ctx->get('amount'),
                'currency' => 'USD',
            ]);

        $context = new WorkflowContext;
        $context->set('amount', 500);

        $result = $step->execute($context);

        $approvalData = $result->output;
        $this->assertSame(['amount' => 500, 'currency' => 'USD'], $approvalData['review_data']);
    }

    #[Test]
    public function it_supports_array_review_data(): void
    {
        $step = HumanApprovalStep::make('Review')
            ->as('review')
            ->withReviewData(['key' => 'value']);

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertSame(['key' => 'value'], $result->output['review_data']);
    }

    #[Test]
    public function it_supports_closure_notify_users(): void
    {
        $step = HumanApprovalStep::make('Approve')
            ->as('test')
            ->notifyUsers(fn (WorkflowContext $ctx) => $ctx->get('managers', []));

        $context = new WorkflowContext;
        $context->set('managers', [10, 20]);

        $result = $step->execute($context);

        $this->assertSame([10, 20], $result->output['notify_users']);
    }

    #[Test]
    public function it_requires_human_approval(): void
    {
        $step = HumanApprovalStep::make('Approve');

        $this->assertTrue($step->requiresHumanApproval());
    }

    #[Test]
    public function it_has_correct_auto_generated_name(): void
    {
        $step = HumanApprovalStep::make('Test');

        $this->assertSame('human_approval', $step->getName());
    }

    #[Test]
    public function it_has_default_timeout_of_24_hours(): void
    {
        $step = HumanApprovalStep::make('Approve')
            ->as('test');

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertSame(86400, $result->output['timeout']);
    }

    #[Test]
    public function it_has_default_actions(): void
    {
        $step = HumanApprovalStep::make('Approve')
            ->as('test');

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertSame(['approve', 'reject'], $result->output['actions']);
    }

    #[Test]
    public function it_has_default_notify_channels(): void
    {
        $step = HumanApprovalStep::make('Approve')
            ->as('test');

        $context = new WorkflowContext;
        $result = $step->execute($context);

        $this->assertSame(['mail'], $result->output['notify_channels']);
    }

    #[Test]
    public function it_includes_step_metadata_in_waiting_result(): void
    {
        $step = HumanApprovalStep::make('Approve')
            ->as('my_step');

        $context = new WorkflowContext(['input_key' => 'input_val']);
        $result = $step->execute($context);

        $this->assertSame('my_step', $result->getMeta('step_name'));
        $this->assertIsArray($result->getMeta('workflow_context'));
    }
}
