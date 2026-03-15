<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Workflows\Steps;

use AgenticOrchestrator\Workflows\StepResult;
use AgenticOrchestrator\Workflows\WorkflowContext;
use Closure;

/**
 * Human Approval Step - Pauses workflow for human review and approval.
 *
 * Implements human-in-the-loop patterns for workflows requiring oversight.
 */
class HumanApprovalStep extends Step
{
    /**
     * The approval prompt/question.
     *
     * @var string|Closure(WorkflowContext): string
     */
    protected string|Closure $prompt;

    /**
     * Data to present for review.
     *
     * @var array<string, mixed>|Closure(WorkflowContext): array<string, mixed>
     */
    protected array|Closure $reviewData = [];

    /**
     * Allowed actions.
     *
     * @var array<string>
     */
    protected array $actions = ['approve', 'reject'];

    /**
     * Timeout in seconds for waiting.
     */
    protected int $approvalTimeout = 86400; // 24 hours

    /**
     * Action to take on timeout.
     */
    protected string $timeoutAction = 'reject';

    /**
     * Notification channels.
     *
     * @var array<string>
     */
    protected array $notifyChannels = ['mail'];

    /**
     * Users to notify.
     *
     * @var array<int|string>|Closure(WorkflowContext): array<int|string>
     */
    protected array|Closure $notifyUsers = [];

    /**
     * Create a new human approval step.
     */
    public function __construct(string|Closure $prompt)
    {
        $this->prompt = $prompt;
        $this->requiresApproval = true;
    }

    /**
     * Create a human approval step.
     */
    public static function make(string|Closure $prompt): static
    {
        return new static($prompt);
    }

    /**
     * Set the data to review.
     *
     * @param  array<string, mixed>|Closure  $data
     */
    public function withReviewData(array|Closure $data): static
    {
        $this->reviewData = $data;

        return $this;
    }

    /**
     * Set allowed actions.
     *
     * @param  array<string>  $actions
     */
    public function allowActions(array $actions): static
    {
        $this->actions = $actions;

        return $this;
    }

    /**
     * Set the approval timeout.
     */
    public function timeoutAfter(int $seconds): static
    {
        $this->approvalTimeout = $seconds;

        return $this;
    }

    /**
     * Set the timeout action.
     */
    public function onTimeout(string $action): static
    {
        $this->timeoutAction = $action;

        return $this;
    }

    /**
     * Set notification channels.
     *
     * @param  array<string>  $channels
     */
    public function notifyVia(array $channels): static
    {
        $this->notifyChannels = $channels;

        return $this;
    }

    /**
     * Set users to notify.
     *
     * @param  array<int|string>|Closure  $users
     */
    public function notifyUsers(array|Closure $users): static
    {
        $this->notifyUsers = $users;

        return $this;
    }

    /**
     * Execute the approval step.
     */
    protected function handle(WorkflowContext $context): StepResult
    {
        // Check if we're resuming with an approval decision
        $approvalKey = "approval_{$this->getName()}";

        if ($context->has($approvalKey)) {
            $decision = $context->get($approvalKey);

            if ($decision['action'] === 'approve') {
                return StepResult::success([
                    'approved' => true,
                    'approved_by' => $decision['user_id'] ?? null,
                    'approved_at' => $decision['timestamp'] ?? now()->toISOString(),
                    'notes' => $decision['notes'] ?? null,
                ]);
            }

            return StepResult::failed(
                $decision['reason'] ?? 'Approval rejected',
                metadata: [
                    'rejected_by' => $decision['user_id'] ?? null,
                    'rejected_at' => $decision['timestamp'] ?? now()->toISOString(),
                ]
            );
        }

        // Create the approval request
        $prompt = $this->buildPrompt($context);
        $reviewData = $this->buildReviewData($context);
        $usersToNotify = $this->resolveNotifyUsers($context);

        return StepResult::waiting(
            message: $prompt,
            approvalData: [
                'prompt' => $prompt,
                'review_data' => $reviewData,
                'actions' => $this->actions,
                'timeout' => $this->approvalTimeout,
                'timeout_action' => $this->timeoutAction,
                'notify_channels' => $this->notifyChannels,
                'notify_users' => $usersToNotify,
                'expires_at' => now()->addSeconds($this->approvalTimeout)->toISOString(),
                'approval_key' => $approvalKey,
            ],
            metadata: [
                'step_name' => $this->getName(),
                'workflow_context' => $context->getState(),
            ]
        );
    }

    /**
     * Build the approval prompt.
     */
    protected function buildPrompt(WorkflowContext $context): string
    {
        if ($this->prompt instanceof Closure) {
            return ($this->prompt)($context);
        }

        // Variable substitution
        $prompt = $this->prompt;

        foreach ($context->getData() as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $prompt = str_replace("{{$key}}", (string) $value, $prompt);
            }
        }

        return $prompt;
    }

    /**
     * Build the review data.
     *
     * @return array<string, mixed>
     */
    protected function buildReviewData(WorkflowContext $context): array
    {
        if ($this->reviewData instanceof Closure) {
            return ($this->reviewData)($context);
        }

        return $this->reviewData;
    }

    /**
     * Resolve users to notify.
     *
     * @return array<int|string>
     */
    protected function resolveNotifyUsers(WorkflowContext $context): array
    {
        if ($this->notifyUsers instanceof Closure) {
            return ($this->notifyUsers)($context);
        }

        return $this->notifyUsers;
    }
}
