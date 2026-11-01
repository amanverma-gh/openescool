<?php

namespace App\Models;

use App\Models\Concerns\HidesSpam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Post extends Model
{
    use HasFactory, SoftDeletes;
    use HidesSpam;

    protected $fillable = ['user_id', 'category_id', 'title', 'body', 'is_pinned', 'comments_disabled', 'last_activity_at', 'is_spam', 'attachments'];

    /** Photos and GIFs per post, each at most MAX_ATTACHMENT_KB. */
    public const MAX_ATTACHMENTS = 4;

    public const MAX_ATTACHMENT_KB = 1024;

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'comments_disabled' => 'boolean',
            'last_activity_at' => 'datetime',
            'attachments' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Post $post) {
            $post->last_activity_at ??= now();
        });

        // Soft-deleted posts keep their files so a restore still works.
        static::forceDeleted(fn (Post $post) => static::deleteFiles($post->attachments ?? []));
    }

    /**
     * @param  list<string>  $paths
     */
    public static function deleteFiles(array $paths): void
    {
        if ($paths) {
            Storage::disk('public')->delete($paths);
        }
    }

    /**
     * @return list<array{path: string, url: string, gif: bool}>
     */
    public function attachmentList(): array
    {
        return array_map(fn (string $path) => [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'gif' => str_ends_with(strtolower($path), '.gif'),
        ], $this->attachments ?? []);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}
