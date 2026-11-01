import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatFileSize } from '@/lib/utils';
import { type Community, type Lesson, type LessonResource } from '@/types';
import { router, useForm } from '@inertiajs/react';
import { FileText, Link2, LoaderCircle, Paperclip, Plus, Trash2, Upload, Video, X } from 'lucide-react';
import { FormEventHandler, useRef, useState } from 'react';

const RESOURCE_ACCEPT = '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.jpg,.jpeg,.png,.webp,.mp3';

/** A resource row: a link, a file already on the lesson (`path`), or a new upload (`file`). */
type EditorResource = LessonResource & { file?: File | null };

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
    const { resource_kb: resourceKb, video_kb: videoKb } = community.media;
    const { data, setData, post, transform, processing, progress, errors } = useForm({
        title: lesson?.title ?? '',
        video_url: lesson?.video_url ?? '',
        video_file: null as File | null,
        remove_video: false,
        body: lesson?.body ?? '',
        resources: (lesson?.resources ?? []) as EditorResource[],
        is_published: lesson?.is_published ?? true,
    });
    const [fileError, setFileError] = useState<string | null>(null);
    const resourceInput = useRef<HTMLInputElement>(null);
    const videoInput = useRef<HTMLInputElement>(null);
    const replaceIndex = useRef<number | null>(null);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        // Send each resource as a link, a kept file (path) or a new upload; never the download URL.
        transform((d) => ({
            ...d,
            ...(lesson ? { _method: 'patch' } : {}),
            resources: d.resources.map((r) =>
                r.file ? { label: r.label, file: r.file } : r.path ? { label: r.label, path: r.path } : { label: r.label, url: r.url },
            ),
        }));
        const options = { preserveScroll: true, forceFormData: true, onSuccess: onDone };
        post(lesson ? route('lessons.update', [community.slug, lesson.id]) : route('lessons.store', [community.slug, moduleId]), options);
    };

    const setResource = (index: number, changes: Partial<EditorResource>) =>
        setData(
            'resources',
            data.resources.map((r, i) => (i === index ? { ...r, ...changes } : r)),
        );

    const pickResourceFile = (file: File | undefined) => {
        if (!file) return;
        if (file.size > resourceKb * 1024) {
            setFileError(`${file.name}: files must be under ${formatFileSize(resourceKb)}.`);
            return;
        }
        setFileError(null);
        const upload = { file, name: file.name, size: Math.ceil(file.size / 1024), path: undefined, url: '' };
        const index = replaceIndex.current;
        if (index === null) {
            setData('resources', [...data.resources, { label: file.name.replace(/\.[^.]+$/, ''), ...upload }]);
        } else {
            setResource(index, upload);
        }
    };

    const pickVideo = (file: File | undefined) => {
        if (!file) return;
        if (file.size > videoKb * 1024) {
            setFileError(`${file.name}: videos must be under ${formatFileSize(videoKb)}.`);
            return;
        }
        setFileError(null);
        setData((d) => ({ ...d, video_file: file, remove_video: false }));
    };

    const errorFor = (key: string) => (errors as Record<string, string>)[key];
    const resourceError = data.resources
        .map((_, i) => errorFor(`resources.${i}.file`) ?? errorFor(`resources.${i}.url`) ?? errorFor(`resources.${i}.label`))
        .find(Boolean);
    const hasUploadedVideo = !!lesson?.video_file_url && !data.remove_video;

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="lesson-title">Lesson title</Label>
                <Input id="lesson-title" value={data.title} onChange={(e) => setData('title', e.target.value)} maxLength={160} required autoFocus />
                <InputError message={errors.title} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="lesson-video">Video</Label>
                {data.video_file || hasUploadedVideo ? (
                    <div className="bg-muted/50 flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                        <Video className="text-muted-foreground size-4 shrink-0" />
                        <span className="min-w-0 flex-1 truncate">
                            {data.video_file
                                ? `${data.video_file.name} (${formatFileSize(Math.ceil(data.video_file.size / 1024))})`
                                : 'Uploaded video'}
                        </span>
                        <Button type="button" variant="ghost" size="sm" onClick={() => videoInput.current?.click()}>
                            Replace
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Remove video"
                            onClick={() => setData((d) => ({ ...d, video_file: null, remove_video: !!lesson?.video_file_url }))}
                        >
                            <X className="size-4" />
                        </Button>
                    </div>
                ) : (
                    <div className="flex gap-2">
                        <Input
                            id="lesson-video"
                            type="url"
                            value={data.video_url}
                            onChange={(e) => setData('video_url', e.target.value)}
                            placeholder="https://youtube.com/… , vimeo.com/… or loom.com/share/…"
                        />
                        <Button type="button" variant="outline" onClick={() => videoInput.current?.click()} className="shrink-0">
                            <Upload /> Upload video
                        </Button>
                    </div>
                )}
                <p className="text-muted-foreground text-xs">Paste a link, or upload an MP4 or WebM under {formatFileSize(videoKb)}.</p>
                <input
                    ref={videoInput}
                    type="file"
                    accept=".mp4,.webm,video/mp4,video/webm"
                    className="hidden"
                    onChange={(e) => {
                        pickVideo(e.target.files?.[0]);
                        e.target.value = '';
                    }}
                />
                <InputError message={errors.video_url ?? errors.video_file} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="lesson-body">Lesson text</Label>
                <Textarea id="lesson-body" value={data.body} onChange={(e) => setData('body', e.target.value)} rows={10} />
                <InputError message={errors.body} />
            </div>

            <div className="grid gap-2">
                <Label>Resources (files and links)</Label>
                {data.resources.map((resource, i) => (
                    <div key={i} className="flex gap-2">
                        <Input
                            value={resource.label}
                            onChange={(e) => setResource(i, { label: e.target.value })}
                            placeholder="Label"
                            className="w-1/3"
                            required
                        />
                        {resource.file || resource.path ? (
                            <div className="bg-muted/50 flex min-w-0 flex-1 items-center gap-2 rounded-md border px-3 text-sm">
                                <FileText className="text-muted-foreground size-4 shrink-0" />
                                <span className="truncate">{resource.name ?? 'File'}</span>
                                {resource.size ? (
                                    <span className="text-muted-foreground shrink-0 text-xs">{formatFileSize(resource.size)}</span>
                                ) : null}
                            </div>
                        ) : (
                            <Input
                                value={resource.url}
                                onChange={(e) => setResource(i, { url: e.target.value })}
                                placeholder="https://…"
                                type="url"
                            />
                        )}
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            title={resource.file || resource.path ? 'Replace file' : 'Upload a file instead'}
                            aria-label="Upload file"
                            onClick={() => {
                                replaceIndex.current = i;
                                resourceInput.current?.click();
                            }}
                        >
                            <Paperclip className="size-4" />
                        </Button>
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
                <InputError message={resourceError} />
                {data.resources.length < 10 && (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                replaceIndex.current = null;
                                resourceInput.current?.click();
                            }}
                        >
                            <Upload /> Upload file
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setData('resources', [...data.resources, { label: '', url: '' }])}
                        >
                            <Link2 /> Add link
                        </Button>
                    </div>
                )}
                <p className="text-muted-foreground text-xs">
                    PDF, Word, Excel, PowerPoint, TXT, CSV, ZIP, images or MP3, each under {formatFileSize(resourceKb)}.
                </p>
                <input
                    ref={resourceInput}
                    type="file"
                    accept={RESOURCE_ACCEPT}
                    className="hidden"
                    onChange={(e) => {
                        pickResourceFile(e.target.files?.[0]);
                        e.target.value = '';
                    }}
                />
            </div>
            <InputError message={fileError ?? undefined} />

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
                <div className="ml-auto flex items-center gap-2">
                    {processing && progress?.percentage !== undefined && (
                        <span className="text-muted-foreground text-xs">Uploading {progress.percentage}%</span>
                    )}
                    <Button type="button" variant="ghost" onClick={onDone}>
                        Cancel
                    </Button>
                    <Button disabled={processing}>
                        {processing ? <LoaderCircle className="size-4 animate-spin" /> : lesson ? null : <Plus />}
                        {lesson ? 'Save lesson' : 'Add lesson'}
                    </Button>
                </div>
            </div>
        </form>
    );
}
