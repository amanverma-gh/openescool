<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    public const MONTH = 'month';

    public const YEAR = 'year';

    public const ONE_TIME = 'one_time';

    /**
     * Currencies offered in the admin UI. Stripe supports many more.
     */
    public const CURRENCIES = ['USD', 'EUR', 'GBP', 'INR', 'AUD', 'CAD', 'SGD', 'AED'];

    protected $fillable = ['name', 'interval', 'amount', 'currency', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'amount' => 'integer'];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isRecurring(): bool
    {
        return $this->interval !== self::ONE_TIME;
    }

    /**
     * INR goes through Razorpay (UPI, Indian cards) when it is configured;
     * everything else goes through Stripe.
     */
    public function gateway(): string
    {
        return $this->currency === 'INR' && filled(config('services.razorpay.key_id')) ? 'razorpay' : 'stripe';
    }

    /**
     * Monthly-equivalent amount, for MRR.
     */
    public function monthlyAmount(): int
    {
        return match ($this->interval) {
            self::MONTH => $this->amount,
            self::YEAR => intdiv($this->amount, 12),
            default => 0,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return $this->only(['id', 'name', 'interval', 'amount', 'currency', 'is_active']) + ['gateway' => $this->gateway()];
    }
}
