<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LikeService
{
    /**
     * Toggle a like and award/remove one point for the author in that community.
     *
     * @return bool whether the item is now liked
     */
    public function toggle(User $user, Post|Comment $likeable): bool
    {
        $community = $likeable instanceof Post ? $likeable->community : $likeable->post->community;

        return DB::transaction(function () use ($user, $likeable, $community) {
            $existing = $likeable->likes()->where('user_id', $user->id)->first();
            $delta = $existing ? -1 : 1;

            if ($existing) {
                $existing->delete();
                $likeable->decrement('likes_count');
            } else {
                $likeable->likes()->create(['user_id' => $user->id]);
                $likeable->increment('likes_count');
            }

            $community->members()->where('user_id', $likeable->user_id)->increment('points', $delta);

            return ! $existing;
        });
    }
}
