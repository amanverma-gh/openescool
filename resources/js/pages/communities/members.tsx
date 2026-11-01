import { UserAvatar } from '@/components/community/user-avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import CommunityLayout from '@/layouts/community-layout';
import { cn, isOnline, timeAgo } from '@/lib/utils';
import { type Community, type Paginated, type PublicUser, type Role, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { MessageCircle, MoreHorizontal, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type Member = PublicUser & {
    member_id: number;
    bio: string | null;
    role: Role;
    level: number;
    points: number;
    joined_at: string | null;
    last_seen_at: string | null;
    answers: Record<string, string | null> | null;
};

interface MembersProps {
    community: Community;
    members: Paginated<Member>;
    filters: { tab: 'members' | 'admins' | 'pending'; q: string };
    counts: { members: number; admins: number; pending: number };
}

export default function Members({ community, members, filters, counts }: MembersProps) {
    const { auth } = usePage<SharedData>().props;
    const [q, setQ] = useState(filters.q);
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(() => {
            router.get(route('members.index', community.slug), { tab: filters.tab, ...(q ? { q } : {}) }, { preserveState: true, replace: true });
        }, 300);
        return () => clearTimeout(timer);
    }, [q]); // eslint-disable-line react-hooks/exhaustive-deps

    const tabs = [
        { key: 'members', label: 'Members', count: counts.members },
        { key: 'admins', label: 'Admins', count: counts.admins },
        ...(community.can.manage ? [{ key: 'pending', label: 'Pending', count: counts.pending }] : []),
    ];

    const act = (method: 'post' | 'patch' | 'delete', name: string, member: Member, data: Record<string, string | boolean> = {}) =>
        router.visit(route(name, [community.slug, member.member_id]), { method, data, preserveScroll: true });

    const isOwner = community.membership?.role === 'owner';

    return (
        <CommunityLayout community={community}>
            <Head title={`Members · ${community.name}`} />

            <div className="mx-auto max-w-3xl">
                <div className="mb-4 flex flex-wrap items-center gap-2">
                    {tabs.map((tab) => (
                        <Link
                            key={tab.key}
                            href={route('members.index', { community: community.slug, tab: tab.key })}
                            className={cn(
                                'rounded-full border px-3.5 py-1.5 text-sm',
                                filters.tab === tab.key ? 'bg-foreground text-background border-foreground' : 'bg-card hover:bg-accent',
                            )}
                        >
                            {tab.label} <span className="opacity-60">{tab.count}</span>
                        </Link>
                    ))}
                    <div className="relative ml-auto w-full sm:w-56">
                        <Search className="text-muted-foreground absolute top-2.5 left-3 size-4" />
                        <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search members" className="bg-card pl-9" />
                    </div>
                </div>

                <div className="bg-card divide-y rounded-xl border shadow-xs">
                    {members.data.map((m) => (
                        <div key={m.member_id} className="flex gap-4 p-4">
                            <UserAvatar user={m} size="lg" showLevel online={isOnline(m.last_seen_at)} />
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Link href={route('users.show', m.username)} className="font-semibold hover:underline">
                                        {m.name}
                                    </Link>
                                    {m.role !== 'member' && (
                                        <span className="bg-brand/10 text-brand rounded-full px-2 py-0.5 text-xs font-medium capitalize">
                                            {m.role}
                                        </span>
                                    )}
                                </div>
                                <div className="text-muted-foreground text-sm">@{m.username}</div>
                                {m.bio && <p className="mt-1 text-sm">{m.bio}</p>}
                                <div className="text-muted-foreground mt-1 text-xs">
                                    {isOnline(m.last_seen_at) ? 'Online now' : m.last_seen_at ? `Active ${timeAgo(m.last_seen_at)} ago` : null}
                                    {m.joined_at && ` · Joined ${new Date(m.joined_at).toLocaleDateString()}`}
                                </div>
                                {m.answers && filters.tab === 'pending' && (
                                    <dl className="bg-muted/60 mt-3 space-y-2 rounded-lg p-3 text-sm">
                                        {Object.entries(m.answers).map(([question, answer]) => (
                                            <div key={question}>
                                                <dt className="text-muted-foreground text-xs">{question}</dt>
                                                <dd>{answer || '—'}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                )}
                                {filters.tab === 'pending' && (
                                    <div className="mt-3 flex gap-2">
                                        <Button size="sm" onClick={() => act('post', 'members.approve', m)}>
                                            Approve
                                        </Button>
                                        <Button size="sm" variant="outline" onClick={() => act('delete', 'members.destroy', m)}>
                                            Decline
                                        </Button>
                                    </div>
                                )}
                            </div>

                            {auth.user && filters.tab !== 'pending' && m.id !== auth.user.id && (
                                <Button variant="outline" size="sm" className="self-start" asChild>
                                    <Link href={route('chat.show', m.username)}>
                                        <MessageCircle /> Message
                                    </Link>
                                </Button>
                            )}
                            {community.can.manage && filters.tab !== 'pending' && m.role !== 'owner' && m.id !== auth.user?.id && (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="ghost" size="icon" aria-label="Member options">
                                            <MoreHorizontal className="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        {isOwner && m.role !== 'admin' && (
                                            <DropdownMenuItem onSelect={() => act('patch', 'members.update', m, { role: 'admin' })}>
                                                Make admin
                                            </DropdownMenuItem>
                                        )}
                                        {m.role !== 'moderator' && (isOwner || m.role !== 'admin') && (
                                            <DropdownMenuItem onSelect={() => act('patch', 'members.update', m, { role: 'moderator' })}>
                                                Make moderator
                                            </DropdownMenuItem>
                                        )}
                                        {m.role !== 'member' && (isOwner || m.role !== 'admin') && (
                                            <DropdownMenuItem onSelect={() => act('patch', 'members.update', m, { role: 'member' })}>
                                                Remove role
                                            </DropdownMenuItem>
                                        )}
                                        {(isOwner || m.role !== 'admin') && (
                                            <>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    onSelect={() => confirm(`Remove ${m.name}?`) && act('delete', 'members.destroy', m)}
                                                    className="text-red-600"
                                                >
                                                    Remove from community
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        confirm(`Ban ${m.name}? They won't be able to rejoin.`) &&
                                                        act('delete', 'members.destroy', m, { ban: true })
                                                    }
                                                    className="text-red-600"
                                                >
                                                    Ban
                                                </DropdownMenuItem>
                                            </>
                                        )}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            )}
                        </div>
                    ))}

                    {members.data.length === 0 && <p className="text-muted-foreground p-10 text-center text-sm">No members found.</p>}
                </div>

                {members.last_page > 1 && (
                    <div className="mt-4 flex items-center justify-between text-sm">
                        <Button variant="outline" size="sm" disabled={!members.prev_page_url} asChild={!!members.prev_page_url}>
                            {members.prev_page_url ? <Link href={members.prev_page_url}>Previous</Link> : <span>Previous</span>}
                        </Button>
                        <span className="text-muted-foreground">
                            Page {members.current_page} of {members.last_page}
                        </span>
                        <Button variant="outline" size="sm" disabled={!members.next_page_url} asChild={!!members.next_page_url}>
                            {members.next_page_url ? <Link href={members.next_page_url}>Next</Link> : <span>Next</span>}
                        </Button>
                    </div>
                )}
            </div>
        </CommunityLayout>
    );
}
