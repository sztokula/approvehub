<?php

namespace App\Notifications;

use App\Models\ApprovalStep;
use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Handles ReviewStepActivatedNotification responsibilities for the ApproveHub domain.
 */
class ReviewStepActivatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Document $document,
        private readonly ApprovalStep $step,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Approval step activated')
            ->line("A new approval step has been activated for \"{$this->document->title}\".")
            ->line("Step: {$this->step->step_order} - {$this->step->name}")
            ->action('Open document', route('documents.show', $this->document))
            ->line('Please take action on this step.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'document_id' => $this->document->id,
            'step_id' => $this->step->id,
            'step_order' => $this->step->step_order,
        ];
    }
}
