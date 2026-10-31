import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { type PublicUser } from '@/types';
import { Link } from '@inertiajs/react';

const sizes = {
    xs: 'size-6 text-[10px]',
    sm: 'size-8 text-xs',
    md: 'size-10 text-sm',
    lg: 'size-14 text-base',
    xl: 'size-28 text-3xl',
};

interface UserAvatarProps {
    user: Pick<PublicUser, 'name' | 'username' | 'avatar'> & { level?: number | null };
    size?: keyof typeof sizes;
    showLevel?: boolean;
    link?: boolean;
    online?: boolean;
    className?: string;
}

export function UserAvatar({ user, size = 'md', showLevel = false, link = true, online = false, className }: UserAvatarProps) {
    const getInitials = useInitials();

    const avatar = (
        <span className={cn('relative inline-flex shrink-0', className)}>
            <Avatar className={cn('overflow-hidden rounded-full', sizes[size])}>
                <AvatarImage src={user.avatar ?? undefined} alt={user.name} className="object-cover" />
                <AvatarFallback className="bg-brand/15 text-brand rounded-full font-semibold">{getInitials(user.name)}</AvatarFallback>
            </Avatar>
            {showLevel && user.level ? (
                <span
                    className="bg-level ring-background absolute -right-1 -bottom-1 flex size-[18px] items-center justify-center rounded-full text-[10px] font-bold text-white ring-2"
                    title={`Level ${user.level}`}
                >
                    {user.level}
                </span>
            ) : null}
            {online && <span className="ring-background absolute right-0 bottom-0 size-2.5 rounded-full bg-emerald-500 ring-2" />}
        </span>
    );

    if (!link) return avatar;

    return (
        <Link href={route('users.show', user.username)} className="shrink-0" aria-label={user.name}>
            {avatar}
        </Link>
    );
}
