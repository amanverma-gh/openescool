import { FlashToast } from '@/components/community/flash-toast';
import { TopBar } from '@/components/community/top-bar';
import { type BreadcrumbItem, type CommunityLink, type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';

interface AppLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    community?: CommunityLink;
    nav?: React.ReactNode;
}

export default function AppLayout({ children, community, nav }: AppLayoutProps) {
    const { auth } = usePage<SharedData>().props;

    return (
        <div className="bg-canvas min-h-svh">
            <TopBar current={community} />
            {nav}
            {auth.user?.restricted && (
                <div className="border-b border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
                    <div className="mx-auto flex max-w-6xl items-center gap-2 px-4 py-2.5 text-sm">
                        <ShieldAlert className="size-4 shrink-0" />
                        Your account is under review by an admin. You can read everything, but posting, commenting and messaging are paused until
                        then.
                    </div>
                </div>
            )}
            <main className="mx-auto w-full max-w-6xl px-4 py-6">{children}</main>
            <FlashToast />
        </div>
    );
}
