import { EventForm } from '@/components/calendar/event-form';
import { RichText } from '@/components/community/rich-text';
import { UserAvatar } from '@/components/community/user-avatar';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import CommunityLayout from '@/layouts/community-layout';
import { cn } from '@/lib/utils';
import { type Community, type EventOccurrence } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { CalendarPlus, ChevronLeft, ChevronRight, Clock, Download, Link2, Plus, Repeat } from 'lucide-react';
import { useMemo, useState } from 'react';

interface CalendarProps {
    community: Community;
    month: string; // YYYY-MM
    occurrences: EventOccurrence[];
    upcoming: EventOccurrence[];
}

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const dayKey = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const timeLabel = (iso: string) => new Date(iso).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
const dateLabel = (iso: string) => new Date(iso).toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });

function shiftMonth(month: string, delta: number): string {
    const [y, m] = month.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

function googleCalendarUrl(o: EventOccurrence): string {
    const fmt = (iso: string) =>
        new Date(iso)
            .toISOString()
            .replace(/[-:]/g, '')
            .replace(/\.\d{3}/, '');
    const params = new URLSearchParams({
        action: 'TEMPLATE',
        text: o.title,
        dates: `${fmt(o.starts_at)}/${fmt(o.ends_at)}`,
        details: o.description ?? '',
        location: o.location ?? window.location.href,
    });
    return `https://calendar.google.com/calendar/render?${params}`;
}

export default function Calendar({ community, month, occurrences, upcoming }: CalendarProps) {
    const [selected, setSelected] = useState<EventOccurrence | null>(null);
    const [editing, setEditing] = useState(false);
    const [creatingOn, setCreatingOn] = useState<string | null>(null);

    // 6-week grid starting on the Sunday on/before the 1st, in the viewer's own timezone.
    const days = useMemo(() => {
        const [y, m] = month.split('-').map(Number);
        const first = new Date(y, m - 1, 1);
        const start = new Date(y, m - 1, 1 - first.getDay());
        return Array.from({ length: 42 }, (_, i) => new Date(start.getFullYear(), start.getMonth(), start.getDate() + i));
    }, [month]);

    const byDay = useMemo(() => {
        const map: Record<string, EventOccurrence[]> = {};
        for (const o of occurrences) (map[dayKey(new Date(o.starts_at))] ??= []).push(o);
        return map;
    }, [occurrences]);

    const monthIndex = Number(month.split('-')[1]) - 1;
    const today = dayKey(new Date());
    const title = new Date(Number(month.slice(0, 4)), monthIndex, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    const zone = Intl.DateTimeFormat().resolvedOptions().timeZone.replace('_', ' ');

    const open = (o: EventOccurrence) => {
        setEditing(false);
        setSelected(o);
    };

    return (
        <CommunityLayout community={community}>
            <Head title={`Calendar · ${community.name}`} />

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
                <div className="bg-card min-w-0 rounded-xl border shadow-xs">
                    <div className="flex items-center gap-2 border-b p-4">
                        <h1 className="text-lg font-semibold">{title}</h1>
                        <div className="ml-2 flex">
                            <Button variant="ghost" size="icon" asChild>
                                <Link
                                    href={route('calendar.index', { community: community.slug, month: shiftMonth(month, -1) })}
                                    preserveScroll
                                    aria-label="Previous month"
                                >
                                    <ChevronLeft />
                                </Link>
                            </Button>
                            <Button variant="ghost" size="icon" asChild>
                                <Link
                                    href={route('calendar.index', { community: community.slug, month: shiftMonth(month, 1) })}
                                    preserveScroll
                                    aria-label="Next month"
                                >
                                    <ChevronRight />
                                </Link>
                            </Button>
                        </div>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route('calendar.index', community.slug)}>Today</Link>
                        </Button>
                        {community.can.manage && (
                            <Button size="sm" className="ml-auto" onClick={() => setCreatingOn(today)}>
                                <Plus /> New event
                            </Button>
                        )}
                    </div>

                    <div className="text-muted-foreground grid grid-cols-7 border-b text-center text-xs font-medium">
                        {WEEKDAYS.map((d) => (
                            <div key={d} className="py-2">
                                {d}
                            </div>
                        ))}
                    </div>
                    <div className="grid grid-cols-7">
                        {days.map((day, i) => {
                            const key = dayKey(day);
                            const inMonth = day.getMonth() === monthIndex;
                            const events = byDay[key] ?? [];
                            return (
                                <div
                                    key={key}
                                    className={cn(
                                        'group relative min-h-20 border-b p-1 sm:min-h-28 sm:p-1.5',
                                        i % 7 !== 6 && 'border-r',
                                        !inMonth && 'bg-muted/40',
                                    )}
                                >
                                    <div className="flex items-center justify-between">
                                        <span
                                            className={cn(
                                                'flex size-6 items-center justify-center rounded-full text-xs',
                                                key === today && 'bg-brand text-brand-foreground font-semibold',
                                                !inMonth && 'text-muted-foreground',
                                            )}
                                        >
                                            {day.getDate()}
                                        </span>
                                        {community.can.manage && (
                                            <button
                                                onClick={() => setCreatingOn(key)}
                                                className="text-muted-foreground hover:text-foreground hidden size-5 items-center justify-center rounded group-hover:flex"
                                                aria-label={`Add event on ${key}`}
                                            >
                                                <Plus className="size-3.5" />
                                            </button>
                                        )}
                                    </div>
                                    <div className="mt-1 space-y-1">
                                        {events.map((o) => (
                                            <button
                                                key={o.key}
                                                onClick={() => open(o)}
                                                className="bg-brand/10 text-brand hover:bg-brand/20 block w-full truncate rounded px-1 py-0.5 text-left text-[11px] font-medium sm:px-1.5 sm:text-xs"
                                                title={o.title}
                                            >
                                                <span className="hidden xl:inline">{timeLabel(o.starts_at)} · </span>
                                                {o.title}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                    <p className="text-muted-foreground p-3 text-xs">Times shown in your time zone ({zone}).</p>
                </div>

                <aside className="bg-card h-fit rounded-xl border p-4 shadow-xs">
                    <h2 className="mb-3 text-sm font-semibold">Upcoming events</h2>
                    {upcoming.length === 0 && <p className="text-muted-foreground text-sm">Nothing scheduled yet.</p>}
                    <ul className="space-y-3">
                        {upcoming.map((o) => (
                            <li key={o.key}>
                                <button
                                    onClick={() => open(o)}
                                    className="hover:bg-accent -mx-2 flex w-[calc(100%+1rem)] gap-3 rounded-lg p-2 text-left"
                                >
                                    <div className="bg-brand/10 text-brand flex w-11 shrink-0 flex-col items-center rounded-md py-1">
                                        <span className="text-[10px] font-semibold uppercase">
                                            {new Date(o.starts_at).toLocaleDateString(undefined, { month: 'short' })}
                                        </span>
                                        <span className="text-lg leading-none font-bold">{new Date(o.starts_at).getDate()}</span>
                                    </div>
                                    <div className="min-w-0">
                                        <div className="truncate text-sm font-medium">{o.title}</div>
                                        <div className="text-muted-foreground text-xs">
                                            {new Date(o.starts_at).toLocaleDateString(undefined, { weekday: 'short' })} · {timeLabel(o.starts_at)}
                                        </div>
                                    </div>
                                </button>
                            </li>
                        ))}
                    </ul>
                </aside>
            </div>

            <Dialog open={!!selected} onOpenChange={(v) => !v && setSelected(null)}>
                <DialogContent className="sm:max-w-lg">
                    {selected &&
                        (editing ? (
                            <>
                                <DialogHeader>
                                    <DialogTitle>Edit event</DialogTitle>
                                    {selected.recurrence === 'weekly' && (
                                        <DialogDescription>Changes apply to every week of this event.</DialogDescription>
                                    )}
                                </DialogHeader>
                                <EventForm community={community} event={selected} onDone={() => setSelected(null)} />
                            </>
                        ) : (
                            <>
                                <DialogHeader>
                                    <DialogTitle className="text-xl">{selected.title}</DialogTitle>
                                </DialogHeader>
                                <div className="space-y-3 text-sm">
                                    <div className="flex items-center gap-2">
                                        <Clock className="text-muted-foreground size-4" />
                                        {dateLabel(selected.starts_at)}, {timeLabel(selected.starts_at)} – {timeLabel(selected.ends_at)}
                                    </div>
                                    {selected.recurrence === 'weekly' && (
                                        <div className="flex items-center gap-2">
                                            <Repeat className="text-muted-foreground size-4" />
                                            Every week{selected.repeat_until && ` until ${new Date(selected.repeat_until).toLocaleDateString()}`}
                                        </div>
                                    )}
                                    {selected.location ? (
                                        <div className="flex items-center gap-2">
                                            <Link2 className="text-muted-foreground size-4 shrink-0" />
                                            {/^https?:\/\//.test(selected.location) ? (
                                                <a
                                                    href={selected.location}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-brand truncate hover:underline"
                                                >
                                                    {selected.location}
                                                </a>
                                            ) : (
                                                <span>{selected.location}</span>
                                            )}
                                        </div>
                                    ) : (
                                        community.membership?.status !== 'active' && (
                                            <p className="text-muted-foreground">Join the community to see the meeting link.</p>
                                        )
                                    )}
                                    <div className="flex items-center gap-2">
                                        <UserAvatar user={selected.host} size="xs" />
                                        Hosted by {selected.host.name}
                                    </div>
                                    {selected.description && <RichText text={selected.description} className="border-t pt-3" />}
                                </div>
                                <div className="flex flex-wrap gap-2 border-t pt-4">
                                    <Button variant="outline" size="sm" asChild>
                                        <a href={googleCalendarUrl(selected)} target="_blank" rel="noopener noreferrer">
                                            <CalendarPlus /> Google Calendar
                                        </a>
                                    </Button>
                                    <Button variant="outline" size="sm" asChild>
                                        <a href={route('events.ics', [community.slug, selected.event_id])}>
                                            <Download /> Apple / Outlook (.ics)
                                        </a>
                                    </Button>
                                    {community.can.manage && (
                                        <Button size="sm" className="ml-auto" onClick={() => setEditing(true)}>
                                            Edit
                                        </Button>
                                    )}
                                </div>
                            </>
                        ))}
                </DialogContent>
            </Dialog>

            <Dialog open={creatingOn !== null} onOpenChange={(v) => !v && setCreatingOn(null)}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>New event</DialogTitle>
                    </DialogHeader>
                    {creatingOn && <EventForm community={community} defaultDate={creatingOn} onDone={() => setCreatingOn(null)} />}
                </DialogContent>
            </Dialog>
        </CommunityLayout>
    );
}
