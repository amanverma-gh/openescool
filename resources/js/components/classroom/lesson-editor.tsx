import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { type Community, type Lesson, type LessonResource } from '@/types';
import { router, useForm } from '@inertiajs/react';
import { LoaderCircle, Plus, Trash2, X } from 'lucide-react';
import { FormEventHandler } from 'react';

/**
 * Edit a lesson's content. `moduleId` without `lesson` creates a new one.
 */
export function LessonEditor({
    community,
    lesson,
    moduleId,
    onDone,
}: {
    community: Community;
    lesson?: Lesson;
    moduleId?: number;
    onDone: () => void;
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        title: lesson?.title ?? '',
        video_url: lesson?.video_url ?? '',
        body: lesson?.body ?? '',
        resources: (lesson?.resources ?? []) as LessonResource[],
        is_published: lesson?.is_published ?? true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onDone };
        if (lesson) {
            patch(route('lessons.update', [community.slug, lesson.id]), options);
        } else {
            post(route('lessons.store', [community.slug, moduleId]), options);
        }
    };

    const setResource = (index: number, field: keyof LessonResource, value: string) =>
        setData(
            'resources',
            data.resources.map((r, i) => (i === index ? { ...r, [field]: value } : r)),
        );

    const errorFor = (key: string) => (errors as Record<string, string>)[key];

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="lesson-title">Lesson title</Label>
                <Input id="lesson-title" value={data.title} onChange={(e) => setData('title', e.target.value)} maxLength={160} required autoFocus />
                <InputError message={errors.title} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="lesson-video">Video link</Label>
                <Input
                    id="lesson-video"
                    type="url"
                    value={data.video_url}
                    onChange={(e) => setData('video_url', e.target.value)}
                    placeholder="https://youtube.com/… , vimeo.com/… or loom.com/share/…"
                />
                <InputError message={errors.video_url} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="lesson-body">Lesson text</Label>
                <Textarea id="lesson-body" value={data.body} onChange={(e) => setData('body', e.target.value)} rows={10} />
                <InputError message={errors.body} />
            </div>
            <div className="grid gap-2">
                <Label>Resources (links, downloads)</Label>
                {data.resources.map((resource, i) => (
                    <div key={i} className="flex gap-2">
                        <Input
                            value={resource.label}
                            onChange={(e) => setResource(i, 'label', e.target.value)}
                            placeholder="Label"
                            className="w-1/3"
                        />
                        <Input value={resource.url} onChange={(e) => setResource(i, 'url', e.target.value)} placeholder="https://…" type="url" />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() =>
                                setData(
                                    'resources',
                                    data.resources.filter((_, j) => j !== i),
                                )
                            }
                            aria-label="Remove resource"
                        >
                            <X className="size-4" />
                        </Button>
                    </div>
                ))}
                <InputError message={errorFor('resources.0.url') ?? errorFor('resources.0.label')} />
                {data.resources.length < 10 && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="w-fit"
                        onClick={() => setData('resources', [...data.resources, { label: '', url: '' }])}
                    >
                        <Plus /> Add resource
                    </Button>
                )}
            </div>
            <label className="flex items-center gap-2 text-sm">
                <Checkbox checked={data.is_published} onCheckedChange={(v) => setData('is_published', v === true)} />
                Published
            </label>
            <div className="flex items-center gap-2 border-t pt-4">
                {lesson && (
                    <Button
                        type="button"
                        variant="ghost"
                        className="text-red-600"
                        onClick={() => confirm('Delete this lesson?') && router.delete(route('lessons.destroy', [community.slug, lesson.id]))}
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
                        {lesson ? 'Save lesson' : 'Add lesson'}
                    </Button>
                </div>
            </div>
        </form>
    );
}
