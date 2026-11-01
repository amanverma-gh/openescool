import { PostCard } from '@/components/community/post-card';
import { UserAvatar } from '@/components/community/user-avatar';
import CommunityLayout from '@/layouts/community-layout';
import { cn } from '@/lib/utils';
import { type Community, type Post, type PublicUser } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Search as SearchIcon } from 'lucide-react';
import { useState } from 'react';

interface SearchProps {
    community: Community;
    q: string;
    posts: Post[];
    members: (PublicUser & { level: number; role: string })[];
}

export default function Search({ community, q, posts, members }: SearchProps) {
    const [query, setQuery] = useState(q);
    const [tab, setTab] = useState<'posts' | 'members'>(posts.length === 0 && members.length > 0 ? 'members' : 'posts');
    const searched = q.length >= 2;

    return (
        <CommunityLayout community={community}>
            <Head title={q ? `Search: ${q}` : 'Search'} />

            <div className="mx-auto max-w-3xl">
                <form
                    role="search"
                    className="relative mb-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(route('communities.search', community.slug), { q: query.trim() }, { preserveState: false });
                    }}
                >
                    <SearchIcon className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                    <input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={`Search ${community.name}`}
                        autoFocus={!q}
                        className="bg-card focus:ring-ring h-11 w-full rounded-xl border pr-3 pl-9 outline-none focus:ring-2"
                    />
                </form>

                {!searched ? (
                    <p className="text-muted-foreground py-10 text-center text-sm">Type at least 2 letters to search posts and members.</p>
                ) : (
                    <>
                        <div className="mb-4 flex gap-2">
                            {(['posts', 'members'] as const).map((t) => (
                                <button
                                    key={t}
                                    onClick={() => setTab(t)}
                                    className={cn(
                                        'rounded-full border px-4 py-1.5 text-sm capitalize',
                                        tab === t ? 'bg-foreground text-background border-foreground' : 'bg-card hover:bg-accent',
                                    )}
                                >
                                    {t} ({t === 'posts' ? posts.length : members.length})
                                </button>
                            ))}
                        </div>

                        {tab === 'posts' && (
                            <div className="space-y-4">
                                {posts.length === 0 && <Empty q={q} />}
                                {posts.map((post) => (
                                    <PostCard key={post.id} post={post} community={community} />
                                ))}
                            </div>
                        )}

                        {tab === 'members' && (
                            <div className="bg-card divide-y rounded-xl border shadow-xs">
                                {members.length === 0 && <Empty q={q} />}
                                {members.map((m) => (
                                    <Link
                                        key={m.id}
                                        href={route('users.show', m.username)}
                                        className="hover:bg-accent/50 flex items-center gap-3 p-4"
                                    >
                                        <UserAvatar user={m} showLevel link={false} />
                                        <div className="min-w-0">
                                            <div className="truncate font-medium">{m.name}</div>
                                            <div className="text-muted-foreground text-sm">
                                                @{m.username}
                                                {m.role !== 'member' && <span className="capitalize"> · {m.role}</span>}
                                            </div>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </CommunityLayout>
    );
}

function Empty({ q }: { q: string }) {
    return <p className="text-muted-foreground p-8 text-center text-sm">Nothing found for “{q}”.</p>;
}
