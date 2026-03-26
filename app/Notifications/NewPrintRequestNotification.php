<?php

namespace App\Notifications;

use App\Models\PrintRequest;
use App\Support\MailSubject;
use App\Support\PrintRequestMailData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewPrintRequestNotification extends Notification implements ShouldQueue
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
        $subject = MailSubject::make('New print request ready for review');

        return (new MailMessage)
            ->subject($subject)
            ->markdown('mail.notifications.print-request-update', [
                'headline' => 'A new print request is ready for review',
                'greeting' => PrintRequestMailData::greetingFor($notifiable),
                'intro' => 'A requester submitted a new print job and it is waiting in the queue for review.',
                'details' => PrintRequestMailData::adminDetails($this->printRequest),
                'instructions' => PrintRequestMailData::instructionsExcerpt($this->printRequest->instructions),
                'nextSteps' => 'Open the request to review the files, source reference, and notes before moving it into production.',
                'actionLabel' => 'Review request',
                'actionUrl' => PrintRequestMailData::requestUrl($this->printRequest),
                'closing' => 'This message was sent because the print queue received a new submission.',
            ]);
    }
}
