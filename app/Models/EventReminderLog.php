<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per event occurrence that has been reminded about.
 */
class EventReminderLog extends Model
{
    protected $table = 'event_reminders';

    protected $fillable = ['event_id', 'occurrence_at'];

    protected function casts(): array
    {
        return ['occurrence_at' => 'datetime'];
    }
}
