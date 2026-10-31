import { CommunitySidebar } from '@/components/community/community-sidebar';
import { PostCard } from '@/components/community/post-card';
import { PostEditor } from '@/components/community/post-editor';
import { UserAvatar } from '@/components/community/user-avatar';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import CommunityLayout from '@/layouts/community-layout';
import { cn } from '@/lib/utils';
import { type Category, type Community, type Post, type PublicUser, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

interface FeedProps {
    community: Community;
    categories: Category[];
    filters: { category: string | null; sort: 'default' | 'new' | 'top' };
    pinned: Post[];
    posts: Post[];
    page: number;
    nextPage: number | null;
    leaderboard?: (PublicUser & { points: number; level: number })[];
}

export default function Feed({ community, categories, filters, pinned, posts, page, nextPage, leaderboard }: FeedProps) {
    const { auth } = usePage<SharedData>().props;
    const [composing, setComposing] = useState(false);
    const [list, setList] = useState<Post[]>(posts);
    const [loading, setLoading] = useState(false);

    // Page 1 replaces the list (filters changed, new post, etc.); later pages append.
    useEffect(() => {
        setList((current) => (page === 1 ? posts : [...current, ...posts.filter((p) => !current.some((c) => c.id === p.id))]));
    }, [posts, page]);

    const filterUrl = (changes: Partial<FeedProps['filters']>) => {
        const next = { ...filters, ...changes };
        return route('communities.feed', {
            community: community.slug,
            ...(next.category ? { category: next.category } : {}),
            ...(next.sort !== 'default' ? { sort: next.sort } : {}),
        });
    };

    const loadMore = () => {
        if (!nextPage) return;
        setLoading(true);
        router.reload({
            data: { page: nextPage },
            only: ['posts', 'page', 'nextPage'],
            preserveUrl: true,
            onFinish: () => setLoading(false),
        });
    };

    return (
        <CommunityLayout community={community}>
            <Head title={community.name} />

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
                <div className="min-w-0 space-y-4">
                    {community.can.participate && auth.user && (
                        <div className="bg-card rounded-xl border p-4 shadow-xs">
                            {composing ? (
                                <PostEditor community={community} categories={categories} onDone={() => setComposing(false)} autoFocus />
                            ) : (
                                <button onClick={() => setComposing(true)} className="flex w-full items-center gap-3 text-left">
                                    <UserAvatar user={auth.user as never} size="md" link={false} />
                                    <span className="bg-muted text-muted-foreground flex-1 rounded-lg px-4 py-2.5 text-sm">Write something…</span>
                                </button>
                            )}
                        </div>
                    )}

                    <div className="flex items-center gap-2">
                        <div className="no-scrollbar -mx-1 flex min-w-0 flex-1 gap-2 overflow-x-auto px-1">
                            <FilterChip href={filterUrl({ category: null })} active={!filters.category}>
                                All
                            </FilterChip>
                            {categories.map((c) => (
                                <FilterChip key={c.id} href={filterUrl({ category: c.slug })} active={filters.category === c.slug}>
                                    {c.emoji} {c.name}
                                </FilterChip>
                            ))}
                        </div>
                        <Select value={filters.sort} onValueChange={(sort) => router.get(filterUrl({ sort: sort as FeedProps['filters']['sort'] }))}>
                            <SelectTrigger className="bg-card h-9 w-32 shrink-0">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent align="end">
                                <SelectItem value="default">Activity</SelectItem>
                                <SelectItem value="new">Newest</SelectItem>
                                <SelectItem value="top">Top</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {pinned.map((post) => (
                        <PostCard key={post.id} post={post} community={community} />
                    ))}
                    {list.map((post) => (
                        <PostCard key={post.id} post={post} community={community} />
                    ))}

                    {list.length === 0 && pinned.length === 0 && (
                        <div className="bg-card text-muted-foreground rounded-xl border p-10 text-center text-sm">
                            No posts here yet. {community.can.participate && 'Be the first to start a conversation!'}
                        </div>
                    )}

                    {nextPage && (
                        <div className="flex justify-center pt-2">
                            <Button variant="outline" onClick={loadMore} disabled={loading}>
                                {loading && <LoaderCircle className="size-4 animate-spin" />}
                                Load more
                            </Button>
                        </div>
                    )}
                </div>

                <CommunitySidebar community={community} leaderboard={leaderboard} />
            </div>
        </CommunityLayout>
    );
}

function FilterChip({ href, active, children }: { href: string; active: boolean; children: React.ReactNode }) {
    return (
        <Link
            href={href}
            preserveScroll
            className={cn(
                'shrink-0 rounded-full border px-3.5 py-1.5 text-sm whitespace-nowrap transition-colors',
                active ? 'bg-foreground text-background border-foreground' : 'bg-card hover:bg-accent',
            )}
        >
            {children}
        </Link>
    );
}
