import { UserAvatar } from '@/components/community/user-avatar';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn, timeAgo } from '@/lib/utils';
import { type PublicUser } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Bell } from 'lucide-react';

interface NotificationItem {
    id: string;
    read: boolean;
    created_at: string;
    data: { actor: PublicUser | null; message: string; excerpt: string | null; url: string };
}

export default function Index({ notifications }: { notifications: NotificationItem[] }) {
    const hasUnread = notifications.some((n) => !n.read);

    return (
        <AppLayout>
            <Head title="Notifications" />

            <div className="mx-auto max-w-2xl">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-xl font-bold">Notifications</h1>
                    {hasUnread && (
                        <Button variant="ghost" size="sm" onClick={() => router.post(route('notifications.read-all'), {}, { preserveScroll: true })}>
                            Mark all as read
                        </Button>
                    )}
                </div>

                <div className="bg-card divide-y rounded-xl border shadow-xs">
                    {notifications.map((n) => (
                        <Link
                            key={n.id}
                            href={route('notifications.show', n.id)}
                            className={cn('hover:bg-accent/50 flex gap-3 p-4', !n.read && 'bg-brand/5')}
                        >
                            {n.data.actor ? (
                                <UserAvatar user={n.data.actor} size="md" link={false} />
                            ) : (
                                <span className="bg-muted flex size-10 items-center justify-center rounded-full">
                                    <Bell className="size-4" />
                                </span>
                            )}
                            <div className="min-w-0 flex-1 text-sm">
                                <p className={cn(!n.read && 'font-medium')}>{n.data.message}</p>
                                {n.data.excerpt && <p className="text-muted-foreground mt-0.5 line-clamp-2">{n.data.excerpt}</p>}
                                <p className="text-muted-foreground mt-1 text-xs">{timeAgo(n.created_at)}</p>
                            </div>
                            {!n.read && <span className="bg-brand mt-2 size-2 shrink-0 rounded-full" />}
                        </Link>
                    ))}
                    {notifications.length === 0 && <p className="text-muted-foreground p-10 text-center text-sm">You're all caught up.</p>}
                </div>
            </div>
        </AppLayout>
    );
}
