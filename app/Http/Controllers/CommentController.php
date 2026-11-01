<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Community;
use App\Models\Post;
use App\Notifications\Mentioned;
use App\Notifications\NewComment;
use App\Services\MediaAttachments;
use App\Services\SpamGuard;
use App\Support\Mentions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommentController extends Controller
{
    public function store(Request $request, Community $community, Post $post, SpamGuard $spam): RedirectResponse
    {
        abort_unless($post->community_id === $community->id, 404);
        $this->authorize('comment', $post);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            // Replies are one level deep, like Skool: replying to a reply attaches to its parent.
            'parent_id' => ['nullable', Rule::exists('comments', 'id')->where('post_id', $post->id)],
        ]);

        $parent = isset($data['parent_id']) ? Comment::find($data['parent_id']) : null;
        $parent = $parent?->parent_id ? $parent->parent : $parent;

        if ($reason = $spam->check($request->user(), $data['body'])) {
            $post->comments()->create(['user_id' => $request->user()->id, 'parent_id' => $parent?->id, 'body' => $data['body'], 'is_spam' => true]);
            $spam->restrict($request->user(), $reason);

            return back()->with('error', 'Your comment is being reviewed by an admin. You can comment again once it is approved.');
        }

        $attachment = $this->attachment($request, $community, null);

        $comment = DB::transaction(function () use ($post, $request, $data, $parent, $attachment) {
            $comment = $post->comments()->create([
                'user_id' => $request->user()->id,
                'parent_id' => $parent?->id,
                'body' => $data['body'],
                'attachment' => $attachment,
            ]);

            $post->increment('comments_count', 1, ['last_activity_at' => now()]);

            return $comment;
        });

        $this->notify($comment, $post, $parent);

        return back();
    }

    public function update(Request $request, Community $community, Comment $comment): RedirectResponse
    {
        abort_unless($comment->post->community_id === $community->id, 404);
        $this->authorize('update', $comment);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        if ($reason = app(SpamGuard::class)->check($request->user(), $data['body'])) {
            $comment->update([...$data, 'is_spam' => true]);
            app(SpamGuard::class)->restrict($request->user(), $reason);

            return back()->with('error', 'Your comment is being reviewed by an admin.');
        }

        $old = $comment->attachment;
        $kept = $request->boolean('remove_attachment') ? null : $old;
        $attachment = $this->attachment($request, $community, $kept);

        $comment->update([...$data, 'attachment' => $attachment]);
        if ($old && $old !== $attachment) {
            MediaAttachments::deleteFiles([$old]);
        }

        return back();
    }

    /**
     * One photo or one GIPHY GIF per comment, when the community allows it.
     * A new one replaces the current one.
     */
    protected function attachment(Request $request, Community $community, ?string $current): ?string
    {
        $adding = $request->hasFile('image') || $request->filled('gif_id');

        if (! $adding) {
            return $current;
        }

        if (! $community->allow_comment_media) {
            throw ValidationException::withMessages(['image' => 'Photos and GIFs are turned off for comments here.']);
        }

        return app(MediaAttachments::class)->collect($request, $community, [], 1, 'image', "comments/{$community->id}")[0] ?? null;
    }

    public function destroy(Community $community, Comment $comment): RedirectResponse
    {
        abort_unless($comment->post->community_id === $community->id, 404);
        $this->authorize('delete', $comment);

        DB::transaction(function () use ($comment) {
            $removed = 1 + $comment->replies()->count();
            $comment->replies()->delete();
            $comment->delete();
            $comment->post->decrement('comments_count', $removed);
        });

        return back();
    }

    protected function notify(Comment $comment, Post $post, ?Comment $parent): void
    {
        $comment->load('author');
        $mentioned = Mentions::resolve($comment->body, $post->community, except: $comment->author);

        // Post author and parent-comment author hear about it, unless they wrote it or were mentioned.
        $recipients = collect([
            [$post->author, false],
            [$parent?->author, true],
        ])->filter(fn ($pair) => $pair[0]
            && $pair[0]->isNot($comment->author)
            && ! $mentioned->contains($pair[0])
        )->unique(fn ($pair) => $pair[0]->id);

        foreach ($recipients as [$user, $isReply]) {
            $user->notify(new NewComment($comment, $isReply));
        }

        Notification::send($mentioned, new Mentioned($comment->author, $post, $comment));
    }
}
