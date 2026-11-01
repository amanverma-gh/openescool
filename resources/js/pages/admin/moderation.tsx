import { UserAvatar } from '@/components/community/user-avatar';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn, timeAgo } from '@/lib/utils';
import { type Paginated, type PublicUser } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Ban, CheckCircle2, Flag, ShieldAlert, Trash2, Undo2 } from 'lucide-react';

interface FlaggedUser extends PublicUser {
    email: string;
    joined: string;
    restricted_at: string | null;
    banned_at: string | null;
    reason: string | null;
    reports: number;
    samples: { kind: 'Post' | 'Comment' | 'Message'; text: string; spam: boolean; at: string }[];
}

interface ModerationProps {
    tab: 'review' | 'banned';
    users: Paginated<FlaggedUser>;
    counts: { review: number; banned: number };
}

export default function Moderation({ tab, users, counts }: ModerationProps) {
    const act = (url: string, method: 'post' | 'delete' = 'post') => router.visit(url, { method, preserveScroll: true });

    return (
        <AppLayout>
            <Head title="Spam review" />

            <div className="mb-6">
                <h1 className="flex items-center gap-2 text-2xl font-bold">
                    <ShieldAlert className="size-6" /> Spam review
                </h1>
                <p className="text-muted-foreground mt-1 text-sm">
                    Accounts caught by the spam filter or reported by members land here. They can read but can’t post, comment or message until you
                    decide.
                </p>
            </div>

            <div className="mb-4 flex gap-2">
                {(['review', 'banned'] as const).map((t) => (
                    <Link
                        key={t}
                        href={route('moderation.index', t === 'banned' ? { tab: 'banned' } : {})}
                        className={cn(
                            'rounded-full border px-4 py-1.5 text-sm',
                            tab === t ? 'bg-foreground text-background border-foreground' : 'hover:bg-accent',
                        )}
                    >
                        {t === 'review' ? 'Under review' : 'Banned'} ({counts[t]})
                    </Link>
                ))}
            </div>

            {users.data.length === 0 && (
                <div className="bg-card text-muted-foreground rounded-xl border p-10 text-center text-sm">
                    <CheckCircle2 className="mx-auto mb-2 size-8 text-emerald-500" />
                    {tab === 'review' ? 'Nothing to review. All clear!' : 'No banned accounts.'}
                </div>
            )}

            <div className="space-y-4">
                {users.data.map((user) => (
                    <article key={user.id} className="bg-card rounded-xl border p-5 shadow-xs">
                        <div className="flex flex-wrap items-start gap-3">
                            <UserAvatar user={user} />
                            <div className="min-w-0 flex-1">
                                <Link href={route('users.show', user.username)} className="font-semibold hover:underline">
                                    {user.name}
                                </Link>
                                <div className="text-muted-foreground text-xs">
                                    {user.email} · joined {timeAgo(user.joined)} ago
                                </div>
                                <div className="mt-2 flex flex-wrap gap-2 text-xs">
                                    {user.reason && (
                                        <span className="rounded-full bg-amber-100 px-2 py-0.5 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100">
                                            {user.reason}
                                        </span>
                                    )}
                                    {user.reports > 0 && (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-red-800 dark:bg-red-900/40 dark:text-red-100">
                                            <Flag className="size-3" /> {user.reports} {user.reports === 1 ? 'report' : 'reports'}
                                        </span>
                                    )}
                                    <span className="text-muted-foreground">
                                        {user.banned_at
                                            ? `Banned ${timeAgo(user.banned_at)} ago`
                                            : user.restricted_at && `Held ${timeAgo(user.restricted_at)} ago`}
                                    </span>
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {tab === 'review' ? (
                                    <>
                                        <Button size="sm" variant="outline" onClick={() => act(route('moderation.release', user.id))}>
                                            <CheckCircle2 /> Not spam
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="text-red-600"
                                            onClick={() => act(route('moderation.ban', user.id))}
                                        >
                                            <Ban /> Ban
                                        </Button>
                                    </>
                                ) : (
                                    <Button size="sm" variant="outline" onClick={() => act(route('moderation.unban', user.id))}>
                                        <Undo2 /> Unban
                                    </Button>
                                )}
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    onClick={() => {
                                        if (confirm(`Delete ${user.name}'s account and everything they posted? This can't be undone.`)) {
                                            act(route('moderation.destroy', user.id), 'delete');
                                        }
                                    }}
                                >
                                    <Trash2 /> Delete account
                                </Button>
                            </div>
                        </div>

                        {user.samples.length > 0 && (
                            <ul className="mt-4 space-y-2 border-t pt-4">
                                {user.samples.map((s, i) => (
                                    <li key={i} className="bg-muted/50 rounded-lg px-3 py-2 text-sm">
                                        <div className="text-muted-foreground mb-0.5 text-xs">
                                            {s.kind} · {timeAgo(s.at)} ago {s.spam && <span className="font-medium text-red-600">· hidden</span>}
                                        </div>
                                        <p className="break-words whitespace-pre-wrap">{s.text}</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </article>
                ))}
            </div>

            {users.last_page > 1 && (
                <div className="mt-6 flex justify-between">
                    {users.prev_page_url ? (
                        <Button variant="outline" asChild>
                            <Link href={users.prev_page_url}>Previous</Link>
                        </Button>
                    ) : (
                        <span />
                    )}
                    {users.next_page_url && (
                        <Button variant="outline" asChild>
                            <Link href={users.next_page_url}>Next</Link>
                        </Button>
                    )}
                </div>
            )}
        </AppLayout>
    );
}
