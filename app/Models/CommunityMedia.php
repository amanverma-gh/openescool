<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CommunityMedia extends Model
{
    public const IMAGE = 'image';

    public const VIDEO = 'video';

    public const MAX_ITEMS = 8;

    /**
     * Video hosts we can embed safely.
     */
    public const VIDEO_PATTERN = '~^https?://(www\.)?(youtube\.com/(watch\?v=|shorts/|embed/)|youtu\.be/|vimeo\.com/\d|loom\.com/share/)~i';

    protected $table = 'community_media';

    protected $fillable = ['type', 'path', 'url', 'position'];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'src' => $this->type === self::IMAGE ? Storage::disk('public')->url($this->path) : $this->url,
        ];
    }
}
