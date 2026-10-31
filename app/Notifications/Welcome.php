<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class Welcome extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to '.config('app.name'))
            ->greeting('Hi '.strtok($notifiable->name, ' ').',')
            ->line('Your account is ready. Jump into a community, introduce yourself and start learning together.')
            ->action('Explore communities', route('home'))
            ->line('Tip: add a photo and a short bio so other members know who you are.');
    }
}
