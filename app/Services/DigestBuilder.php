<?php

namespace App\Services;

use App\Models\Community;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Collects what a member missed: top posts per community plus upcoming events.
 */
class DigestBuilder
{
    public const POSTS_PER_COMMUNITY = 5;

    /**
     * @return list<array{community: array<string, string>, new_posts: int, posts: list<array<string, mixed>>, events: list<array<string, mixed>>}>
     */
    public function build(User $user, CarbonImmutable $since, CarbonImmutable $eventsUntil): array
    {
        $communities = Community::query()
            ->whereIn('id', $user->memberships()->where('status', 'active')->select('community_id'))
            ->orderBy('name')
            ->get();

        $sections = [];

        foreach ($communities as $community) {
            $recent = $community->posts()->where('created_at', '>=', $since)->where('user_id', '!=', $user->id);

            $posts = (clone $recent)
                ->with('author:id,name,username')
                ->orderByRaw('likes_count + 2 * comments_count desc')
                ->latest()
                ->limit(self::POSTS_PER_COMMUNITY)
                ->get()
                ->map(fn (Post $post) => [
                    'title' => $post->title,
                    'excerpt' => Str::limit(preg_replace('/\s+/', ' ', (string) $post->body), 140),
                    'author' => $post->author->name,
                    'likes' => $post->likes_count,
                    'comments' => $post->comments_count,
                    'url' => route('posts.show', [$community, $post]),
                ])
                ->all();

            $events = $community->events()->get()
                ->flatMap(fn (Event $event) => $event->occurrencesBetween(now()->toImmutable(), $eventsUntil)
                    ->map(fn (CarbonImmutable $at) => ['event' => $event, 'at' => $at]))
                ->sortBy('at')
                ->take(3)
                ->map(fn (array $o) => [
                    'title' => $o['event']->title,
                    // Shown in the host's timezone, labelled, since we don't know the reader's.
                    'when' => $o['at']->setTimezone($o['event']->timezone)->format('D j M, g:i A').' ('.$o['event']->timezone.')',
                    'url' => route('calendar.index', $community),
                ])
                ->values()
                ->all();

            if ($posts || $events) {
                $sections[] = [
                    'community' => ['name' => $community->name, 'url' => route('communities.feed', $community)],
                    'new_posts' => (clone $recent)->count(),
                    'posts' => $posts,
                    'events' => $events,
                ];
            }
        }

        return $sections;
    }
}
