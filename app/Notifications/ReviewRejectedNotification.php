<?php

namespace App\Notifications;

use App\Models\ApprovalStep;
use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Handles ReviewRejectedNotification responsibilities for the ApproveHub domain.
 */
class ReviewRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Document $document,
        private readonly ApprovalStep $step,
        private readonly ?string $note = null,
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
        $message = (new MailMessage)
            ->subject('Document review rejected')
            ->line("Document \"{$this->document->title}\" has been rejected.")
            ->line("Step: {$this->step->step_order} - {$this->step->name}")
            ->action('Open document', route('documents.show', $this->document));

        if ($this->note !== null && $this->note !== '') {
            $message->line("Rejection note: {$this->note}");
        }

        return $message;
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
            'note' => $this->note,
        ];
    }
}
