import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { ThumbsUp } from 'lucide-react';
import { useEffect, useState } from 'react';

interface LikeButtonProps {
    url: string;
    liked: boolean;
    count: number;
    disabled?: boolean;
    size?: 'sm' | 'md';
}

/**
 * Optimistic like toggle: flips instantly, rolls back if the request fails.
 */
export function LikeButton({ url, liked, count, disabled, size = 'md' }: LikeButtonProps) {
    const [state, setState] = useState({ liked, count });

    useEffect(() => setState({ liked, count }), [liked, count]);

    const toggle = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (disabled) return;

        const previous = state;
        setState({ liked: !state.liked, count: state.count + (state.liked ? -1 : 1) });

        // `only` keeps the round trip tiny: the UI already shows the new state.
        router.post(url, {}, { only: ['flash'], preserveScroll: true, preserveState: true, onError: () => setState(previous) });
    };

    return (
        <button
            type="button"
            onClick={toggle}
            disabled={disabled}
            aria-pressed={state.liked}
            className={cn(
                'inline-flex items-center gap-1.5 rounded-md transition-colors disabled:cursor-default',
                size === 'sm' ? 'text-xs' : 'text-sm',
                state.liked ? 'text-brand font-medium' : 'text-muted-foreground',
                !disabled && 'hover:text-foreground',
            )}
            title={disabled ? undefined : state.liked ? 'Unlike' : 'Like'}
        >
            <ThumbsUp className={cn(size === 'sm' ? 'size-3.5' : 'size-4', state.liked && 'fill-current')} />
            {state.count > 0 && <span>{state.count}</span>}
        </button>
    );
}
