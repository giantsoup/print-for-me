<?php

namespace App\Notifications;

use App\Models\PrintRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewPrintRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected PrintRequest $printRequest
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name', "Taylor's Print Services");
        $subject = "[{$appName}] New print request";

        return (new MailMessage)
            ->subject($subject)
            ->greeting('New print request received')
            ->line('A new print request has been submitted.')
            ->line('Request ID: '.$this->printRequest->id)
            ->line('User ID: '.$this->printRequest->user_id)
            ->line('Status: '.$this->printRequest->status)
            ->line('Source URL: '.($this->printRequest->source_url ?: '(none)'));
    }
}
