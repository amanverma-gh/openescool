import { UserAvatar } from '@/components/community/user-avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { type CommunityLink, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Bell, ChevronsUpDown, Compass, Plus } from 'lucide-react';

function CommunityIcon({ community, className = 'size-8' }: { community: Pick<CommunityLink, 'name' | 'icon'>; className?: string }) {
    return community.icon ? (
        <img src={community.icon} alt="" className={`${className} rounded-lg object-cover`} />
    ) : (
        <span className={`${className} bg-brand text-brand-foreground flex items-center justify-center rounded-lg text-sm font-bold`}>
            {community.name.charAt(0).toUpperCase()}
        </span>
    );
}

export { CommunityIcon };

export function TopBar({ current }: { current?: CommunityLink }) {
    const { auth, name } = usePage<SharedData>().props;
    const communities = auth.communities ?? [];

    return (
        <header className="bg-background/95 supports-[backdrop-filter]:bg-background/80 sticky top-0 z-40 border-b backdrop-blur">
            <div className="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4">
                <DropdownMenu>
                    <DropdownMenuTrigger className="hover:bg-accent -ml-2 flex min-w-0 items-center gap-2 rounded-lg px-2 py-1.5 text-left">
                        {current ? (
                            <CommunityIcon community={current} />
                        ) : (
                            <span className="bg-foreground text-background flex size-8 items-center justify-center rounded-lg text-sm font-bold">
                                {name.charAt(0)}
                            </span>
                        )}
                        <span className="truncate text-base font-semibold">{current?.name ?? name}</span>
                        <ChevronsUpDown className="text-muted-foreground size-4 shrink-0" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-64">
                        {communities.length > 0 && (
                            <>
                                <DropdownMenuLabel className="text-muted-foreground text-xs font-normal">Your communities</DropdownMenuLabel>
                                {communities.map((c) => (
                                    <DropdownMenuItem key={c.id} asChild>
                                        <Link href={route('communities.feed', c.slug)} className="gap-2">
                                            <CommunityIcon community={c} className="size-6" />
                                            <span className="truncate">{c.name}</span>
                                        </Link>
                                    </DropdownMenuItem>
                                ))}
                                <DropdownMenuSeparator />
                            </>
                        )}
                        <DropdownMenuItem asChild>
                            <Link href={route('home')}>
                                <Compass className="mr-2" /> Discover communities
                            </Link>
                        </DropdownMenuItem>
                        {auth.user?.is_admin && (
                            <DropdownMenuItem asChild>
                                <Link href={route('communities.create')}>
                                    <Plus className="mr-2" /> Create a community
                                </Link>
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>

                <div className="ml-auto flex items-center gap-1">
                    {auth.user ? (
                        <>
                            <Button variant="ghost" size="icon" className="relative" asChild>
                                <Link href={route('notifications.index')} aria-label="Notifications">
                                    <Bell className="size-5" />
                                    {auth.unreadNotifications > 0 && (
                                        <span className="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] leading-4 font-bold text-white">
                                            {auth.unreadNotifications > 9 ? '9+' : auth.unreadNotifications}
                                        </span>
                                    )}
                                </Link>
                            </Button>
                            <DropdownMenu>
                                <DropdownMenuTrigger className="rounded-full p-1">
                                    <UserAvatar user={auth.user as never} size="sm" link={false} />
                                </DropdownMenuTrigger>
                                <DropdownMenuContent className="w-56" align="end">
                                    <UserMenuContent user={auth.user} />
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </>
                    ) : (
                        <>
                            <Button variant="ghost" asChild>
                                <Link href={route('login')}>Log in</Link>
                            </Button>
                            <Button asChild>
                                <Link href={route('register')}>Sign up</Link>
                            </Button>
                        </>
                    )}
                </div>
            </div>
        </header>
    );
}
