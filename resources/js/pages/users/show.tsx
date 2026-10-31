import { CommunityIcon } from '@/components/community/top-bar';
import { UserAvatar } from '@/components/community/user-avatar';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { isOnline, timeAgo } from '@/lib/utils';
import { type CommunityLink, type PublicUser } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Calendar, Heart, MapPin, MessageCircle } from 'lucide-react';

interface ProfileProps {
    profile: PublicUser & { bio: string | null; location: string | null; joined_at: string; last_seen_at: string | null; is_self: boolean };
    communities: (CommunityLink & { level: number; role: string })[];
    posts: { id: number; title: string; likes_count: number; comments_count: number; created_at: string; community: CommunityLink }[];
    stats: { posts: number; likes: number };
}

export default function Show({ profile, communities, posts, stats }: ProfileProps) {
    const online = isOnline(profile.last_seen_at);

    return (
        <AppLayout>
            <Head title={profile.name} />

            <div className="grid gap-6 lg:grid-cols-[300px_minmax(0,1fr)]">
                <aside className="bg-card h-fit space-y-4 rounded-xl border p-6 text-center shadow-xs">
                    <UserAvatar user={profile} size="xl" link={false} online={online} />
                    <div>
                        <h1 className="text-xl font-bold">{profile.name}</h1>
                        <p className="text-muted-foreground text-sm">@{profile.username}</p>
                    </div>
                    {profile.bio && <p className="text-sm">{profile.bio}</p>}
                    <div className="text-muted-foreground space-y-1 text-sm">
                        {profile.location && (
                            <div className="flex items-center justify-center gap-1">
                                <MapPin className="size-4" /> {profile.location}
                            </div>
                        )}
                        <div className="flex items-center justify-center gap-1">
                            <Calendar className="size-4" /> Joined{' '}
                            {new Date(profile.joined_at).toLocaleDateString(undefined, { month: 'short', year: 'numeric' })}
                        </div>
                        <div>{online ? '🟢 Online now' : profile.last_seen_at ? `Active ${timeAgo(profile.last_seen_at)} ago` : null}</div>
                    </div>
                    <div className="grid grid-cols-2 border-t pt-4">
                        <div>
                            <div className="font-semibold">{stats.posts}</div>
                            <div className="text-muted-foreground text-xs">Posts</div>
                        </div>
                        <div>
                            <div className="font-semibold">{stats.likes}</div>
                            <div className="text-muted-foreground text-xs">Likes received</div>
                        </div>
                    </div>
                    {profile.is_self && (
                        <Button variant="outline" className="w-full" asChild>
                            <Link href={route('profile.edit')}>Edit profile</Link>
                        </Button>
                    )}
                </aside>

                <div className="space-y-6">
                    {communities.length > 0 && (
                        <section className="bg-card rounded-xl border p-5 shadow-xs">
                            <h2 className="mb-3 font-semibold">Memberships</h2>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {communities.map((c) => (
                                    <Link
                                        key={c.id}
                                        href={route('communities.feed', c.slug)}
                                        className="hover:bg-accent flex items-center gap-3 rounded-lg p-2"
                                    >
                                        <CommunityIcon community={c} className="size-10" />
                                        <div className="min-w-0">
                                            <div className="truncate text-sm font-medium">{c.name}</div>
                                            <div className="text-muted-foreground text-xs">
                                                Level {c.level}
                                                {c.role !== 'member' && <span className="capitalize"> · {c.role}</span>}
                                            </div>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        </section>
                    )}

                    <section className="bg-card rounded-xl border shadow-xs">
                        <h2 className="border-b px-5 py-4 font-semibold">Recent posts</h2>
                        {posts.length === 0 && <p className="text-muted-foreground p-5 text-sm">No posts yet.</p>}
                        <ul className="divide-y">
                            {posts.map((p) => (
                                <li key={p.id}>
                                    <Link href={route('posts.show', [p.community.slug, p.id])} className="hover:bg-accent/50 block px-5 py-3">
                                        <div className="font-medium">{p.title}</div>
                                        <div className="text-muted-foreground mt-1 flex items-center gap-3 text-xs">
                                            <span>{p.community.name}</span>
                                            <span>{timeAgo(p.created_at)}</span>
                                            <span className="inline-flex items-center gap-1">
                                                <Heart className="size-3" /> {p.likes_count}
                                            </span>
                                            <span className="inline-flex items-center gap-1">
                                                <MessageCircle className="size-3" /> {p.comments_count}
                                            </span>
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
