<?php

namespace App\Notifications;

use App\Mail\PrintRequestCompletedMail;
use App\Models\PrintRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PrintRequestCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected PrintRequest $printRequest
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): Mailable|MailMessage
    {
        return (new PrintRequestCompletedMail($this->printRequest, $notifiable))
            ->to($notifiable->routeNotificationFor('mail', $this));
    }
}
