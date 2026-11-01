import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
];

/**
 * Compact relative time: "3h", "2d", "just now". Older than a month shows a date.
 */
export function timeAgo(date: string | Date): string {
    const seconds = Math.round((Date.now() - new Date(date).getTime()) / 1000);

    if (seconds < 60) return 'just now';
    if (seconds > 2592000) {
        return new Date(date).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }

    for (const [unit, size] of units) {
        if (seconds >= size) {
            const value = Math.floor(seconds / size);
            return `${value}${unit === 'minute' ? 'm' : unit.charAt(0)}`;
        }
    }

    return 'just now';
}

export function isOnline(lastSeen: string | null | undefined): boolean {
    return !!lastSeen && Date.now() - new Date(lastSeen).getTime() < 5 * 60 * 1000;
}

export function formatCount(n: number): string {
    return n >= 1000 ? `${(n / 1000).toFixed(n >= 10000 ? 0 : 1)}k` : String(n);
}

export function plural(n: number, word: string): string {
    return `${n} ${word}${n === 1 ? '' : 's'}`;
}

export function formatMoney(amount: number, currency: string): string {
    const value = amount / 100;
    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
            maximumFractionDigits: Number.isInteger(value) ? 0 : 2,
        }).format(value);
    } catch {
        return `${currency} ${value.toFixed(2)}`;
    }
}

export function intervalLabel(interval: 'month' | 'year' | 'one_time'): string {
    return { month: '/month', year: '/year', one_time: ' once' }[interval];
}

export function levelLabel(level: number, names?: (string | null)[]): string {
    const name = names?.[level - 1];
    return name ? `Level ${level} · ${name}` : `Level ${level}`;
}
