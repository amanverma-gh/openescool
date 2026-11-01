import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { type Community, type EventOccurrence } from '@/types';
import { router, useForm } from '@inertiajs/react';
import { LoaderCircle, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

const browserZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

/** Date and time parts of an instant, as seen in a given timezone. */
function partsIn(iso: string, timeZone: string) {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-CA', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        })
            .formatToParts(new Date(iso))
            .map((p) => [p.type, p.value]),
    );
    return { date: `${parts.year}-${parts.month}-${parts.day}`, time: `${parts.hour}:${parts.minute}` };
}

const durations = [15, 30, 45, 60, 90, 120, 180, 240];

export function EventForm({
    community,
    event,
    defaultDate,
    onDone,
}: {
    community: Community;
    event?: EventOccurrence;
    defaultDate?: string;
    onDone: () => void;
}) {
    const zone = event?.timezone ?? browserZone;
    const start = event ? partsIn(event.first_starts_at, zone) : { date: defaultDate ?? partsIn(new Date().toISOString(), zone).date, time: '18:00' };

    const { data, setData, post, patch, processing, errors } = useForm({
        title: event?.title ?? '',
        description: event?.description ?? '',
        date: start.date,
        time: start.time,
        timezone: zone,
        duration_minutes: event?.duration_minutes ?? 60,
        recurrence: event?.recurrence ?? ('none' as 'none' | 'weekly'),
        repeat_until: event?.repeat_until ?? '',
        location: event?.location ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onDone };
        if (event) {
            patch(route('events.update', [community.slug, event.event_id]), options);
        } else {
            post(route('events.store', community.slug), options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="event-title">Title</Label>
                <Input
                    id="event-title"
                    value={data.title}
                    onChange={(e) => setData('title', e.target.value)}
                    placeholder="Weekly Q&A call"
                    maxLength={120}
                    required
                    autoFocus
                />
                <InputError message={errors.title} />
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor="event-date">Date</Label>
                    <Input id="event-date" type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} required />
                    <InputError message={errors.date} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="event-time">Start time</Label>
                    <Input id="event-time" type="time" value={data.time} onChange={(e) => setData('time', e.target.value)} required />
                    <InputError message={errors.time} />
                </div>
                <div className="col-span-2 grid gap-2 sm:col-span-1">
                    <Label>Duration</Label>
                    <Select value={String(data.duration_minutes)} onValueChange={(v) => setData('duration_minutes', Number(v))}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {durations.map((m) => (
                                <SelectItem key={m} value={String(m)}>
                                    {m < 60 ? `${m} min` : `${m / 60} hour${m === 60 ? '' : 's'}`}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <p className="text-muted-foreground -mt-2 text-xs">
                Time zone: {data.timezone.replace('_', ' ')}. Members see it in their own time zone.
            </p>
            <div className="grid grid-cols-2 gap-3">
                <div className="grid gap-2">
                    <Label>Repeat</Label>
                    <Select value={data.recurrence} onValueChange={(v) => setData('recurrence', v as 'none' | 'weekly')}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">Does not repeat</SelectItem>
                            <SelectItem value="weekly">Every week</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                {data.recurrence === 'weekly' && (
                    <div className="grid gap-2">
                        <Label htmlFor="event-until">Until (optional)</Label>
                        <Input id="event-until" type="date" value={data.repeat_until} onChange={(e) => setData('repeat_until', e.target.value)} />
                        <InputError message={errors.repeat_until} />
                    </div>
                )}
            </div>
            <div className="grid gap-2">
                <Label htmlFor="event-location">Meeting link or location</Label>
                <Input
                    id="event-location"
                    value={data.location}
                    onChange={(e) => setData('location', e.target.value)}
                    placeholder="https://zoom.us/j/… (only members can see this)"
                    maxLength={500}
                />
                <InputError message={errors.location} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="event-description">Description</Label>
                <Textarea id="event-description" value={data.description} onChange={(e) => setData('description', e.target.value)} rows={4} />
                <InputError message={errors.description} />
            </div>
            <div className="flex items-center gap-2 border-t pt-4">
                {event && (
                    <Button
                        type="button"
                        variant="ghost"
                        className="text-red-600"
                        onClick={() =>
                            confirm(event.recurrence === 'weekly' ? 'Delete this event and all its repeats?' : 'Delete this event?') &&
                            router.delete(route('events.destroy', [community.slug, event.event_id]), { preserveScroll: true, onSuccess: onDone })
                        }
                    >
                        <Trash2 /> Delete
                    </Button>
                )}
                <div className="ml-auto flex gap-2">
                    <Button type="button" variant="ghost" onClick={onDone}>
                        Cancel
                    </Button>
                    <Button disabled={processing}>
                        {processing && <LoaderCircle className="size-4 animate-spin" />}
                        {event ? 'Save event' : 'Schedule event'}
                    </Button>
                </div>
            </div>
        </form>
    );
}
