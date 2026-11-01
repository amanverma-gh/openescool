import { CommentForm, CommentThread } from '@/components/community/comment-thread';
import { LikeButton } from '@/components/community/like-button';
import { PostEditor } from '@/components/community/post-editor';
import { findVideoEmbed, RichText, VideoEmbed } from '@/components/community/rich-text';
import { UserAvatar } from '@/components/community/user-avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import CommunityLayout from '@/layouts/community-layout';
import { plural, timeAgo } from '@/lib/utils';
import { type Category, type Comment, type Community, type Post, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Flag, MessageCircle, MoreHorizontal, Pencil, Pin, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface ShowProps {
    community: Community;
    categories: Category[];
    post: Post;
    comments: Comment[];
    canComment: boolean;
}

export default function Show({ community, categories, post, comments, canComment }: ShowProps) {
    const { auth } = usePage<SharedData>().props;
    const [editing, setEditing] = useState(false);
    const video = findVideoEmbed(post.body);

    const destroy = () => {
        if (confirm('Delete this post and all its comments?')) {
            router.delete(route('posts.destroy', [community.slug, post.id]));
        }
    };

    return (
        <CommunityLayout community={community}>
            <Head title={post.title} />

            <div className="mx-auto max-w-3xl">
                <Link
                    href={route('communities.feed', community.slug)}
                    className="text-muted-foreground hover:text-foreground mb-4 inline-flex items-center gap-1 text-sm"
                >
                    <ArrowLeft className="size-4" /> Back to feed
                </Link>

                <article className="bg-card rounded-xl border p-6 shadow-xs">
                    {editing ? (
                        <PostEditor community={community} categories={categories} post={post} onDone={() => setEditing(false)} autoFocus />
                    ) : (
                        <>
                            <header className="mb-4 flex items-center gap-3">
                                <UserAvatar user={post.author} size="md" showLevel />
                                <div className="text-sm">
                                    <Link href={route('users.show', post.author.username)} className="font-semibold hover:underline">
                                        {post.author.name}
                                    </Link>
                                    <div className="text-muted-foreground text-xs">
                                        {timeAgo(post.created_at)}
                                        {post.category && ` · ${post.category.emoji ?? ''} ${post.category.name}`}
                                    </div>
                                </div>
                                <div className="ml-auto flex items-center gap-2">
                                    {post.is_pinned && <Pin className="text-muted-foreground size-4" aria-label="Pinned" />}
                                    {(post.can.update || post.can.delete || post.can.pin || (auth.user && auth.user.id !== post.author.id)) && (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="icon" aria-label="Post options">
                                                    <MoreHorizontal className="size-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                {post.can.update && (
                                                    <DropdownMenuItem onSelect={() => setEditing(true)}>
                                                        <Pencil className="mr-2" /> Edit
                                                    </DropdownMenuItem>
                                                )}
                                                {post.can.pin && (
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            router.post(route('posts.pin', [community.slug, post.id]), {}, { preserveScroll: true })
                                                        }
                                                    >
                                                        <Pin className="mr-2" /> {post.is_pinned ? 'Unpin' : 'Pin to feed'}
                                                    </DropdownMenuItem>
                                                )}
                                                {auth.user && auth.user.id !== post.author.id && (
                                                    <DropdownMenuItem
                                                        onSelect={() => router.post(route('report', ['post', post.id]), {}, { preserveScroll: true })}
                                                    >
                                                        <Flag className="mr-2" /> Report spam
                                                    </DropdownMenuItem>
                                                )}
                                                {post.can.delete && (
                                                    <DropdownMenuItem onSelect={destroy} className="text-red-600">
                                                        <Trash2 className="mr-2" /> Delete
                                                    </DropdownMenuItem>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    )}
                                </div>
                            </header>

                            <h1 className="mb-3 text-2xl font-bold tracking-tight">{post.title}</h1>
                            <RichText text={post.body} className="text-[15px] leading-relaxed" />
                            {video && (
                                <div className="mt-4">
                                    <VideoEmbed src={video} title={post.title} />
                                </div>
                            )}
                            {post.attachments.length > 0 && (
                                <div className={`mt-4 grid gap-2 ${post.attachments.length > 1 ? 'grid-cols-2' : ''}`}>
                                    {post.attachments.map((a) => (
                                        <a
                                            key={a.path}
                                            href={a.url}
                                            target="_blank"
                                            rel="noopener"
                                            className="bg-muted block overflow-hidden rounded-lg border"
                                        >
                                            <img src={a.url} alt="" loading="lazy" className="max-h-[480px] w-full object-contain" />
                                        </a>
                                    ))}
                                </div>
                            )}

                            <footer className="mt-6 flex items-center gap-5 border-t pt-4 text-sm">
                                <LikeButton
                                    url={route('posts.like', [community.slug, post.id])}
                                    liked={post.liked}
                                    count={post.likes_count}
                                    disabled={!community.can.participate || auth.user?.id === post.author.id}
                                />
                                <span className="text-muted-foreground inline-flex items-center gap-1.5">
                                    <MessageCircle className="size-4" /> {plural(post.comments_count, 'comment')}
                                </span>
                            </footer>
                        </>
                    )}
                </article>

                <section className="mt-6 space-y-6">
                    {canComment && <CommentForm community={community} postId={post.id} />}
                    {post.comments_disabled && <p className="text-muted-foreground text-center text-sm">Comments are turned off for this post.</p>}
                    {!auth.user && (
                        <p className="text-muted-foreground text-center text-sm">
                            <Link href={route('login')} className="text-brand font-medium hover:underline">
                                Log in
                            </Link>{' '}
                            to join the conversation.
                        </p>
                    )}
                    <CommentThread comments={comments} community={community} postId={post.id} canComment={canComment} />
                </section>
            </div>
        </CommunityLayout>
    );
}
