<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Community;
use App\Models\Post;
use App\Services\LikeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function __construct(protected LikeService $likes) {}

    public function post(Request $request, Community $community, Post $post): RedirectResponse
    {
        abort_unless($post->community_id === $community->id, 404);

        return $this->toggle($request, $community, $post);
    }

    public function comment(Request $request, Community $community, Comment $comment): RedirectResponse
    {
        abort_unless($comment->post->community_id === $community->id, 404);

        return $this->toggle($request, $community, $comment);
    }

    protected function toggle(Request $request, Community $community, Post|Comment $likeable): RedirectResponse
    {
        $this->authorize('participate', $community);
        abort_if($likeable->user_id === $request->user()->id, 422, "You can't like your own content.");

        $this->likes->toggle($request->user(), $likeable);

        return back();
    }
}
