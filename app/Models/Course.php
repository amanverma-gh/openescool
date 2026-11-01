<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    public const ACCESS_OPEN = 'open';

    public const ACCESS_LEVEL = 'level';

    protected $fillable = ['title', 'description', 'cover_path', 'access', 'unlock_level', 'is_published', 'position'];

    protected $hidden = ['cover_path'];

    protected $appends = ['cover'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function getCoverAttribute(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('position');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /**
     * Whether this membership has unlocked the course (managers always can).
     */
    public function isUnlockedFor(?CommunityMember $member): bool
    {
        if ($member?->canManage()) {
            return true;
        }

        if (! $member?->isActive() || ! $this->is_published) {
            return false;
        }

        return $this->access !== self::ACCESS_LEVEL || $member->level >= (int) $this->unlock_level;
    }
}
