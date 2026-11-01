import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type Category, type Community, type Post } from '@/types';
import { useForm } from '@inertiajs/react';
import { ImageIcon, LoaderCircle, X } from 'lucide-react';
import { FormEventHandler, useEffect, useMemo, useRef, useState } from 'react';

// Keep in sync with Post::MAX_ATTACHMENTS / MAX_ATTACHMENT_KB.
const MAX_FILES = 4;
const MAX_BYTES = 1024 * 1024;

interface PostEditorProps {
    community: Community;
    categories: Category[];
    post?: Post;
    onDone: () => void;
    autoFocus?: boolean;
}

/**
 * Create or edit a post. Admin-only categories are hidden from regular members.
 * Photos and GIFs show up only when the community owner allows them.
 */
export function PostEditor({ community, categories, post, onDone, autoFocus }: PostEditorProps) {
    const available = categories.filter((c) => !c.admin_only || community.can.moderate || c.id === post?.category?.id);
    const photoInput = useRef<HTMLInputElement>(null);
    const gifInput = useRef<HTMLInputElement>(null);
    const [fileError, setFileError] = useState<string | null>(null);

    const {
        data,
        setData,
        post: submitPost,
        transform,
        processing,
        errors,
        reset,
    } = useForm({
        title: post?.title ?? '',
        body: post?.body ?? '',
        category_id: post?.category?.id ?? available[0]?.id ?? null,
        attachments: [] as File[],
        keep_attachments: (post?.attachments ?? []).map((a) => a.path),
    });

    const previews = useMemo(() => data.attachments.map((file) => URL.createObjectURL(file)), [data.attachments]);
    useEffect(() => () => previews.forEach((url) => URL.revokeObjectURL(url)), [previews]);

    const kept = (post?.attachments ?? []).filter((a) => data.keep_attachments.includes(a.path));
    const total = kept.length + data.attachments.length;

    const addFiles = (files: FileList | null) => {
        setFileError(null);
        const picked = Array.from(files ?? []);
        const tooBig = picked.filter((f) => f.size > MAX_BYTES);
        const ok = picked.filter((f) => f.size <= MAX_BYTES).slice(0, MAX_FILES - total);

        if (tooBig.length) setFileError(`${tooBig.map((f) => f.name).join(', ')}: each file must be under 1 MB.`);
        else if (ok.length < picked.length) setFileError(`You can attach up to ${MAX_FILES} files.`);

        setData('attachments', [...data.attachments, ...ok]);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                reset();
                onDone();
            },
        };

        if (post) {
            // Files need multipart, which PHP only parses on POST, so spoof the PATCH.
            transform((d) => ({ ...d, _method: 'patch' }));
            submitPost(route('posts.update', [community.slug, post.id]), options);
        } else {
            submitPost(route('posts.store', community.slug), options);
        }
    };

    const attachmentErrors = Object.entries(errors)
        .filter(([key]) => key.startsWith('attachments'))
        .map(([, message]) => message);

    return (
        <form onSubmit={submit} className="space-y-3">
            <div>
                <input
                    value={data.title}
                    onChange={(e) => setData('title', e.target.value)}
                    placeholder="Title"
                    className="placeholder:text-muted-foreground focus:bg-muted/40 h-11 w-full rounded-lg px-3 text-lg font-semibold outline-none"
                    autoFocus={autoFocus}
                    maxLength={200}
                    required
                />
                <InputError message={errors.title} className="px-3" />
            </div>
            <div>
                <textarea
                    value={data.body}
                    onChange={(e) => setData('body', e.target.value)}
                    placeholder="Write something… Paste a YouTube, Vimeo or Loom link to embed a video. Use @username to mention someone."
                    className="placeholder:text-muted-foreground focus:bg-muted/40 min-h-36 w-full resize-y rounded-lg px-3 py-2 text-sm outline-none"
                    required
                />
                <InputError message={errors.body} className="px-3" />
            </div>

            {total > 0 && (
                <div className="grid grid-cols-4 gap-2 px-3">
                    {kept.map((a) => (
                        <Thumb
                            key={a.path}
                            src={a.url}
                            gif={a.gif}
                            onRemove={() =>
                                setData(
                                    'keep_attachments',
                                    data.keep_attachments.filter((p) => p !== a.path),
                                )
                            }
                        />
                    ))}
                    {data.attachments.map((file, i) => (
                        <Thumb
                            key={`${file.name}-${i}`}
                            src={previews[i]}
                            gif={file.type === 'image/gif'}
                            onRemove={() =>
                                setData(
                                    'attachments',
                                    data.attachments.filter((_, j) => j !== i),
                                )
                            }
                        />
                    ))}
                </div>
            )}
            {(fileError || attachmentErrors.length > 0) && <InputError message={fileError ?? attachmentErrors[0]} className="px-3" />}

            <div className="flex flex-wrap items-center gap-2 border-t pt-3">
                {community.allow_images && (
                    <>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            title="Add photo (under 1 MB)"
                            aria-label="Add photo"
                            disabled={total >= MAX_FILES}
                            onClick={() => photoInput.current?.click()}
                        >
                            <ImageIcon className="size-5" />
                        </Button>
                        <input
                            ref={photoInput}
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            hidden
                            onChange={(e) => {
                                addFiles(e.target.files);
                                e.target.value = '';
                            }}
                        />
                    </>
                )}
                {community.allow_gifs && (
                    <>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-9 px-2 text-xs font-bold"
                            title="Add GIF (under 1 MB)"
                            disabled={total >= MAX_FILES}
                            onClick={() => gifInput.current?.click()}
                        >
                            <span className="rounded border-2 border-current px-1 leading-4">GIF</span>
                        </Button>
                        <input
                            ref={gifInput}
                            type="file"
                            accept="image/gif"
                            multiple
                            hidden
                            onChange={(e) => {
                                addFiles(e.target.files);
                                e.target.value = '';
                            }}
                        />
                    </>
                )}
                {available.length > 0 && (
                    <Select value={data.category_id ? String(data.category_id) : undefined} onValueChange={(v) => setData('category_id', Number(v))}>
                        <SelectTrigger className="h-9 w-48">
                            <SelectValue placeholder="Select a category" />
                        </SelectTrigger>
                        <SelectContent>
                            {available.map((c) => (
                                <SelectItem key={c.id} value={String(c.id)}>
                                    {c.emoji} {c.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}
                <InputError message={errors.category_id} />
                <div className="ml-auto flex gap-2">
                    <Button type="button" variant="ghost" onClick={onDone}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={processing || !data.title.trim() || !data.body.trim()}>
                        {processing && <LoaderCircle className="size-4 animate-spin" />}
                        {post ? 'Save' : 'Post'}
                    </Button>
                </div>
            </div>
        </form>
    );
}

function Thumb({ src, gif, onRemove }: { src: string; gif: boolean; onRemove: () => void }) {
    return (
        <div className="bg-muted relative aspect-square overflow-hidden rounded-lg border">
            <img src={src} alt="" className="size-full object-cover" />
            {gif && <span className="absolute bottom-1 left-1 rounded bg-black/70 px-1 text-[10px] font-bold text-white">GIF</span>}
            <button
                type="button"
                onClick={onRemove}
                aria-label="Remove"
                className="absolute top-1 right-1 rounded-full bg-black/70 p-0.5 text-white hover:bg-black"
            >
                <X className="size-3.5" />
            </button>
        </div>
    );
}
