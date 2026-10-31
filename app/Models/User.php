<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'bio',
        'location',
        'avatar_path',
        'google_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
        'avatar_path',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['avatar'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->username ??= static::generateUsername($user->name);
        });
    }

    public static function generateUsername(string $name): string
    {
        $base = Str::slug($name) ?: 'member';

        do {
            $username = $base.'-'.random_int(1000, 9999);
        } while (static::where('username', $username)->exists());

        return $username;
    }

    public function getAvatarAttribute(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return Str::startsWith($this->avatar_path, 'http')
            ? $this->avatar_path
            : Storage::disk('public')->url($this->avatar_path);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CommunityMember::class);
    }

    public function communities(): BelongsToMany
    {
        return $this->belongsToMany(Community::class, 'community_members')
            ->withPivot(['role', 'status', 'points'])
            ->wherePivot('status', CommunityMember::STATUS_ACTIVE);
    }

    public function membershipIn(Community $community): ?CommunityMember
    {
        return $this->memberships->firstWhere('community_id', $community->id);
    }

    /**
     * Minimal public representation used on posts, comments and member lists.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'avatar' => $this->avatar,
        ];
    }
}
