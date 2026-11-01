<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\User;
use App\Support\Unsubscribe;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * A post an admin chose to email to every member ("Send as email" in the editor).
 * The email carries the whole post, like Skool.
 */
class PostBroadcast extends CommunityActivity
{
    public function __construct(public Post $post) {}

    public function actor(): ?User
    {
        return $this->post->author;
    }

    public function emailType(): ?string
    {
        return 'announcements';
    }

    public function message(): string
    {
        return "{$this->post->author->name} posted in {$this->post->community->name}: \"{$this->post->title}\"";
    }

    public function excerpt(): ?string
    {
        return Str::limit($this->post->body, 160);
    }

    public function url(): string
    {
        return route('posts.show', [$this->post->community, $this->post]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->post->title)
            ->greeting($this->post->title)
            ->line("**{$this->post->author->name}** posted in **{$this->post->community->name}**:");

        foreach (preg_split('/\R{2,}/', Str::limit($this->post->body, 5000)) as $paragraph) {
            $mail->line(e($paragraph));
        }

        if ($image = collect($this->post->attachmentList())->firstWhere('gif', false)) {
            $mail->line("![]({$image['url']})");
        }

        return $mail
            ->action('Read and reply', $this->url())
            ->line('[Stop post emails from admins]('.Unsubscribe::url($notifiable, 'announcements').') · [Email settings]('.route('notifications.settings').')');
    }
}
