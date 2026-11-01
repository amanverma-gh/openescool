<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = ['user_a_id', 'user_b_id', 'a_read_at', 'b_read_at', 'last_message_at'];

    protected function casts(): array
    {
        return ['a_read_at' => 'datetime', 'b_read_at' => 'datetime', 'last_message_at' => 'datetime'];
    }

    public static function between(User $one, User $two): self
    {
        [$a, $b] = $one->id < $two->id ? [$one->id, $two->id] : [$two->id, $one->id];

        return static::firstOrCreate(['user_a_id' => $a, 'user_b_id' => $b]);
    }

    public static function findBetween(User $one, User $two): ?self
    {
        [$a, $b] = $one->id < $two->id ? [$one->id, $two->id] : [$two->id, $one->id];

        return static::where('user_a_id', $a)->where('user_b_id', $b)->first();
    }

    public function scopeFor(Builder $query, User $user): Builder
    {
        return $query->where(fn ($q) => $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id));
    }

    public function scopeUnreadFor(Builder $query, User $user): Builder
    {
        $unread = fn (string $side) => fn ($q) => $q->where("user_{$side}_id", $user->id)
            ->whereNotNull('last_message_at')
            ->where(fn ($q) => $q->whereNull("{$side}_read_at")->orWhereColumn("{$side}_read_at", '<', 'last_message_at'));

        return $query->where(fn ($q) => $q->where($unread('a'))->orWhere($unread('b')))
            // Chats whose messages were all held as spam don't count.
            ->whereHas('messages', fn ($m) => $m->where('is_spam', false)->where('user_id', '!=', $user->id));
    }

    public function userA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_a_id');
    }

    public function userB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_b_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function includes(User $user): bool
    {
        return in_array($user->id, [$this->user_a_id, $this->user_b_id], true);
    }

    public function otherUser(User $user): User
    {
        return $this->user_a_id === $user->id ? $this->userB : $this->userA;
    }

    public function readColumn(User $user): string
    {
        return $this->user_a_id === $user->id ? 'a_read_at' : 'b_read_at';
    }

    public function isUnreadFor(User $user): bool
    {
        $readAt = $this->{$this->readColumn($user)};

        return $this->last_message_at && (! $readAt || $readAt->lt($this->last_message_at));
    }

    public function markRead(User $user): void
    {
        $this->forceFill([$this->readColumn($user) => now()])->save();
    }
}
