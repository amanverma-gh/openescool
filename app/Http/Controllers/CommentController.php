<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Community;
use App\Models\Post;
use App\Notifications\Mentioned;
use App\Notifications\NewComment;
use App\Support\Mentions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    public function store(Request $request, Community $community, Post $post): RedirectResponse
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

        $comment = DB::transaction(function () use ($post, $request, $data, $parent) {
            $comment = $post->comments()->create([
                'user_id' => $request->user()->id,
                'parent_id' => $parent?->id,
                'body' => $data['body'],
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

        $comment->update($request->validate(['body' => ['required', 'string', 'max:5000']]));

        return back();
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
