<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Community extends Model
{
    use HasFactory;

    public const PRIVACY_PUBLIC = 'public';

    public const PRIVACY_PRIVATE = 'private';

    protected $fillable = [
        'name',
        'slug',
        'tagline',
        'about',
        'icon_path',
        'cover_path',
        'privacy',
        'requires_approval',
        'join_questions',
    ];

    protected $hidden = ['icon_path', 'cover_path'];

    protected $appends = ['icon', 'cover'];

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'join_questions' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getIconAttribute(): ?string
    {
        return $this->icon_path ? Storage::disk('public')->url($this->icon_path) : null;
    }

    public function getCoverAttribute(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public function isPublic(): bool
    {
        return $this->privacy === self::PRIVACY_PUBLIC;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CommunityMember::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('status', CommunityMember::STATUS_ACTIVE);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('position');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class)->orderBy('position');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Per-instance cache so policy checks don't re-query on every call.
     *
     * @var array<int, CommunityMember|null>
     */
    protected array $membershipCache = [];

    public function membershipFor(?User $user): ?CommunityMember
    {
        if (! $user) {
            return null;
        }

        if (! array_key_exists($user->id, $this->membershipCache)) {
            $this->membershipCache[$user->id] = $this->members()->where('user_id', $user->id)->first();
        }

        return $this->membershipCache[$user->id];
    }

    public function forgetMembership(User $user): void
    {
        unset($this->membershipCache[$user->id]);
    }

    public function refreshMembersCount(): void
    {
        $this->forceFill(['members_count' => $this->activeMembers()->count()])->save();
    }
}
