import { GifPicker, giphyUrl } from '@/components/community/gif-picker';
import { LikeButton } from '@/components/community/like-button';
import { MediaThumb } from '@/components/community/post-editor';
import { RichText } from '@/components/community/rich-text';
import { UserAvatar } from '@/components/community/user-avatar';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { formatFileSize, timeAgo } from '@/lib/utils';
import { type Comment, type Community, type SharedData } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Flag, ImageIcon, LoaderCircle, Trash2 } from 'lucide-react';
import { FormEventHandler, useEffect, useMemo, useRef, useState } from 'react';

interface ThreadContext {
    community: Community;
    postId: number;
    canComment: boolean;
}

export function CommentForm({
    community,
    postId,
    parentId,
    initial = '',
    onDone,
    autoFocus,
}: {
    community: Community;
    postId: number;
    parentId?: number;
    initial?: string;
    onDone?: () => void;
    autoFocus?: boolean;
}) {
    const { auth } = usePage<SharedData>().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        body: initial,
        parent_id: parentId ?? null,
        image: null as File | null,
        gif_id: '',
    });
    const photoInput = useRef<HTMLInputElement>(null);
    const [fileError, setFileError] = useState<string | null>(null);
    const media = community.media;
    const canPhoto = media.comments && media.images;
    const canGif = media.comments && media.gifs;
    const preview = useMemo(() => (data.image ? URL.createObjectURL(data.image) : null), [data.image]);
    useEffect(() => () => void (preview && URL.revokeObjectURL(preview)), [preview]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('comments.store', [community.slug, postId]), {
            preserveScroll: true,
            forceFormData: !!data.image,
            onSuccess: () => {
                reset('body', 'image', 'gif_id');
                onDone?.();
            },
        });
    };

    const hasMedia = !!data.image || !!data.gif_id;
    const mediaError = fileError ?? errors.image ?? errors.gif_id;

    return (
        <form onSubmit={submit} className="flex items-start gap-3">
            <UserAvatar user={auth.user as never} size="sm" link={false} />
            <div className="flex-1 space-y-2">
                <Textarea
                    value={data.body}
                    onChange={(e) => setData('body', e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) submit(e);
                    }}
                    placeholder={parentId ? 'Write a reply…' : 'Your comment…'}
                    className="bg-card min-h-11"
                    rows={data.body ? 3 : 1}
                    autoFocus={autoFocus}
                />
                {errors.body && <p className="text-sm text-red-600">{errors.body}</p>}
                {(preview || data.gif_id) && (
                    <MediaThumb
                        src={preview ?? giphyUrl(data.gif_id)}
                        gif={!!data.gif_id}
                        className="size-20"
                        onRemove={() => {
                            setData((d) => ({ ...d, image: null, gif_id: '' }));
                        }}
                    />
                )}
                {mediaError && <p className="text-sm text-red-600">{mediaError}</p>}
                {(data.body.trim() || canPhoto || canGif) && (
                    <div className="flex items-center justify-end gap-2">
                        {/* One photo or one GIF per comment. */}
                        <div className="mr-auto flex items-center">
                            {canPhoto && (
                                <>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-8"
                                        title={`Add photo (under ${formatFileSize(media.max_kb)})`}
                                        aria-label="Add photo"
                                        disabled={hasMedia}
                                        onClick={() => photoInput.current?.click()}
                                    >
                                        <ImageIcon className="size-4" />
                                    </Button>
                                    <input
                                        ref={photoInput}
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        hidden
                                        onChange={(e) => {
                                            const file = e.target.files?.[0];
                                            e.target.value = '';
                                            if (!file) return;
                                            if (file.size > media.max_kb * 1024) {
                                                setFileError(`Photos must be under ${formatFileSize(media.max_kb)}.`);
                                                return;
                                            }
                                            setFileError(null);
                                            setData('image', file);
                                        }}
                                    />
                                </>
                            )}
                            {canGif && <GifPicker compact disabled={hasMedia} onPick={(gif) => setData('gif_id', gif.id)} />}
                        </div>
                        {onDone && (
                            <Button type="button" variant="ghost" size="sm" onClick={onDone}>
                                Cancel
                            </Button>
                        )}
                        <Button size="sm" disabled={processing || !data.body.trim()}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            {parentId ? 'Reply' : 'Comment'}
                        </Button>
                    </div>
                )}
            </div>
        </form>
    );
}

function CommentItem({ comment, ctx, isReply = false }: { comment: Comment; ctx: ThreadContext; isReply?: boolean }) {
    const { auth } = usePage<SharedData>().props;
    const [replying, setReplying] = useState(false);
    const [editing, setEditing] = useState(false);
    const edit = useForm({ body: comment.body, remove_attachment: false });

    const remove = () => {
        if (confirm('Delete this comment?')) {
            router.delete(route('comments.destroy', [ctx.community.slug, comment.id]), { preserveScroll: true });
        }
    };

    return (
        <div id={`comment-${comment.id}`} className="scroll-mt-24">
            <div className="flex gap-3">
                <UserAvatar user={comment.author} size={isReply ? 'sm' : 'md'} showLevel />
                <div className="min-w-0 flex-1">
                    <div className="bg-muted/60 rounded-xl px-4 py-2.5">
                        <div className="mb-0.5 flex items-center gap-2 text-sm">
                            <Link href={route('users.show', comment.author.username)} className="font-semibold hover:underline">
                                {comment.author.name}
                            </Link>
                            <span className="text-muted-foreground text-xs">{timeAgo(comment.created_at)}</span>
                        </div>
                        {editing ? (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    edit.patch(route('comments.update', [ctx.community.slug, comment.id]), {
                                        preserveScroll: true,
                                        onSuccess: () => setEditing(false),
                                    });
                                }}
                                className="space-y-2 py-1"
                            >
                                <Textarea
                                    value={edit.data.body}
                                    onChange={(e) => edit.setData('body', e.target.value)}
                                    className="bg-card"
                                    autoFocus
                                />
                                {comment.attachment && !edit.data.remove_attachment && (
                                    <MediaThumb
                                        src={comment.attachment.url}
                                        gif={comment.attachment.gif}
                                        className="size-20"
                                        onRemove={() => edit.setData('remove_attachment', true)}
                                    />
                                )}
                                <div className="flex justify-end gap-2">
                                    <Button type="button" variant="ghost" size="sm" onClick={() => setEditing(false)}>
                                        Cancel
                                    </Button>
                                    <Button size="sm" disabled={edit.processing}>
                                        Save
                                    </Button>
                                </div>
                            </form>
                        ) : (
                            <>
                                <RichText text={comment.body} className="text-sm" />
                                {comment.attachment && (
                                    <a href={comment.attachment.url} target="_blank" rel="noopener" className="mt-2 block w-fit">
                                        <img src={comment.attachment.url} alt="" loading="lazy" className="max-h-60 max-w-full rounded-lg border" />
                                    </a>
                                )}
                            </>
                        )}
                    </div>
                    <div className="text-muted-foreground mt-1 flex items-center gap-4 px-2 text-xs">
                        <LikeButton
                            size="sm"
                            url={route('comments.like', [ctx.community.slug, comment.id])}
                            liked={comment.liked}
                            count={comment.likes_count}
                            disabled={!ctx.community.can.participate || auth.user?.id === comment.author.id}
                        />
                        {ctx.canComment && (
                            <button onClick={() => setReplying(true)} className="hover:text-foreground font-medium">
                                Reply
                            </button>
                        )}
                        {comment.can.update && (
                            <button onClick={() => setEditing(true)} className="hover:text-foreground">
                                Edit
                            </button>
                        )}
                        {comment.can.delete && (
                            <button onClick={remove} className="hover:text-red-600" title="Delete" aria-label="Delete comment">
                                <Trash2 className="size-3.5" />
                            </button>
                        )}
                        {auth.user && auth.user.id !== comment.author.id && (
                            <button
                                onClick={() => router.post(route('report', ['comment', comment.id]), {}, { preserveScroll: true })}
                                className="hover:text-red-600"
                                title="Report spam"
                                aria-label="Report comment as spam"
                            >
                                <Flag className="size-3.5" />
                            </button>
                        )}
                    </div>
                </div>
            </div>

            {(comment.replies?.length || replying) && (
                <div className="mt-3 ml-12 space-y-3">
                    {comment.replies?.map((reply) => <CommentItem key={reply.id} comment={reply} ctx={ctx} isReply />)}
                    {replying && (
                        <CommentForm
                            community={ctx.community}
                            postId={ctx.postId}
                            parentId={comment.parent_id ?? comment.id}
                            initial={isReply ? `@${comment.author.username} ` : ''}
                            onDone={() => setReplying(false)}
                            autoFocus
                        />
                    )}
                </div>
            )}
        </div>
    );
}

export function CommentThread({ comments, ...ctx }: ThreadContext & { comments: Comment[] }) {
    return (
        <div className="space-y-5">
            {comments.map((comment) => (
                <CommentItem key={comment.id} comment={comment} ctx={ctx} />
            ))}
        </div>
    );
}
