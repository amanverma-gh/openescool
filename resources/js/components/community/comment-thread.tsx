import { LikeButton } from '@/components/community/like-button';
import { RichText } from '@/components/community/rich-text';
import { UserAvatar } from '@/components/community/user-avatar';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { timeAgo } from '@/lib/utils';
import { type Comment, type Community, type SharedData } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

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
    const { data, setData, post, processing, errors, reset } = useForm({ body: initial, parent_id: parentId ?? null });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('comments.store', [community.slug, postId]), {
            preserveScroll: true,
            onSuccess: () => {
                reset('body');
                onDone?.();
            },
        });
    };

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
                {data.body.trim() && (
                    <div className="flex justify-end gap-2">
                        {onDone && (
                            <Button type="button" variant="ghost" size="sm" onClick={onDone}>
                                Cancel
                            </Button>
                        )}
                        <Button size="sm" disabled={processing}>
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
    const edit = useForm({ body: comment.body });

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
                            <RichText text={comment.body} className="text-sm" />
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
                            <button onClick={remove} className="hover:text-red-600">
                                Delete
                            </button>
                        )}
                        {auth.user && auth.user.id !== comment.author.id && (
                            <button
                                onClick={() => router.post(route('report', ['comment', comment.id]), {}, { preserveScroll: true })}
                                className="hover:text-red-600"
                            >
                                Report
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
