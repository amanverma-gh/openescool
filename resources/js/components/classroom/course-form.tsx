import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { type Community, type CourseCard } from '@/types';
import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

/**
 * Create or edit a course's details (title, access rules, cover, publish state).
 */
export function CourseForm({ community, course, onDone }: { community: Community; course?: CourseCard; onDone: () => void }) {
    const { data, setData, post, processing, errors } = useForm({
        title: course?.title ?? '',
        description: course?.description ?? '',
        access: course?.access ?? ('open' as 'open' | 'level'),
        unlock_level: course?.unlock_level ?? 2,
        is_published: course?.is_published ?? false,
        cover: null as File | null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const url = course ? route('courses.update', [community.slug, course.id]) : route('courses.store', community.slug);
        post(url, { forceFormData: true, preserveScroll: true, onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="course-title">Course title</Label>
                <Input id="course-title" value={data.title} onChange={(e) => setData('title', e.target.value)} maxLength={120} required autoFocus />
                <InputError message={errors.title} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="course-description">Description</Label>
                <Textarea
                    id="course-description"
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    maxLength={1000}
                    rows={3}
                />
                <InputError message={errors.description} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="course-cover">Cover image (16:9 works best)</Label>
                <Input id="course-cover" type="file" accept="image/*" onChange={(e) => setData('cover', e.target.files?.[0] ?? null)} />
                <InputError message={errors.cover} />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label>Access</Label>
                    <Select value={data.access} onValueChange={(v) => setData('access', v as 'open' | 'level')}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="open">All members</SelectItem>
                            <SelectItem value="level">Unlock at a level</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                {data.access === 'level' && (
                    <div className="grid gap-2">
                        <Label>Unlock at level</Label>
                        <Select value={String(data.unlock_level)} onValueChange={(v) => setData('unlock_level', Number(v))}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {[2, 3, 4, 5, 6, 7, 8, 9].map((level) => (
                                    <SelectItem key={level} value={String(level)}>
                                        Level {level}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.unlock_level} />
                    </div>
                )}
            </div>
            <label className="flex items-center gap-2 text-sm">
                <Checkbox checked={data.is_published} onCheckedChange={(v) => setData('is_published', v === true)} />
                Published (members can see it)
            </label>
            <div className="flex justify-end gap-2">
                <Button type="button" variant="ghost" onClick={onDone}>
                    Cancel
                </Button>
                <Button disabled={processing}>
                    {processing && <LoaderCircle className="size-4 animate-spin" />}
                    {course ? 'Save course' : 'Create course'}
                </Button>
            </div>
        </form>
    );
}
