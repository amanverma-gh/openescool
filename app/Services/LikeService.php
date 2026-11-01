<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\CommunityMember;
use App\Models\PointEvent;
use App\Models\Post;
use App\Models\User;
use App\Notifications\LevelUp;
use Illuminate\Support\Facades\Cache;
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

        [$liked, $author] = DB::transaction(function () use ($user, $likeable, $community) {
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
            PointEvent::create(['community_id' => $community->id, 'user_id' => $likeable->user_id, 'points' => $delta]);

            return [! $existing, $community->members()->where('user_id', $likeable->user_id)->first()];
        });

        // Crossing a threshold upward: celebrate once (unliking and re-liking stays quiet).
        if ($liked && $author && $author->user_id !== $user->id
            && $author->level > CommunityMember::levelForPoints($author->points - 1)
            && Cache::add("level-up:{$author->id}:{$author->level}", true, now()->addYear())) {
            $author->user->notify(new LevelUp($community, $author->level));
        }

        return $liked;
    }
}
