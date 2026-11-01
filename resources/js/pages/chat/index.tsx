import { UserAvatar } from '@/components/community/user-avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/app-layout';
import { cn, isOnline, timeAgo } from '@/lib/utils';
import { type PublicUser, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Flag, MessageCircle, MoreHorizontal, Search, Send } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface ChatMessage {
    id: number;
    user_id: number;
    body: string;
    created_at: string;
    is_spam: boolean;
}

interface ChatProps {
    conversations: { user: PublicUser; last: { body: string; user_id: number } | null; last_at: string; unread: boolean }[];
    people: PublicUser[];
    search: string;
    active: {
        user: PublicUser & { last_seen_at: string | null };
        messages: ChatMessage[];
        blockedByMe: boolean;
        cannotMessage: string | null;
    } | null;
}

export default function Chat({ conversations, people, search, active }: ChatProps) {
    const { auth } = usePage<SharedData>().props;
    const [q, setQ] = useState(search);

    // Poll for new messages while the page is open and visible.
    useEffect(() => {
        const timer = setInterval(() => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['conversations', 'active', 'auth'] });
            }
        }, 8000);
        return () => clearInterval(timer);
    }, []);

    useEffect(() => {
        const timer = setTimeout(() => {
            if (q !== search) {
                router.get(window.location.pathname, q ? { q } : {}, { preserveState: true, replace: true, only: ['people', 'search'] });
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [q, search]);

    return (
        <AppLayout>
            <Head title={active ? `Chat with ${active.user.name}` : 'Chats'} />

            <div className="bg-card grid h-[calc(100svh-8.5rem)] min-h-[420px] overflow-hidden rounded-xl border shadow-xs md:grid-cols-[320px_minmax(0,1fr)]">
                <aside className={cn('flex min-h-0 flex-col border-r', active && 'hidden md:flex')}>
                    <div className="border-b p-3">
                        <h1 className="mb-2 px-1 text-lg font-semibold">Chats</h1>
                        <div className="relative">
                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <input
                                type="search"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Search users"
                                className="bg-muted focus:ring-ring h-9 w-full rounded-lg pr-3 pl-9 text-sm outline-none focus:ring-2"
                            />
                        </div>
                    </div>

                    <div className="min-h-0 flex-1 overflow-y-auto">
                        {q && (
                            <div className="border-b py-1">
                                <div className="text-muted-foreground px-4 py-1 text-xs font-medium">People</div>
                                {people.length === 0 && (
                                    <p className="text-muted-foreground px-4 py-2 text-sm">
                                        No one found. You can chat with members of your communities.
                                    </p>
                                )}
                                {people.map((person) => (
                                    <Link
                                        key={person.id}
                                        href={route('chat.show', person.username)}
                                        className="hover:bg-accent flex items-center gap-3 px-4 py-2"
                                    >
                                        <UserAvatar user={person} size="sm" link={false} />
                                        <span className="truncate text-sm font-medium">{person.name}</span>
                                    </Link>
                                ))}
                            </div>
                        )}

                        {conversations.length === 0 && !q && (
                            <div className="text-muted-foreground p-6 text-center text-sm">
                                <MessageCircle className="mx-auto mb-2 size-8 opacity-40" />
                                No chats yet. Search for someone above, or tap “Message” on a member’s profile.
                            </div>
                        )}

                        {conversations.map((c) => (
                            <Link
                                key={c.user.id}
                                href={route('chat.show', c.user.username)}
                                preserveScroll
                                className={cn('hover:bg-accent flex items-center gap-3 px-4 py-3', active?.user.id === c.user.id && 'bg-accent')}
                            >
                                <UserAvatar user={c.user} link={false} />
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-baseline justify-between gap-2">
                                        <span className={cn('truncate text-sm', c.unread ? 'font-bold' : 'font-medium')}>{c.user.name}</span>
                                        <span className="text-muted-foreground shrink-0 text-xs">{timeAgo(c.last_at)}</span>
                                    </div>
                                    <div className={cn('truncate text-sm', c.unread ? 'text-foreground' : 'text-muted-foreground')}>
                                        {c.last ? (c.last.user_id === auth.user.id ? 'You: ' : '') + c.last.body : ''}
                                    </div>
                                </div>
                                {c.unread && <span className="bg-brand size-2.5 shrink-0 rounded-full" aria-label="Unread" />}
                            </Link>
                        ))}
                    </div>
                </aside>

                {active ? (
                    <Thread key={active.user.id} active={active} meId={auth.user.id} />
                ) : (
                    <div className="text-muted-foreground hidden items-center justify-center text-sm md:flex">Select a chat to start messaging</div>
                )}
            </div>
        </AppLayout>
    );
}

function Thread({ active, meId }: { active: NonNullable<ChatProps['active']>; meId: number }) {
    const bottom = useRef<HTMLDivElement>(null);
    const form = useForm({ body: '' });
    const online = isOnline(active.user.last_seen_at);

    useEffect(() => {
        bottom.current?.scrollIntoView({ block: 'end' });
    }, [active.messages.length]);

    const send = (e?: React.FormEvent) => {
        e?.preventDefault();
        if (!form.data.body.trim() || form.processing) return;
        form.post(route('chat.store', active.user.username), {
            preserveScroll: true,
            onSuccess: () => form.reset('body'),
        });
    };

    return (
        <section className="flex min-h-0 flex-col">
            <header className="flex items-center gap-3 border-b px-4 py-3">
                <Link href={route('chat.index')} className="md:hidden" aria-label="Back to chats">
                    <ArrowLeft className="size-5" />
                </Link>
                <UserAvatar user={active.user} size="sm" online={online} />
                <div className="min-w-0 flex-1">
                    <Link href={route('users.show', active.user.username)} className="block truncate font-semibold hover:underline">
                        {active.user.name}
                    </Link>
                    <div className="text-muted-foreground text-xs">
                        {online ? 'Online now' : active.user.last_seen_at ? `Active ${timeAgo(active.user.last_seen_at)} ago` : ''}
                    </div>
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" aria-label="Chat options">
                            <MoreHorizontal className="size-5" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {active.blockedByMe ? (
                            <DropdownMenuItem onSelect={() => router.delete(route('chat.unblock', active.user.username), { preserveScroll: true })}>
                                Unblock {active.user.name}
                            </DropdownMenuItem>
                        ) : (
                            <DropdownMenuItem
                                className="text-red-600"
                                onSelect={() => router.post(route('chat.block', active.user.username), {}, { preserveScroll: true })}
                            >
                                Block {active.user.name}
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </header>

            <div className="min-h-0 flex-1 space-y-2 overflow-y-auto px-4 py-4">
                {active.messages.length === 0 && (
                    <p className="text-muted-foreground py-8 text-center text-sm">Say hi to {active.user.name.split(' ')[0]} 👋</p>
                )}
                {active.messages.map((m) => {
                    const mine = m.user_id === meId;
                    return (
                        <div key={m.id} className={cn('group flex items-end gap-1', mine ? 'justify-end' : 'justify-start')}>
                            {!mine && (
                                <button
                                    type="button"
                                    title="Report as spam"
                                    aria-label="Report as spam"
                                    onClick={() => router.post(route('report', ['message', m.id]), {}, { preserveScroll: true })}
                                    className="text-muted-foreground order-last opacity-0 transition-opacity group-hover:opacity-100 hover:text-red-600 focus:opacity-100"
                                >
                                    <Flag className="size-3.5" />
                                </button>
                            )}
                            <div
                                className={cn(
                                    'max-w-[75%] rounded-2xl px-3.5 py-2 text-sm break-words whitespace-pre-wrap',
                                    mine ? 'bg-brand text-brand-foreground rounded-br-sm' : 'bg-muted rounded-bl-sm',
                                    m.is_spam && 'opacity-60',
                                )}
                                title={new Date(m.created_at).toLocaleString()}
                            >
                                {m.body}
                                {m.is_spam && <div className="mt-1 text-[11px] italic">Held for review · not delivered</div>}
                            </div>
                        </div>
                    );
                })}
                <div ref={bottom} />
            </div>

            {active.cannotMessage ? (
                <div className="text-muted-foreground border-t px-4 py-4 text-center text-sm">{active.cannotMessage}</div>
            ) : (
                <form onSubmit={send} className="flex items-end gap-2 border-t p-3">
                    <textarea
                        value={form.data.body}
                        onChange={(e) => form.setData('body', e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter' && !e.shiftKey) send(e);
                        }}
                        rows={1}
                        maxLength={2000}
                        placeholder={`Message ${active.user.name}`}
                        className="bg-muted focus:ring-ring max-h-32 min-h-10 flex-1 resize-none rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2"
                    />
                    <Button type="submit" size="icon" disabled={form.processing || !form.data.body.trim()} aria-label="Send">
                        <Send className="size-4" />
                    </Button>
                </form>
            )}
        </section>
    );
}
