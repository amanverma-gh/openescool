<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Event extends Model
{
    public const RECURRENCE_NONE = 'none';

    public const RECURRENCE_WEEKLY = 'weekly';

    protected $fillable = ['title', 'description', 'starts_at', 'duration_minutes', 'timezone', 'recurrence', 'repeat_until', 'location'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'repeat_until' => 'immutable_date',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(EventReminderLog::class);
    }

    /**
     * Start times (UTC) of every occurrence that starts inside [from, to].
     * Weekly repeats keep the same local wall-clock time in the host's zone,
     * so a 7pm call stays at 7pm across daylight-saving changes.
     *
     * @return Collection<int, CarbonImmutable>
     */
    public function occurrencesBetween(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        if ($this->recurrence !== self::RECURRENCE_WEEKLY) {
            return $this->starts_at->between($from, $to) ? collect([$this->starts_at]) : collect();
        }

        $local = $this->starts_at->setTimezone($this->timezone);
        $until = $this->repeat_until?->endOfDay()->setTimezone('UTC');
        $occurrences = collect();

        // Jump close to `from` instead of walking week by week from the first date.
        $weeksToSkip = max(0, (int) floor($this->starts_at->diffInWeeks($from, false)) - 1);
        $current = $local->addWeeks($weeksToSkip);

        while (($utc = $current->setTimezone('UTC'))->lte($to)) {
            if ($until && $utc->gt($until)) {
                break;
            }
            if ($utc->gte($from)) {
                $occurrences->push($utc);
            }
            $current = $current->addWeek();
        }

        return $occurrences;
    }
}
