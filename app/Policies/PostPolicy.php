<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function update(User $user, Post $post): bool
    {
        return $post->user_id === $user->id && $user->can('participate', $post->community);
    }

    public function delete(User $user, Post $post): bool
    {
        return $post->user_id === $user->id || $user->can('moderate', $post->community);
    }

    public function pin(User $user, Post $post): bool
    {
        return $user->can('moderate', $post->community);
    }

    public function comment(User $user, Post $post): bool
    {
        return ! $post->comments_disabled && $user->can('participate', $post->community);
    }
}
