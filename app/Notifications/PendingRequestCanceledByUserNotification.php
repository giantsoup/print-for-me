<?php

namespace App\Notifications;

use App\Models\PrintRequest;
use App\Support\MailSubject;
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
        $subject = MailSubject::make('Pending request canceled by user');

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Pending request canceled by user')
            ->line('A user has canceled their pending print request.')
            ->line('Request ID: '.$this->printRequest->id)
            ->line('User ID: '.$this->printRequest->user_id)
            ->line('Source URL: '.($this->printRequest->source_url ?: '(none)'));
    }
}
