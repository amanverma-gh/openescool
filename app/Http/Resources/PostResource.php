<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin Post
 */
class PostResource extends JsonResource
{
    /**
     * Feed cards get a trimmed body; the post page sets $full.
     */
    public bool $full = false;

    public function full(): static
    {
        $this->full = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->full ? $this->body : Str::limit($this->body, 280),
            'is_pinned' => $this->is_pinned,
            'comments_disabled' => $this->comments_disabled,
            'likes_count' => $this->likes_count,
            'comments_count' => $this->comments_count,
            'liked' => (bool) ($this->liked ?? false),
            'author' => $this->author->toPublicArray() + ['level' => $this->author_level],
            'attachments' => $this->attachmentList(),
            'category' => $this->category?->only(['id', 'name', 'slug', 'emoji']),
            'recent_commenters' => $this->recent_commenters ?? [],
            'created_at' => $this->created_at,
            'last_activity_at' => $this->last_activity_at,
            'can' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
                'pin' => (bool) $user?->can('pin', $this->resource),
            ],
        ];
    }
}
