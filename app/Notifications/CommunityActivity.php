<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Shared base for in-app + email notifications about community activity.
 * Subclasses describe the event; delivery and payload shape live here.
 */
abstract class CommunityActivity extends Notification implements ShouldQueue
{
    use Queueable;

    abstract public function actor(): ?User;

    abstract public function message(): string;

    abstract public function url(): string;

    public function excerpt(): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->message())
            ->greeting('Hi '.strtok($notifiable->name, ' ').',')
            ->line($this->message());

        if ($excerpt = $this->excerpt()) {
            $mail->line('"'.$excerpt.'"');
        }

        return $mail->action('View on '.config('app.name'), $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'actor' => $this->actor()?->toPublicArray(),
            'message' => $this->message(),
            'excerpt' => $this->excerpt(),
            'url' => $this->url(),
        ];
    }
}
