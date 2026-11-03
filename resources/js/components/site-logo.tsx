import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * The site's logo (set in Admin settings), or the first letter of its name.
 */
export function SiteLogo({ className = 'size-8' }: { className?: string }) {
    const { name, logo } = usePage<SharedData>().props;

    return logo ? (
        <img src={logo} alt="" className={cn('shrink-0 rounded-lg object-cover', className)} />
    ) : (
        <span className={cn('bg-foreground text-background flex shrink-0 items-center justify-center rounded-lg text-sm font-bold', className)}>
            {name.charAt(0)}
        </span>
    );
}
