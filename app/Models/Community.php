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

    public const ACCESS_FREE = 'free';

    public const ACCESS_PAID = 'paid';

    protected $fillable = [
        'name',
        'slug',
        'tagline',
        'about',
        'icon_path',
        'cover_path',
        'privacy',
        'access_type',
        'requires_approval',
        'join_questions',
        'level_names',
        'allow_images',
        'allow_gifs',
    ];

    protected $hidden = ['icon_path', 'cover_path'];

    protected $appends = ['icon', 'cover'];

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'join_questions' => 'array',
            'level_names' => 'array',
            'allow_images' => 'boolean',
            'allow_gifs' => 'boolean',
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

    /**
     * "Level 3 (Builder)" when the owner named the level, else "Level 3".
     */
    public function levelLabel(int $level): string
    {
        $name = $this->level_names[$level - 1] ?? null;

        return $name ? "Level {$level} ({$name})" : "Level {$level}";
    }

    /**
     * @return list<string|null>
     */
    public function levelNames(): array
    {
        return array_map(fn (int $i) => $this->level_names[$i] ?? null, array_keys(CommunityMember::LEVEL_THRESHOLDS));
    }

    public function isPublic(): bool
    {
        return $this->privacy === self::PRIVACY_PUBLIC;
    }

    /**
     * Paid communities need an active subscription to join (if they have plans for sale).
     */
    public function isPaid(): bool
    {
        return $this->access_type === self::ACCESS_PAID;
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

    public function media(): HasMany
    {
        return $this->hasMany(CommunityMedia::class)->orderBy('position');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return array{average: float|null, count: int}
     */
    public function ratingSummary(): array
    {
        $row = $this->reviews()->visible()->selectRaw('avg(rating) as average, count(*) as total')->first();

        return [
            'average' => $row->total ? round((float) $row->average, 1) : null,
            'count' => (int) $row->total,
        ];
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class)->orderBy('amount');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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
