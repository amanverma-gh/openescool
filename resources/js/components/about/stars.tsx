import { cn } from '@/lib/utils';
import { Star } from 'lucide-react';

export function Stars({ value, className }: { value: number; className?: string }) {
    return (
        <span className={cn('inline-flex gap-0.5', className)} aria-label={`${value} out of 5 stars`}>
            {[1, 2, 3, 4, 5].map((n) => (
                <Star key={n} className={cn('size-4', n <= Math.round(value) ? 'fill-amber-400 text-amber-400' : 'text-muted-foreground/40')} />
            ))}
        </span>
    );
}

export function StarPicker({ value, onChange }: { value: number; onChange: (value: number) => void }) {
    return (
        <div className="flex gap-1" role="radiogroup" aria-label="Rating">
            {[1, 2, 3, 4, 5].map((n) => (
                <button
                    key={n}
                    type="button"
                    role="radio"
                    aria-checked={value === n}
                    aria-label={`${n} star${n > 1 ? 's' : ''}`}
                    onClick={() => onChange(n)}
                    className="rounded p-0.5 transition hover:scale-110"
                >
                    <Star className={cn('size-7', n <= value ? 'fill-amber-400 text-amber-400' : 'text-muted-foreground/40')} />
                </button>
            ))}
        </div>
    );
}

export function RatingSummary({ average, count, className }: { average: number | null; count: number; className?: string }) {
    if (!count || average === null) return null;

    return (
        <span className={cn('inline-flex items-center gap-1.5 text-sm', className)}>
            <Stars value={average} />
            <span className="text-muted-foreground">
                {average.toFixed(1)} · {count} {count === 1 ? 'review' : 'reviews'}
            </span>
        </span>
    );
}
