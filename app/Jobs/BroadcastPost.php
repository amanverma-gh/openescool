<?php

namespace App\Jobs;

use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Sends an emailed post to every active member, in chunks, in the background.
 * Members who turned off "announcements" still get the in-app notification.
 */
class BroadcastPost implements ShouldQueue
{
    use Queueable;

    public function __construct(public Post $post) {}

    public function handle(): void
    {
        $this->post->loadMissing(['author', 'community']);

        User::query()
            ->whereNull('banned_at')
            ->whereKeyNot($this->post->user_id)
            ->whereIn('id', CommunityMember::where('community_id', $this->post->community_id)
                ->where('status', CommunityMember::STATUS_ACTIVE)
                ->select('user_id'))
            ->chunkById(200, fn ($users) => Notification::send($users, new PostBroadcast($this->post)));
    }
}
