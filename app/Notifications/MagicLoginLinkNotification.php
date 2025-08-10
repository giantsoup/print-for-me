<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicLoginLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $loginUrl
    ) {
        //
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name', "Taylor's Print Services");
        $subject = "[{$appName}] Your magic login link (expires in 10 minutes)";

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello!')
            ->line('Here is your one-time magic login link. For security, it expires in 10 minutes and can only be used once.')
            ->action('Log in now', $this->loginUrl)
            ->line('If you did not request this link, you can ignore this email.');
    }
}
