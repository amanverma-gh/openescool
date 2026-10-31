<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Str;

class NewComment extends CommunityActivity
{
    public function __construct(public Comment $comment, public bool $isReply = false) {}

    public function actor(): ?User
    {
        return $this->comment->author;
    }

    public function message(): string
    {
        $what = $this->isReply ? 'replied to your comment on' : 'commented on your post';

        return "{$this->comment->author->name} {$what} \"{$this->comment->post->title}\"";
    }

    public function excerpt(): ?string
    {
        return Str::limit($this->comment->body, 160);
    }

    public function url(): string
    {
        return route('posts.show', [$this->comment->post->community, $this->comment->post]).'#comment-'.$this->comment->id;
    }
}
