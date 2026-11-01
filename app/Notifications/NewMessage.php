<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\Unsubscribe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Email only: the chat badge already covers in-app.
 */
class NewMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $sender, public string $body) {}

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
            ->subject("{$this->sender->name} sent you a message")
            ->greeting('Hi '.strtok($notifiable->name, ' ').',')
            ->line("{$this->sender->name} wrote:")
            ->line('"'.Str::limit($this->body, 300).'"')
            ->action('Reply', route('chat.show', $this->sender->username))
            ->line('[Stop these emails]('.Unsubscribe::url($notifiable, 'messages').')');
    }
}
