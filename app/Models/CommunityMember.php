<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityMember extends Model
{
    public const ROLE_OWNER = 'owner';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MODERATOR = 'moderator';

    public const ROLE_MEMBER = 'member';

    /** The community team: these roles can chat with members. */
    public const STAFF_ROLES = [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_MODERATOR];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING = 'pending';

    public const STATUS_BANNED = 'banned';

    public const STATUS_EXPIRED = 'expired'; // paid access ended; points and history kept

    /**
     * Points needed to reach each level (index = level - 1).
     */
    public const LEVEL_THRESHOLDS = [0, 5, 20, 65, 155, 515, 2015, 8015, 33015];

    protected $fillable = ['community_id', 'user_id', 'role', 'status', 'answers', 'points', 'joined_at'];

    protected $appends = ['level'];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'joined_at' => 'datetime',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getLevelAttribute(): int
    {
        return static::levelForPoints($this->points);
    }

    public static function levelForPoints(int $points): int
    {
        $level = 1;

        foreach (self::LEVEL_THRESHOLDS as $index => $threshold) {
            if ($points >= $threshold) {
                $level = $index + 1;
            }
        }

        return $level;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Owners and admins manage settings, categories and roles.
     */
    public function canManage(): bool
    {
        return $this->isActive() && in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN], true);
    }

    /**
     * Moderators additionally pin, edit and delete other members' content.
     */
    public function canModerate(): bool
    {
        return $this->canManage() || ($this->isActive() && $this->role === self::ROLE_MODERATOR);
    }
}
