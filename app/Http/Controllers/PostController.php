<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommentResource;
use App\Http\Resources\PostResource;
use App\Models\Comment;
use App\Models\Community;
use App\Models\Post;
use App\Notifications\Mentioned;
use App\Services\SpamGuard;
use App\Support\AuthorLevels;
use App\Support\Mentions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function show(Request $request, Community $community, Post $post): Response
    {
        $this->ensureCanView($community);
        abort_unless($post->community_id === $community->id, 404);

        $post->load(['author', 'category'])
            ->loadExists(['likes as liked' => fn (Builder $q) => $q->where('user_id', Auth::id() ?? 0)]);

        $likedScope = ['likes as liked' => fn (Builder $q) => $q->where('user_id', Auth::id() ?? 0)];

        $comments = $post->comments()
            ->whereNull('parent_id')
            ->with(['author', 'replies' => fn ($q) => $q->with('author')->withExists($likedScope)])
            ->withExists($likedScope)
            ->oldest()
            ->get();

        AuthorLevels::attach($community, $comments->concat($comments->pluck('replies')->flatten())->push($post));

        return Inertia::render('posts/show', [
            'community' => $this->communityProps($community),
            'categories' => $community->categories()->get(['id', 'name', 'slug', 'emoji', 'admin_only']),
            'post' => (new PostResource($post))->full()->resolve(),
            'comments' => CommentResource::collection($comments)->resolve(),
            'canComment' => (bool) $request->user()?->can('comment', $post),
        ]);
    }

    public function store(Request $request, Community $community, SpamGuard $spam): RedirectResponse
    {
        $this->authorize('participate', $community);

        $data = $this->validated($request, $community);

        if ($reason = $spam->check($request->user(), $data['title'].' '.$data['body'])) {
            $community->posts()->create([...$data, 'user_id' => $request->user()->id, 'is_spam' => true]);
            $spam->restrict($request->user(), $reason);

            return back()->with('error', 'Your post is being reviewed by an admin. You can post again once it is approved.');
        }

        $post = $community->posts()->create([...$data, 'user_id' => $request->user()->id]);

        $this->notifyMentions($post, $post->body);

        return back()->with('success', 'Post published.');
    }

    public function update(Request $request, Community $community, Post $post): RedirectResponse
    {
        abort_unless($post->community_id === $community->id, 404);
        $this->authorize('update', $post);

        $data = $this->validated($request, $community);

        // Editing an approved post into spam is a common trick.
        if ($reason = app(SpamGuard::class)->check($request->user(), $data['title'].' '.$data['body'])) {
            $post->update([...$data, 'is_spam' => true]);
            app(SpamGuard::class)->restrict($request->user(), $reason);

            return to_route('communities.feed', $community)->with('error', 'Your post is being reviewed by an admin.');
        }

        $post->update($data);

        return back()->with('success', 'Post updated.');
    }

    public function destroy(Community $community, Post $post): RedirectResponse
    {
        abort_unless($post->community_id === $community->id, 404);
        $this->authorize('delete', $post);

        $post->delete();

        return to_route('communities.feed', $community)->with('success', 'Post deleted.');
    }

    public function pin(Request $request, Community $community, Post $post): RedirectResponse
    {
        abort_unless($post->community_id === $community->id, 404);
        $this->authorize('pin', $post);

        $post->update(['is_pinned' => ! $post->is_pinned]);

        return back()->with('success', $post->is_pinned ? 'Post pinned to the top of the feed.' : 'Post unpinned.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, Community $community): array
    {
        $canModerate = $request->user()->can('moderate', $community);

        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')
                    ->where('community_id', $community->id)
                    ->when(! $canModerate, fn ($rule) => $rule->where('admin_only', false)),
            ],
            'comments_disabled' => ['sometimes', 'boolean'],
        ]);
    }

    protected function notifyMentions(Post $post, string $text, ?Comment $comment = null): void
    {
        $author = $comment?->author ?? $post->author;

        Notification::send(
            Mentions::resolve($text, $post->community, except: $author),
            new Mentioned($author, $post, $comment),
        );
    }
}
