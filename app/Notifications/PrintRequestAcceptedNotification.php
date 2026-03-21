<?php

namespace App\Notifications;

use App\Models\PrintRequest;
use App\Support\MailSubject;
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
            ->subject(MailSubject::make('Your print request was accepted'))
            ->greeting('Your request was accepted')
            ->line('Your print request has been accepted and will move to printing soon.')
            ->line('Request ID: '.$this->printRequest->id);
    }
}
