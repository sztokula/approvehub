<?php

namespace App\Console\Commands;

use App\Actions\Audit\RecordAuditLogAction;
use App\Enums\ApprovalStepStatus;
use App\Models\ApprovalStep;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\ReviewReminderNotification;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Handles SendReviewRemindersCommand responsibilities for the ApproveHub domain.
 */
class SendReviewRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'review:send-reminders {--hours=24 : Remind for steps due within N hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder notifications for active approval steps nearing due date.';

    /**
     * Execute the console command.
     */
    public function handle(RecordAuditLogAction $recordAuditLogAction): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $now = now();
        $deadline = now()->addHours($hours);

        $steps = ApprovalStep::query()
            ->with(['workflow.documentVersion.document', 'assignees.user', 'fallbackUser'])
            ->where('status', ApprovalStepStatus::Active)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$now, $deadline])
            ->get();

        $sentCount = 0;

        foreach ($steps as $step) {
            if ($this->wasReminderSentRecently($step->id, $now)) {
                continue;
            }

            $document = $step->workflow->documentVersion->document;
            $recipients = $this->resolveRecipientsForStep($step);

            if ($recipients->isEmpty()) {
                continue;
            }

            $recipients->each(
                fn (User $recipient) => $recipient->notify(new ReviewReminderNotification($document, $step))
            );

            $recordAuditLogAction->execute(
                organizationId: $document->organization_id,
                actor: null,
                action: 'review.reminder_sent',
                targetType: 'approval_step',
                targetId: $step->id,
                metadata: [
                    'document_id' => $document->id,
                    'recipient_ids' => $recipients->pluck('id')->all(),
                    'due_at' => optional($step->due_at)->toDateTimeString(),
                ],
            );

            $sentCount++;
        }

        $this->info("Sent reminders for {$sentCount} step(s).");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveRecipientsForStep(ApprovalStep $step): Collection
    {
        $assigneeUsers = $step->assignees->pluck('user')->filter();

        if ($assigneeUsers->isNotEmpty()) {
            return User::query()->whereKey($assigneeUsers->pluck('id'))->get();
        }

        if ($step->assignee_role !== null) {
            $organizationId = $step->workflow->documentVersion->document->organization_id;

            $roleRecipients = User::query()
                ->whereHas('organizationMemberships', function ($query) use ($organizationId, $step): void {
                    $query->where('organization_id', $organizationId)
                        ->whereHas('role', fn ($role) => $role->where('name', $step->assignee_role));
                })
                ->get();

            if ($roleRecipients->isNotEmpty()) {
                return $roleRecipients;
            }
        }

        if ($step->fallbackUser !== null) {
            return User::query()->whereKey($step->fallbackUser->id)->get();
        }

        return collect();
    }

    private function wasReminderSentRecently(int $stepId, CarbonInterface $now): bool
    {
        return AuditLog::query()
            ->where('action', 'review.reminder_sent')
            ->where('target_type', 'approval_step')
            ->where('target_id', $stepId)
            ->where('occurred_at', '>=', $now->copy()->subHours(12))
            ->exists();
    }
}
