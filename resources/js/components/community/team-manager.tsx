import { UserAvatar } from '@/components/community/user-avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { type PublicUser, type Role } from '@/types';
import { router } from '@inertiajs/react';
import { Search, ShieldCheck } from 'lucide-react';
import { useEffect, useState } from 'react';

export type TeamMember = PublicUser & { member_id: number; role: Role };

const ROLE_HELP: Record<string, string> = {
    owner: 'Full control',
    admin: 'Settings, members, courses, events, payments',
    moderator: 'Pin, edit and delete posts and comments, approve members',
};

/**
 * Settings → Team: see who runs the community, add members as moderators or
 * admins, and change or remove their role.
 */
export function TeamManager({ slug, team, canManageAdmins }: { slug: string; team: TeamMember[]; canManageAdmins: boolean }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<(PublicUser & { member_id: number })[]>([]);
    const [searching, setSearching] = useState(false);

    useEffect(() => {
        const q = query.trim();
        if (!q) {
            setResults([]);
            return;
        }
        const timer = setTimeout(() => {
            setSearching(true);
            fetch(route('members.search', { community: slug, q }), { headers: { Accept: 'application/json' } })
                .then((r) => (r.ok ? r.json() : []))
                .then(setResults)
                .finally(() => setSearching(false));
        }, 250);
        return () => clearTimeout(timer);
    }, [query, slug]);

    const setRole = (memberId: number, role: Role) =>
        router.patch(
            route('members.update', [slug, memberId]),
            { role },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setQuery('');
                    setResults([]);
                },
            },
        );

    // Owners (and platform admins) can make admins; admins can add moderators.
    const roleOptions: Role[] = canManageAdmins ? ['admin', 'moderator'] : ['moderator'];

    return (
        <div className="bg-card space-y-4 rounded-xl border p-6 shadow-xs">
            <div>
                <h2 className="flex items-center gap-2 text-lg font-semibold">
                    <ShieldCheck className="size-5" /> Team
                </h2>
                <p className="text-muted-foreground mt-1 text-sm">
                    Moderators keep the community clean; admins also manage settings, members and courses. The team can always add photos and GIFs.
                </p>
            </div>

            <ul className="divide-y rounded-lg border">
                {team.map((m) => {
                    const editable = m.role !== 'owner' && (canManageAdmins || m.role !== 'admin');
                    return (
                        <li key={m.member_id} className="flex items-center gap-3 px-3 py-2.5">
                            <UserAvatar user={m} size="sm" />
                            <div className="min-w-0 flex-1">
                                <div className="truncate text-sm font-medium">{m.name}</div>
                                <div className="text-muted-foreground truncate text-xs">{ROLE_HELP[m.role]}</div>
                            </div>
                            {editable ? (
                                <select
                                    aria-label={`Role for ${m.name}`}
                                    value={m.role}
                                    onChange={(e) => {
                                        const role = e.target.value as Role;
                                        if (role !== 'member' || confirm(`Remove ${m.name} from the team?`)) setRole(m.member_id, role);
                                    }}
                                    className="border-input bg-background h-8 rounded-md border px-2 text-sm"
                                >
                                    {roleOptions.map((r) => (
                                        <option key={r} value={r}>
                                            {r === 'admin' ? 'Admin' : 'Moderator'}
                                        </option>
                                    ))}
                                    <option value="member">Remove from team</option>
                                </select>
                            ) : (
                                <span className="bg-brand/10 text-brand rounded-full px-2 py-0.5 text-xs font-medium capitalize">{m.role}</span>
                            )}
                        </li>
                    );
                })}
            </ul>

            <div className="space-y-2">
                <label htmlFor="team-search" className="text-sm font-medium">
                    Add someone to the team
                </label>
                <div className="relative">
                    <Search className="text-muted-foreground absolute top-2.5 left-3 size-4" />
                    <Input
                        id="team-search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search members by name or username"
                        className="pl-9"
                        autoComplete="off"
                    />
                </div>
                {query.trim() && (
                    <ul className="divide-y rounded-lg border">
                        {results.map((u) => (
                            <li key={u.member_id} className="flex flex-wrap items-center gap-3 px-3 py-2">
                                <UserAvatar user={u} size="sm" link={false} />
                                <div className="min-w-0 flex-1">
                                    <div className="truncate text-sm font-medium">{u.name}</div>
                                    <div className="text-muted-foreground truncate text-xs">@{u.username}</div>
                                </div>
                                <div className="flex gap-2">
                                    {roleOptions.map((r) => (
                                        <Button
                                            key={r}
                                            type="button"
                                            size="sm"
                                            variant={r === 'moderator' ? 'default' : 'outline'}
                                            onClick={() => setRole(u.member_id, r)}
                                        >
                                            Make {r}
                                        </Button>
                                    ))}
                                </div>
                            </li>
                        ))}
                        {!searching && results.length === 0 && (
                            <li className="text-muted-foreground px-3 py-3 text-sm">No members found. Only people who have joined can be added.</li>
                        )}
                    </ul>
                )}
            </div>
        </div>
    );
}
