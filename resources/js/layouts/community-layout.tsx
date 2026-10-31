import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type Community } from '@/types';
import { Link, usePage } from '@inertiajs/react';

interface CommunityLayoutProps {
    community: Community;
    children: React.ReactNode;
}

export default function CommunityLayout({ community, children }: CommunityLayoutProps) {
    const { url } = usePage();
    const base = `/c/${community.slug}`;
    const path = url.split('?')[0];

    const tabs = [
        { title: 'Community', href: base, active: path === base || path.startsWith(`${base}/posts`) },
        { title: 'Members', href: `${base}/members`, active: path.startsWith(`${base}/members`) },
        { title: 'About', href: `${base}/about`, active: path.startsWith(`${base}/about`) },
        ...(community.can.manage ? [{ title: 'Settings', href: `${base}/settings`, active: path.startsWith(`${base}/settings`) }] : []),
    ];

    const nav = (
        <nav className="bg-background border-b">
            <div className="no-scrollbar mx-auto flex max-w-6xl gap-1 overflow-x-auto px-4">
                {tabs.map((tab) => (
                    <Link
                        key={tab.href}
                        href={tab.href}
                        prefetch
                        className={cn(
                            'relative px-3 py-3 text-sm whitespace-nowrap transition-colors',
                            tab.active ? 'text-foreground font-semibold' : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {tab.title}
                        {tab.active && <span className="bg-foreground absolute inset-x-3 bottom-0 h-0.5 rounded-full" />}
                    </Link>
                ))}
            </div>
        </nav>
    );

    return (
        <AppLayout community={community} nav={nav}>
            {children}
        </AppLayout>
    );
}
