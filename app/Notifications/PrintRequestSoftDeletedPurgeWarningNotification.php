<?php

namespace App\Notifications;

use App\Models\PrintRequest;
use App\Support\MailSubject;
use App\Support\PrintRequestMailData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PrintRequestSoftDeletedPurgeWarningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected PrintRequest $printRequest
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(MailSubject::make('Your deleted print request will be permanently removed in 7 days'))
            ->markdown('mail.notifications.print-request-update', [
                'headline' => 'Your deleted print request will be removed in 7 days',
                'greeting' => PrintRequestMailData::greetingFor($notifiable),
                'intro' => 'A deleted print request is scheduled for permanent removal in seven days. If you still need this print, submit a new request before the removal date below.',
                'details' => PrintRequestMailData::purgeDetails($this->printRequest),
                'instructions' => PrintRequestMailData::instructionsExcerpt($this->printRequest->instructions),
                'nextSteps' => 'Deleted requests are automatically purged after 90 days and cannot be recovered once that deadline passes.',
                'actionLabel' => 'Start a new request',
                'actionUrl' => PrintRequestMailData::createUrl(),
                'closing' => 'If you no longer need the request, no action is required.',
            ]);
    }
}
