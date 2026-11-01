<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Support\AuthorLevels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class FeedController extends Controller
{
    public const PER_PAGE = 15;

    public function index(Request $request, Community $community): Response
    {
        $this->ensureCanView($community);

        $filters = $request->validate([
            'category' => ['nullable', 'string', 'max:60'],
            'sort' => ['nullable', 'in:default,new,top'],
        ]);
        $sort = $filters['sort'] ?? 'default';
        $category = isset($filters['category'])
            ? $community->categories()->where('slug', $filters['category'])->first()
            : null;

        $showPinned = ! $category && $sort === 'default';

        $query = $this->baseQuery($community)
            ->when($category, fn (Builder $q) => $q->where('category_id', $category->id))
            ->when($showPinned, fn (Builder $q) => $q->where('is_pinned', false))
            ->when($sort === 'default', fn (Builder $q) => $q->orderByDesc('last_activity_at'))
            ->when($sort === 'new', fn (Builder $q) => $q->latest())
            ->when($sort === 'top', fn (Builder $q) => $q->orderByDesc('likes_count')->latest());

        $page = $query->simplePaginate(self::PER_PAGE);
        $posts = $this->present($community, $page->getCollection());

        return Inertia::render('communities/feed', [
            'community' => $this->communityProps($community),
            'categories' => $community->categories()->get(['id', 'name', 'slug', 'emoji', 'admin_only']),
            'filters' => ['category' => $category?->slug, 'sort' => $sort],
            'pinned' => fn () => $showPinned
                ? $this->present($community, $this->baseQuery($community)->where('is_pinned', true)->latest()->get())
                : [],
            // The client appends pages > 1 to what it already shows ("Load more").
            'posts' => $posts,
            'page' => $page->currentPage(),
            'nextPage' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
            'leaderboard' => Inertia::defer(fn () => $this->leaderboard($community)),
        ]);
    }

    /**
     * Search posts (title, body) and members (name, username) in one community.
     */
    public function search(Request $request, Community $community): Response
    {
        $this->ensureCanView($community);

        $q = mb_substr(trim((string) $request->query('q')), 0, 100);
        $like = '%'.addcslashes($q, '%_\\').'%';
        $searching = mb_strlen($q) >= 2;

        $posts = $searching
            ? $this->present($community, $this->baseQuery($community)
                ->where(fn (Builder $w) => $w->where('title', 'like', $like)->orWhere('body', 'like', $like))
                ->orderByDesc('last_activity_at')
                ->limit(30)
                ->get())
            : [];

        $members = $searching
            ? $community->activeMembers()
                ->whereHas('user', fn (Builder $u) => $u->whereNull('banned_at')
                    ->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('username', 'like', $like)))
                ->with('user')
                ->orderByDesc('points')
                ->limit(12)
                ->get()
                ->map(fn (CommunityMember $m) => $m->user->toPublicArray() + ['level' => $m->level, 'role' => $m->role])
                ->all()
            : [];

        return Inertia::render('communities/search', [
            'community' => $this->communityProps($community),
            'q' => $q,
            'posts' => $posts,
            'members' => $members,
        ]);
    }

    protected function baseQuery(Community $community): Builder
    {
        return Post::query()
            ->where('community_id', $community->id)
            ->with(['author', 'category'])
            ->withExists(['likes as liked' => fn (Builder $q) => $q->where('user_id', Auth::id() ?? 0)]);
    }

    /**
     * @param  Collection<int, Post>  $posts
     * @return list<array<string, mixed>>
     */
    protected function present(Community $community, Collection $posts): array
    {
        AuthorLevels::attach($community, $posts);

        // Avatars of up to 5 most recent distinct commenters per post.
        $commenters = Comment::query()
            ->whereIn('post_id', $posts->modelKeys())
            ->with('author')
            ->latest()
            ->limit(400)
            ->get()
            ->groupBy('post_id')
            ->map(fn ($comments) => $comments->unique('user_id')->take(5)->map(fn ($c) => $c->author->toPublicArray())->values());

        foreach ($posts as $post) {
            $post->setAttribute('recent_commenters', $commenters[$post->id] ?? []);
        }

        return PostResource::collection($posts)->resolve();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function leaderboard(Community $community): array
    {
        return $community->activeMembers()
            ->where('points', '>', 0)
            ->orderByDesc('points')
            ->with('user')
            ->limit(5)
            ->get()
            ->map(fn (CommunityMember $m) => $m->user->toPublicArray() + ['points' => $m->points, 'level' => $m->level])
            ->all();
    }
}
