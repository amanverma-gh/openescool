<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    public const ACTIVE = 'active';

    public const PAST_DUE = 'past_due';

    public const ENDED = 'ended'; // access removed; a pending cancellation is cancel_at_period_end

    /**
     * Days of access kept after a failed renewal while the gateway retries.
     */
    public const GRACE_DAYS = 3;

    protected $fillable = ['user_id', 'community_id', 'plan_id', 'gateway', 'gateway_id', 'status', 'cancel_at_period_end', 'current_period_end', 'ended_at'];

    protected function casts(): array
    {
        return [
            'cancel_at_period_end' => 'boolean',
            'current_period_end' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * One-time purchases never renew and never expire.
     */
    public function isLifetime(): bool
    {
        return $this->current_period_end === null;
    }

    public function grantsAccess(): bool
    {
        if ($this->status === self::ENDED) {
            return false;
        }

        return $this->current_period_end === null
            || $this->current_period_end->copy()->addDays(self::GRACE_DAYS)->isFuture();
    }
}
