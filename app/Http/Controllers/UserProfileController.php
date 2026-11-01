<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserProfileController extends Controller
{
    public function show(Request $request, User $user): Response
    {
        $viewer = $request->user();

        // Only show communities the viewer is allowed to see.
        $communities = $user->communities()->get()
            ->filter(fn (Community $c) => $c->isPublic() || $c->membershipFor($viewer)?->isActive())
            ->values();

        $posts = Post::query()
            ->where('user_id', $user->id)
            ->whereIn('community_id', $communities->pluck('id'))
            ->with('community:id,name,slug')
            ->latest()
            ->limit(10)
            ->get(['id', 'community_id', 'title', 'likes_count', 'comments_count', 'created_at']);

        return Inertia::render('users/show', [
            'profile' => $user->toPublicArray() + [
                'bio' => $user->bio,
                'location' => $user->location,
                'joined_at' => $user->created_at,
                'last_seen_at' => $user->last_seen_at,
                'is_self' => $viewer?->is($user) ?? false,
            ],
            // Platform admins get the moderation controls on profiles.
            'moderation' => $viewer?->is_admin && ! $viewer->is($user) ? [
                'restricted' => $user->isRestricted(),
                'banned' => $user->isBanned(),
                'is_admin' => (bool) $user->is_admin,
            ] : null,
            'communities' => $communities->map(fn (Community $c) => $c->only(['id', 'name', 'slug', 'icon']) + [
                'level' => CommunityMember::levelForPoints((int) $c->pivot->points),
                'role' => $c->pivot->role,
            ]),
            'posts' => $posts,
            'stats' => [
                'posts' => Post::where('user_id', $user->id)->count(),
                'likes' => (int) Post::where('user_id', $user->id)->sum('likes_count'),
            ],
        ]);
    }
}
