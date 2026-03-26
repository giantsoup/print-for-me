<?php

namespace App\Notifications;

use App\Models\PrintRequest;
use App\Support\MailSubject;
use App\Support\PrintRequestMailData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PrintRequestAcceptedNotification extends Notification implements ShouldQueue
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
            ->subject(MailSubject::make('Your print request has been accepted'))
            ->markdown('mail.notifications.print-request-update', [
                'headline' => 'Your print request has been accepted',
                'greeting' => PrintRequestMailData::greetingFor($notifiable),
                'intro' => 'Your request has been reviewed and approved for production. You can open the request at any time to keep track of the latest status.',
                'details' => PrintRequestMailData::requesterDetails($this->printRequest),
                'instructions' => PrintRequestMailData::instructionsExcerpt($this->printRequest->instructions),
                'nextSteps' => 'We will send another update as the request moves through production.',
                'actionLabel' => 'View request details',
                'actionUrl' => PrintRequestMailData::requestUrl($this->printRequest),
                'closing' => 'Thank you for using our print queue.',
            ]);
    }
}
