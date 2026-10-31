import { CommunityIcon } from '@/components/community/top-bar';
import { UserAvatar } from '@/components/community/user-avatar';
import { Button } from '@/components/ui/button';
import { formatCount } from '@/lib/utils';
import { type Community, type PublicUser, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Globe, Lock } from 'lucide-react';

type Leader = PublicUser & { points: number; level: number };

export function CommunitySidebar({ community, leaderboard }: { community: Community; leaderboard?: Leader[] }) {
    const { auth } = usePage<SharedData>().props;

    return (
        <aside className="space-y-4">
            <div className="bg-card overflow-hidden rounded-xl border shadow-xs">
                {community.cover ? (
                    <img src={community.cover} alt="" className="aspect-[2/1] w-full object-cover" />
                ) : (
                    <div className="from-brand/80 to-brand aspect-[3/1] w-full bg-gradient-to-br" />
                )}
                <div className="space-y-3 p-4">
                    <div className="flex items-center gap-2">
                        <CommunityIcon community={community} className="size-9" />
                        <h2 className="font-semibold">{community.name}</h2>
                    </div>
                    {community.tagline && <p className="text-muted-foreground text-sm">{community.tagline}</p>}
                    <div className="text-muted-foreground flex items-center gap-4 text-xs">
                        <span className="inline-flex items-center gap-1">
                            {community.privacy === 'public' ? <Globe className="size-3.5" /> : <Lock className="size-3.5" />}
                            {community.privacy === 'public' ? 'Public' : 'Private'}
                        </span>
                        <span>
                            <strong className="text-foreground">{formatCount(community.members_count)}</strong> members
                        </span>
                    </div>
                    {!community.membership && (
                        <Button className="w-full" asChild>
                            <Link href={auth.user ? route('communities.about', community.slug) : route('register')}>Join community</Link>
                        </Button>
                    )}
                    {community.membership?.status === 'pending' && (
                        <p className="bg-muted rounded-md px-3 py-2 text-center text-sm">Membership pending approval</p>
                    )}
                    {community.can.manage && (
                        <Button variant="outline" className="w-full" asChild>
                            <Link href={route('communities.settings', community.slug)}>Community settings</Link>
                        </Button>
                    )}
                </div>
            </div>

            {leaderboard === undefined ? (
                <div className="bg-card h-48 animate-pulse rounded-xl border" />
            ) : (
                leaderboard.length > 0 && (
                    <div className="bg-card rounded-xl border p-4 shadow-xs">
                        <h3 className="mb-3 text-sm font-semibold">Leaderboard</h3>
                        <ol className="space-y-3">
                            {leaderboard.map((leader, i) => (
                                <li key={leader.id} className="flex items-center gap-3 text-sm">
                                    <span className="text-muted-foreground w-4 text-center text-xs font-semibold">{i + 1}</span>
                                    <UserAvatar user={leader} size="sm" showLevel />
                                    <Link href={route('users.show', leader.username)} className="min-w-0 flex-1 truncate font-medium hover:underline">
                                        {leader.name}
                                    </Link>
                                    <span className="text-brand text-xs font-semibold">+{leader.points}</span>
                                </li>
                            ))}
                        </ol>
                    </div>
                )
            )}
        </aside>
    );
}
