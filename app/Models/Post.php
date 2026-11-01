<?php

namespace App\Models;

use App\Models\Concerns\HidesSpam;
use App\Services\MediaAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;
    use HidesSpam;

    protected $fillable = ['user_id', 'category_id', 'title', 'body', 'is_pinned', 'comments_disabled', 'last_activity_at', 'is_spam', 'attachments'];

    /** Upper bound for the per-community "photos per post" setting. */
    public const MAX_ATTACHMENTS = 4;

    /** A community can email one post to all members per this many hours. */
    public const EMAIL_COOLDOWN_HOURS = 24;

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'comments_disabled' => 'boolean',
            'last_activity_at' => 'datetime',
            'emailed_at' => 'datetime',
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
     * @param  list<string>  $entries
     */
    public static function deleteFiles(array $entries): void
    {
        MediaAttachments::deleteFiles($entries);
    }

    /**
     * @return list<array{path: string, url: string, gif: bool}>
     */
    public function attachmentList(): array
    {
        return array_map(MediaAttachments::present(...), $this->attachments ?? []);
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
