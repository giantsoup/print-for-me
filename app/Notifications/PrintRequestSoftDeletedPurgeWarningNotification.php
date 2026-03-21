<?php

namespace App\Notifications;

use App\Models\PrintRequest;
use App\Support\MailSubject;
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
            ->greeting('Heads up: pending deletion')
            ->line('Your print request was deleted and is scheduled for permanent removal in 7 days.')
            ->line('Request ID: '.$this->printRequest->id)
            ->line('If this was a mistake, you can recreate the request or contact support.');
    }
}
