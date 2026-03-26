<?php

namespace App\Notifications;

use App\Support\MailSubject;
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
        return (new MailMessage)
            ->subject(MailSubject::make('Your secure sign-in link expires in 10 minutes'))
            ->markdown('mail.notifications.magic-login', [
                'greeting' => trim((string) data_get($notifiable, 'name', '')) !== ''
                    ? 'Hi '.trim((string) data_get($notifiable, 'name')).','
                    : 'Hello,',
                'loginUrl' => $this->loginUrl,
            ]);
    }
}
