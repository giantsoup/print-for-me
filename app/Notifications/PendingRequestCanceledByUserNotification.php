<?php

namespace App\Notifications;

use App\Models\PrintRequest;
use App\Support\MailSubject;
use App\Support\PrintRequestMailData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendingRequestCanceledByUserNotification extends Notification implements ShouldQueue
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
        $subject = MailSubject::make('Pending print request canceled');

        return (new MailMessage)
            ->subject($subject)
            ->markdown('mail.notifications.print-request-update', [
                'headline' => 'A pending print request was canceled',
                'greeting' => PrintRequestMailData::greetingFor($notifiable),
                'intro' => 'The requester canceled this print job before production began.',
                'details' => PrintRequestMailData::adminDetails($this->printRequest),
                'instructions' => PrintRequestMailData::instructionsExcerpt($this->printRequest->instructions),
                'nextSteps' => 'No further action is required unless your team needs to follow up with the requester.',
                'actionLabel' => 'Review active queue',
                'actionUrl' => PrintRequestMailData::queueUrl(),
                'closing' => 'This notice is for operational visibility in the print queue.',
            ]);
    }
}
