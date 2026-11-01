<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\Event;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request, Community $community): Response
    {
        $this->ensureCanView($community);

        $month = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? now()->format('Y-m');
        $start = CarbonImmutable::createFromFormat('Y-m-d', "{$month}-01", 'UTC')->startOfMonth();

        // A few days of padding so viewers in any timezone see the whole month.
        $from = $start->subDays(7);
        $to = $start->endOfMonth()->addDays(7);
        $isMember = (bool) $community->membershipFor($request->user())?->isActive();

        $events = $community->events()->with('host')->get();

        $present = fn (Event $event, CarbonImmutable $at) => [
            'key' => "{$event->id}-{$at->timestamp}",
            'event_id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'starts_at' => $at->toIso8601String(),
            'ends_at' => $at->addMinutes($event->duration_minutes)->toIso8601String(),
            'first_starts_at' => $event->starts_at->toIso8601String(),
            'duration_minutes' => $event->duration_minutes,
            'recurrence' => $event->recurrence,
            'repeat_until' => $event->repeat_until?->toDateString(),
            'timezone' => $event->timezone,
            // Meeting links are for members only.
            'location' => $isMember ? $event->location : null,
            'host' => $event->host->toPublicArray(),
        ];

        $occurrences = $events
            ->flatMap(fn (Event $e) => $e->occurrencesBetween($from, $to)->map(fn ($at) => $present($e, $at)))
            ->sortBy('starts_at')
            ->values();

        $upcoming = $events
            ->flatMap(fn (Event $e) => $e->occurrencesBetween(CarbonImmutable::now()->subHour(), CarbonImmutable::now()->addDays(60))
                ->map(fn ($at) => $present($e, $at)))
            ->sortBy('starts_at')
            ->take(5)
            ->values();

        return Inertia::render('calendar/index', [
            'community' => $this->communityProps($community),
            'month' => $month,
            'occurrences' => $occurrences,
            'upcoming' => $upcoming,
        ]);
    }

    public function store(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('manage', $community);

        $event = new Event($this->validated($request));
        $event->community()->associate($community);
        $event->host()->associate($request->user());
        $event->save();

        return back()->with('success', 'Event scheduled.');
    }

    public function update(Request $request, Community $community, Event $event): RedirectResponse
    {
        $this->authorize('manage', $community);
        abort_unless($event->community_id === $community->id, 404);

        $event->update($this->validated($request));

        return back()->with('success', 'Event updated.');
    }

    public function destroy(Community $community, Event $event): RedirectResponse
    {
        $this->authorize('manage', $community);
        abort_unless($event->community_id === $community->id, 404);

        $event->delete();

        return back()->with('success', 'Event deleted.');
    }

    /**
     * iCalendar file so members can add the event to Google, Apple or Outlook calendars.
     */
    public function ics(Request $request, Community $community, Event $event): \Illuminate\Http\Response
    {
        $this->ensureCanView($community);
        abort_unless($event->community_id === $community->id, 404);

        $isMember = (bool) $community->membershipFor($request->user())?->isActive();
        $format = fn (CarbonImmutable $at) => $at->utc()->format('Ymd\THis\Z');
        $escape = fn (?string $text) => str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], (string) $text);
        $url = route('calendar.index', $community);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.config('app.name').'//Calendar//EN',
            'BEGIN:VEVENT',
            'UID:event-'.$event->id.'@'.parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.$format(CarbonImmutable::now()),
            'DTSTART:'.$format($event->starts_at),
            'DTEND:'.$format($event->starts_at->addMinutes($event->duration_minutes)),
            'SUMMARY:'.$escape($event->title),
            'DESCRIPTION:'.$escape(trim($event->description."\n\n".$url)),
            'LOCATION:'.$escape($isMember ? $event->location : $url),
            'URL:'.$url,
        ];

        if ($event->recurrence === Event::RECURRENCE_WEEKLY) {
            $lines[] = 'RRULE:FREQ=WEEKLY'.($event->repeat_until ? ';UNTIL='.$format($event->repeat_until->endOfDay()) : '');
        }

        $lines = [...$lines, 'END:VEVENT', 'END:VCALENDAR'];

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($event->title).'.ics"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            // Local date/time as typed by the admin, interpreted in `timezone`.
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            // ALL_WITH_BC: browsers still report legacy names like "Asia/Calcutta".
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC))],
            'duration_minutes' => ['required', 'integer', 'between:15,720'],
            'recurrence' => ['required', Rule::in([Event::RECURRENCE_NONE, Event::RECURRENCE_WEEKLY])],
            'repeat_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'location' => ['nullable', 'string', 'max:500'],
        ]);

        $data['starts_at'] = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$data['date']} {$data['time']}", $data['timezone'])->utc();
        if ($data['recurrence'] === Event::RECURRENCE_NONE) {
            $data['repeat_until'] = null;
        }
        unset($data['date'], $data['time']);

        return $data;
    }
}
