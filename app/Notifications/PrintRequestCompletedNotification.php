<?php

namespace App\Notifications;

use App\Models\PrintRequest;
use App\Support\MailSubject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PrintRequestCompletedNotification extends Notification implements ShouldQueue
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
            ->subject(MailSubject::make('Your print request is complete'))
            ->greeting('Your request is complete')
            ->line('Your print request has been completed.')
            ->line('Request ID: '.$this->printRequest->id);
    }
}
