<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Str;

class Mentioned extends CommunityActivity
{
    public function __construct(public User $by, public Post $post, public ?Comment $comment = null) {}

    public function actor(): ?User
    {
        return $this->by;
    }

    public function message(): string
    {
        return "{$this->by->name} mentioned you in \"{$this->post->title}\"";
    }

    public function excerpt(): ?string
    {
        return Str::limit(($this->comment ?? $this->post)->body, 160);
    }

    public function url(): string
    {
        $url = route('posts.show', [$this->post->community, $this->post]);

        return $this->comment ? $url.'#comment-'.$this->comment->id : $url;
    }
}
