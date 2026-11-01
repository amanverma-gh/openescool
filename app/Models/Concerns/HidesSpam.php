<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Content flagged as spam disappears everywhere until an admin clears it.
 * The moderation queue reads it with withoutGlobalScope('not_spam').
 */
trait HidesSpam
{
    public static function bootHidesSpam(): void
    {
        static::addGlobalScope('not_spam', fn (Builder $query) => $query->where($query->qualifyColumn('is_spam'), false));
    }

    public function initializeHidesSpam(): void
    {
        $this->mergeCasts(['is_spam' => 'boolean']);
    }
}
