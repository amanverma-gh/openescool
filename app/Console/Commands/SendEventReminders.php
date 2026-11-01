<?php

namespace App\Console\Commands;

use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\User;
use App\Notifications\EventReminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;

class SendEventReminders extends Command
{
    protected $signature = 'app:send-event-reminders {--minutes=60 : Remind this many minutes before the start}';

    protected $description = 'Email members about events starting soon';

    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $until = $now->addMinutes((int) $this->option('minutes'));
        $sent = 0;

        foreach (Event::with(['community', 'host'])->where('starts_at', '<=', $until)->cursor() as $event) {
            foreach ($event->occurrencesBetween($now, $until) as $startsAt) {
                // The unique (event, occurrence) row guarantees each reminder goes out once.
                try {
                    $event->reminders()->create(['occurrence_at' => $startsAt]);
                } catch (UniqueConstraintViolationException) {
                    continue;
                }

                $members = User::whereIn('id', $event->community->members()
                    ->where('status', CommunityMember::STATUS_ACTIVE)
                    ->select('user_id'));

                $members->chunkById(200, function ($users) use ($event, $startsAt) {
                    Notification::send($users, new EventReminder($event, $startsAt));
                });
                $sent++;
            }
        }

        $this->info("Sent reminders for {$sent} event(s).");

        return self::SUCCESS;
    }
}
