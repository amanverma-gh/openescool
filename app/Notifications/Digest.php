<?php

namespace App\Notifications;

use App\Support\Unsubscribe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class Digest extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array<string, mixed>>  $sections  from DigestBuilder
     */
    public function __construct(public string $frequency, public array $sections) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $unsubscribe = Unsubscribe::url($notifiable, 'digest');
        $period = $this->frequency === 'daily' ? 'today' : 'this week';
        $first = $this->sections[0]['posts'][0]['title'] ?? null;

        return (new MailMessage)
            ->subject($first ? "\"{$first}\" and more {$period}" : 'Your '.config('app.name')." digest for {$period}")
            ->markdown('mail.digest', [
                'name' => strtok($notifiable->name, ' '),
                'period' => $period,
                'sections' => $this->sections,
                'unsubscribe' => $unsubscribe,
                'settings' => route('notifications.settings'),
            ])
            ->withSymfonyMessage(function (Email $message) use ($unsubscribe) {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', "<{$unsubscribe}>");
                $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
    }
}
