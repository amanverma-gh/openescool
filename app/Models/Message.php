<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Message extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'body', 'fingerprint', 'is_spam'];

    protected function casts(): array
    {
        return ['is_spam' => 'boolean'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Same text with different spacing/case/digits still matches.
     */
    public static function fingerprint(string $body): string
    {
        return hash('sha256', preg_replace('/[\s\d]+/', ' ', Str::lower(trim($body))));
    }
}
