<?php

namespace App\Notifications;

use App\Models\PrintRequest;
use App\Support\MailSubject;
use App\Support\PrintRequestMailData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PrintRequestRevertedToPendingNotification extends Notification implements ShouldQueue
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
            ->subject(MailSubject::make('Your print request needs another review'))
            ->markdown('mail.notifications.print-request-update', [
                'headline' => 'Your print request needs another review',
                'greeting' => PrintRequestMailData::greetingFor($notifiable),
                'intro' => 'Your request was moved back to pending so it can be reviewed again before production continues.',
                'details' => PrintRequestMailData::requesterDetails($this->printRequest),
                'instructions' => PrintRequestMailData::instructionsExcerpt($this->printRequest->instructions),
                'nextSteps' => 'Once the team finishes its follow-up review, you will receive another status update.',
                'actionLabel' => 'Review request',
                'actionUrl' => PrintRequestMailData::requestUrl($this->printRequest),
                'closing' => 'Open the request if you want to revisit the source link, files, or notes that were submitted.',
            ]);
    }
}
