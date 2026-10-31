import { LikeButton } from '@/components/community/like-button';
import { findVideoEmbed } from '@/components/community/rich-text';
import { UserAvatar } from '@/components/community/user-avatar';
import { timeAgo } from '@/lib/utils';
import { type Community, type Post, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { MessageCircle, Pin, PlayCircle } from 'lucide-react';

export function PostCard({ post, community }: { post: Post; community: Community }) {
    const { auth } = usePage<SharedData>().props;
    const href = route('posts.show', [community.slug, post.id]);
    const video = findVideoEmbed(post.body);
    const thumbnail = video?.includes('youtube') ? `https://i.ytimg.com/vi/${video.split('/').pop()}/hqdefault.jpg` : null;

    return (
        <article className="bg-card group relative rounded-xl border p-5 shadow-xs transition-shadow hover:shadow-md">
            <Link href={href} className="absolute inset-0 rounded-xl" aria-label={post.title} prefetch="hover" />

            <header className="relative mb-3 flex items-center gap-3">
                <UserAvatar user={post.author} size="md" showLevel />
                <div className="min-w-0 text-sm">
                    <Link href={route('users.show', post.author.username)} className="font-semibold hover:underline">
                        {post.author.name}
                    </Link>
                    <div className="text-muted-foreground flex items-center gap-1 text-xs">
                        <span>{timeAgo(post.created_at)}</span>
                        {post.category && (
                            <>
                                <span>·</span>
                                <span>
                                    {post.category.emoji} {post.category.name}
                                </span>
                            </>
                        )}
                    </div>
                </div>
                {post.is_pinned && (
                    <span className="text-muted-foreground ml-auto inline-flex items-center gap-1 text-xs font-medium">
                        <Pin className="size-3.5" /> Pinned
                    </span>
                )}
            </header>

            <div className="flex gap-4">
                <div className="min-w-0 flex-1">
                    <h2 className="mb-1 text-lg leading-snug font-semibold">{post.title}</h2>
                    <p className="text-muted-foreground line-clamp-2 text-sm break-words whitespace-pre-line">{post.body}</p>
                </div>
                {thumbnail && (
                    <div className="relative hidden h-20 w-32 shrink-0 overflow-hidden rounded-lg sm:block">
                        <img src={thumbnail} alt="" className="size-full object-cover" loading="lazy" />
                        <PlayCircle className="absolute inset-0 m-auto size-8 text-white drop-shadow" />
                    </div>
                )}
            </div>

            <footer className="relative mt-4 flex items-center gap-5 text-sm">
                <LikeButton
                    url={route('posts.like', [community.slug, post.id])}
                    liked={post.liked}
                    count={post.likes_count}
                    disabled={!community.can.participate || auth.user?.id === post.author.id}
                />
                <span className="text-muted-foreground inline-flex items-center gap-1.5">
                    <MessageCircle className="size-4" />
                    {post.comments_count}
                </span>
                {post.recent_commenters.length > 0 && (
                    <div className="flex -space-x-2">
                        {post.recent_commenters.map((u) => (
                            <UserAvatar key={u.id} user={u} size="xs" link={false} className="ring-card rounded-full ring-2" />
                        ))}
                    </div>
                )}
                {post.comments_count > 0 && (
                    <span className="text-muted-foreground hidden text-xs sm:inline">Last activity {timeAgo(post.last_activity_at)}</span>
                )}
            </footer>
        </article>
    );
}
