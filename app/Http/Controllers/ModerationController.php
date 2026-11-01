<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Message;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Services\SpamGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform-admin queue: everyone under review, with their flagged content and
 * reports, plus the decisions (not spam / ban / delete account).
 */
class ModerationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureAdmin($request);

        $tab = $request->query('tab') === 'banned' ? 'banned' : 'review';

        $users = User::query()
            ->when($tab === 'review', fn ($q) => $q->whereNotNull('restricted_at')->whereNull('banned_at')->orderByDesc('restricted_at'))
            ->when($tab === 'banned', fn ($q) => $q->whereNotNull('banned_at')->orderByDesc('banned_at'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => $user->toPublicArray() + [
                'email' => $user->email,
                'joined' => $user->created_at,
                'restricted_at' => $user->restricted_at,
                'banned_at' => $user->banned_at,
                'reason' => $user->restricted_reason,
                'reports' => Report::where('reported_user_id', $user->id)->whereNull('resolved_at')->count(),
                'samples' => $this->samples($user),
            ]);

        return Inertia::render('admin/moderation', [
            'tab' => $tab,
            'users' => $users,
            'counts' => [
                'review' => User::whereNotNull('restricted_at')->whereNull('banned_at')->count(),
                'banned' => User::whereNotNull('banned_at')->count(),
            ],
        ]);
    }

    /**
     * Put a user under review by hand (e.g. from their profile).
     */
    public function restrict(Request $request, User $user, SpamGuard $spam): RedirectResponse
    {
        $this->ensureAdmin($request);
        $spam->restrict($user, 'Marked as spam by an admin');

        return back()->with('success', "{$user->name} is under review and can't post or message.");
    }

    public function release(Request $request, User $user, SpamGuard $spam): RedirectResponse
    {
        $this->ensureAdmin($request);
        $spam->release($user);

        return back()->with('success', "{$user->name} is cleared and their content is visible again.");
    }

    public function ban(Request $request, User $user, SpamGuard $spam): RedirectResponse
    {
        $this->ensureAdmin($request);
        $spam->ban($user);

        return back()->with('success', "{$user->name} is banned.");
    }

    public function unban(Request $request, User $user, SpamGuard $spam): RedirectResponse
    {
        $this->ensureAdmin($request);
        $user->forceFill(['banned_at' => null])->save();
        $spam->release($user);

        return back()->with('success', "{$user->name} can sign in again.");
    }

    public function destroy(Request $request, User $user, SpamGuard $spam): RedirectResponse
    {
        $this->ensureAdmin($request);
        $name = $user->name;
        $spam->deleteAccount($user);

        return to_route('moderation.index')->with('success', "{$name}'s account and everything they posted was deleted.");
    }

    /**
     * Members flag a post, comment or message as spam.
     */
    public function report(Request $request, string $type, int $id, SpamGuard $spam): RedirectResponse
    {
        $item = match ($type) {
            'post' => Post::find($id),
            'comment' => Comment::find($id),
            'message' => Message::find($id),
            default => null,
        };
        abort_unless($item, 404);

        // You can only report what you can see.
        if ($item instanceof Post) {
            $this->authorize('view', $item->community);
        } elseif ($item instanceof Comment) {
            $this->authorize('view', $item->post->community);
        } else {
            abort_unless($item->conversation->includes($request->user()), 404);
        }

        $spam->report($request->user(), $item);

        return back()->with('success', 'Thanks. Our admins will take a look.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function samples(User $user): array
    {
        $items = collect()
            ->concat(Post::withoutGlobalScopes()->where('user_id', $user->id)->latest()->limit(3)->get()
                ->map(fn (Post $p) => ['kind' => 'Post', 'text' => $p->title.': '.$p->body, 'spam' => $p->is_spam, 'at' => $p->created_at]))
            ->concat(Comment::withoutGlobalScopes()->where('user_id', $user->id)->latest()->limit(3)->get()
                ->map(fn (Comment $c) => ['kind' => 'Comment', 'text' => $c->body, 'spam' => $c->is_spam, 'at' => $c->created_at]))
            // Private messages are shown only when flagged (by the filter or a report), never in general.
            ->concat(Message::where('user_id', $user->id)
                ->where(fn ($q) => $q->where('is_spam', true)->orWhereIn('id', Report::where('reportable_type', 'message')->where('reported_user_id', $user->id)->select('reportable_id')))
                ->latest()->limit(3)->get()
                ->map(fn (Message $m) => ['kind' => 'Message', 'text' => $m->body, 'spam' => $m->is_spam, 'at' => $m->created_at]));

        return $items->sortByDesc('at')->take(4)
            ->map(fn (array $item) => [...$item, 'text' => Str::limit($item['text'], 240)])
            ->values()
            ->all();
    }

    protected function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_admin, 403);
    }
}
