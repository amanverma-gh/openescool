import { UserAvatar } from '@/components/community/user-avatar';
import CommunityLayout from '@/layouts/community-layout';
import { cn } from '@/lib/utils';
import { type Community, type PublicUser } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Lock } from 'lucide-react';

type Leader = PublicUser & { points: number; level: number };

interface LeaderboardsProps {
    community: Community;
    levels: { level: number; points: number; members: number; percent: number }[];
    me: { level: number; points: number; points_to_next: number | null } | null;
    boards: { week: Leader[]; month: Leader[]; all: Leader[] };
}

const medals = ['🥇', '🥈', '🥉'];

export default function Leaderboards({ community, levels, me, boards }: LeaderboardsProps) {
    return (
        <CommunityLayout community={community}>
            <Head title={`Leaderboards · ${community.name}`} />

            <div className="bg-card mb-6 grid gap-6 rounded-xl border p-6 shadow-xs md:grid-cols-[220px_minmax(0,1fr)]">
                <div className="flex flex-col items-center justify-center text-center">
                    {me ? (
                        <>
                            <div className="border-level text-level flex size-24 items-center justify-center rounded-full border-4 text-3xl font-bold">
                                {me.level}
                            </div>
                            <div className="mt-3 font-semibold">Level {me.level}</div>
                            <div className="text-muted-foreground text-sm">
                                {me.points_to_next !== null ? `${me.points_to_next} points to level up` : 'Top level reached 🎉'}
                            </div>
                        </>
                    ) : (
                        <p className="text-muted-foreground text-sm">Join the community to start earning points.</p>
                    )}
                </div>
                <div>
                    <p className="text-muted-foreground mb-3 text-sm">You earn a point every time someone likes your post or comment.</p>
                    <div className="grid gap-x-8 gap-y-2 sm:grid-cols-2">
                        {levels.map((l) => (
                            <div key={l.level} className={cn('flex items-center gap-3 text-sm', me && l.level > me.level && 'opacity-60')}>
                                <span
                                    className={cn(
                                        'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                        me && l.level <= me.level ? 'bg-level text-white' : 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {me && l.level > me.level ? <Lock className="size-3" /> : l.level}
                                </span>
                                <span className="flex-1">
                                    Level {l.level} <span className="text-muted-foreground text-xs">({l.points} pts)</span>
                                </span>
                                <span className="text-muted-foreground text-xs">{l.percent}% of members</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <div className="grid gap-6 md:grid-cols-3">
                <Board title="Leaderboard (7-day)" leaders={boards.week} />
                <Board title="Leaderboard (30-day)" leaders={boards.month} />
                <Board title="Leaderboard (all-time)" leaders={boards.all} />
            </div>
        </CommunityLayout>
    );
}

function Board({ title, leaders }: { title: string; leaders: Leader[] }) {
    return (
        <section className="bg-card rounded-xl border p-4 shadow-xs">
            <h2 className="mb-3 text-sm font-semibold">{title}</h2>
            {leaders.length === 0 && <p className="text-muted-foreground py-6 text-center text-sm">No points earned yet.</p>}
            <ol className="space-y-3">
                {leaders.map((leader, i) => (
                    <li key={leader.id} className="flex items-center gap-3 text-sm">
                        <span className="w-6 text-center text-sm font-semibold">{medals[i] ?? i + 1}</span>
                        <UserAvatar user={leader} size="sm" showLevel />
                        <Link href={route('users.show', leader.username)} className="min-w-0 flex-1 truncate font-medium hover:underline">
                            {leader.name}
                        </Link>
                        <span className="text-brand text-xs font-semibold">+{leader.points}</span>
                    </li>
                ))}
            </ol>
        </section>
    );
}
