import { GifPicker, giphyUrl } from '@/components/community/gif-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { formatFileSize } from '@/lib/utils';
import { type Category, type Community, type Post } from '@/types';
import { useForm } from '@inertiajs/react';
import { ImageIcon, LoaderCircle, X } from 'lucide-react';
import { FormEventHandler, useEffect, useMemo, useRef, useState } from 'react';

interface PostEditorProps {
    community: Community;
    categories: Category[];
    post?: Post;
    onDone: () => void;
    autoFocus?: boolean;
}

/**
 * Create or edit a post. Admin-only categories are hidden from regular members.
 * Photos and GIPHY GIFs show up only when the community owner allows them,
 * within the owner's limits (how many per post, max photo size).
 */
export function PostEditor({ community, categories, post, onDone, autoFocus }: PostEditorProps) {
    const available = categories.filter((c) => !c.admin_only || community.can.moderate || c.id === post?.category?.id);
    const { images, gifs, max_images: maxItems, max_kb: maxKb } = community.media;
    const photoInput = useRef<HTMLInputElement>(null);
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
        gif_id: '',
        keep_attachments: (post?.attachments ?? []).map((a) => a.path),
    });

    const previews = useMemo(() => data.attachments.map((file) => URL.createObjectURL(file)), [data.attachments]);
    useEffect(() => () => previews.forEach((url) => URL.revokeObjectURL(url)), [previews]);

    const kept = (post?.attachments ?? []).filter((a) => data.keep_attachments.includes(a.path));
    const hasGif = !!data.gif_id || kept.some((a) => a.gif);
    const total = kept.length + data.attachments.length + (data.gif_id ? 1 : 0);
    const full = total >= maxItems;

    const addFiles = (files: FileList | null) => {
        setFileError(null);
        const picked = Array.from(files ?? []);
        const tooBig = picked.filter((f) => f.size > maxKb * 1024);
        const ok = picked.filter((f) => f.size <= maxKb * 1024).slice(0, maxItems - total);

        if (tooBig.length) setFileError(`${tooBig.map((f) => f.name).join(', ')}: each photo must be under ${formatFileSize(maxKb)}.`);
        else if (ok.length < picked.length) setFileError(`You can attach up to ${maxItems} ${maxItems === 1 ? 'item' : 'items'}.`);

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

    const attachmentError = fileError ?? Object.entries(errors).find(([key]) => key.startsWith('attachments') || key === 'gif_id')?.[1];

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
                        <MediaThumb
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
                        <MediaThumb
                            key={`${file.name}-${i}`}
                            src={previews[i]}
                            onRemove={() =>
                                setData(
                                    'attachments',
                                    data.attachments.filter((_, j) => j !== i),
                                )
                            }
                        />
                    ))}
                    {data.gif_id && <MediaThumb src={giphyUrl(data.gif_id)} gif onRemove={() => setData('gif_id', '')} />}
                </div>
            )}
            {attachmentError && <InputError message={attachmentError} className="px-3" />}

            <div className="flex flex-wrap items-center gap-2 border-t pt-3">
                {images && (
                    <>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            title={`Add photo (under ${formatFileSize(maxKb)})`}
                            aria-label="Add photo"
                            disabled={full}
                            onClick={() => photoInput.current?.click()}
                        >
                            <ImageIcon className="size-5" />
                        </Button>
                        <input
                            ref={photoInput}
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            multiple={maxItems > 1}
                            hidden
                            onChange={(e) => {
                                addFiles(e.target.files);
                                e.target.value = '';
                            }}
                        />
                    </>
                )}
                {/* One GIF per post. */}
                {gifs && <GifPicker disabled={full || hasGif} onPick={(gif) => setData('gif_id', gif.id)} />}
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

export function MediaThumb({
    src,
    gif = false,
    onRemove,
    className = 'aspect-square',
}: {
    src: string;
    gif?: boolean;
    onRemove: () => void;
    className?: string;
}) {
    return (
        <div className={`bg-muted relative overflow-hidden rounded-lg border ${className}`}>
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
