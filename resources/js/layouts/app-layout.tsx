import { FlashToast } from '@/components/community/flash-toast';
import { TopBar } from '@/components/community/top-bar';
import { type BreadcrumbItem, type CommunityLink } from '@/types';

interface AppLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    community?: CommunityLink;
    nav?: React.ReactNode;
}

export default function AppLayout({ children, community, nav }: AppLayoutProps) {
    return (
        <div className="bg-canvas min-h-svh">
            <TopBar current={community} />
            {nav}
            <main className="mx-auto w-full max-w-6xl px-4 py-6">{children}</main>
            <FlashToast />
        </div>
    );
}
