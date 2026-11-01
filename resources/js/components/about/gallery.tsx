import { findVideoEmbed, VideoEmbed } from '@/components/community/rich-text';
import { cn } from '@/lib/utils';
import { Play } from 'lucide-react';
import { useState } from 'react';

export interface MediaItem {
    id: number;
    type: 'image' | 'video';
    src: string;
}

/**
 * Still frame for a video link. Only YouTube exposes one without an API call.
 */
export function videoThumbnail(url: string): string | null {
    const youtube = url.match(/(?:youtube\.com\/(?:watch\?v=|shorts\/|embed\/)|youtu\.be\/)([\w-]{11})/i);
    return youtube ? `https://i.ytimg.com/vi/${youtube[1]}/hqdefault.jpg` : null;
}

export function MediaThumb({ item, className, large = false }: { item: MediaItem; className?: string; large?: boolean }) {
    const still = item.type === 'image' ? item.src : videoThumbnail(item.src);

    return (
        <div className={cn('relative overflow-hidden bg-neutral-900', className)}>
            {still && <img src={still} alt="" className="size-full object-cover" loading="lazy" />}
            {item.type === 'video' && (
                <span className="absolute inset-0 flex items-center justify-center">
                    <span className={cn('flex items-center justify-center rounded-md bg-black/70 text-white', large ? 'size-14' : 'size-8')}>
                        <Play className={cn('fill-current', large ? 'size-6' : 'size-4')} />
                    </span>
                </span>
            )}
        </div>
    );
}

/**
 * Skool-style viewer: a large stage plus a strip of thumbnails. Videos load
 * only when played, so the page stays fast.
 */
export function Gallery({ items, fallback, title }: { items: MediaItem[]; fallback: string | null; title: string }) {
    const [index, setIndex] = useState(0);
    const [playing, setPlaying] = useState<number | null>(null);

    if (items.length === 0) {
        return fallback ? (
            <img src={fallback} alt="" className="aspect-video w-full rounded-lg object-cover" />
        ) : (
            <div className="from-brand/70 to-brand aspect-[3/1] w-full rounded-lg bg-gradient-to-br" />
        );
    }

    const current = items[Math.min(index, items.length - 1)];
    const embed = current.type === 'video' ? findVideoEmbed(current.src) : null;

    return (
        <div className="space-y-2">
            {current.type === 'image' ? (
                <img src={current.src} alt="" className="aspect-video w-full rounded-lg bg-neutral-900 object-contain" />
            ) : playing === current.id && embed ? (
                <VideoEmbed src={`${embed}${embed.includes('?') ? '&' : '?'}autoplay=1`} title={title} />
            ) : (
                <button type="button" onClick={() => setPlaying(current.id)} className="block w-full" aria-label="Play video">
                    <MediaThumb item={current} className="aspect-video w-full rounded-lg" large />
                </button>
            )}

            {items.length > 1 && (
                <div className="no-scrollbar flex gap-2 overflow-x-auto">
                    {items.map((item, i) => (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => {
                                setIndex(i);
                                setPlaying(null);
                            }}
                            aria-label={`Show item ${i + 1}`}
                            className={cn(
                                'shrink-0 overflow-hidden rounded-md ring-offset-2 transition',
                                i === index ? 'ring-brand ring-2' : 'opacity-80 hover:opacity-100',
                            )}
                        >
                            <MediaThumb item={item} className="h-14 w-20 sm:h-16 sm:w-24" />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
