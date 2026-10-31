import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Fragment, type ReactNode } from 'react';

const TOKEN = /(https?:\/\/[^\s<]+[^\s<.,:;"')\]!?])|(?<![\w@])@([a-z0-9][a-z0-9_-]{1,49})/gi;

/**
 * Plain text with clickable links and @mentions. React escapes everything,
 * so user content can never inject HTML.
 */
export function RichText({ text, className }: { text: string; className?: string }) {
    const parts: ReactNode[] = [];
    let last = 0;

    for (const match of text.matchAll(TOKEN)) {
        const index = match.index ?? 0;
        parts.push(text.slice(last, index));

        if (match[1]) {
            parts.push(
                <a key={index} href={match[1]} target="_blank" rel="noopener noreferrer nofollow" className="text-brand break-all hover:underline">
                    {match[1]}
                </a>,
            );
        } else {
            parts.push(
                <Link key={index} href={route('users.show', match[2].toLowerCase())} className="text-brand font-medium hover:underline">
                    @{match[2]}
                </Link>,
            );
        }

        last = index + match[0].length;
    }
    parts.push(text.slice(last));

    return (
        <div className={cn('break-words whitespace-pre-wrap', className)}>
            {parts.map((part, i) => (
                <Fragment key={i}>{part}</Fragment>
            ))}
        </div>
    );
}

/**
 * Embed URL for the first YouTube, Vimeo or Loom link in the text, if any.
 */
export function findVideoEmbed(text: string): string | null {
    const youtube = text.match(/(?:youtube\.com\/(?:watch\?v=|shorts\/|embed\/)|youtu\.be\/)([\w-]{11})/i);
    if (youtube) return `https://www.youtube-nocookie.com/embed/${youtube[1]}`;

    const vimeo = text.match(/vimeo\.com\/(\d+)/i);
    if (vimeo) return `https://player.vimeo.com/video/${vimeo[1]}`;

    const loom = text.match(/loom\.com\/share\/([a-f0-9]+)/i);
    if (loom) return `https://www.loom.com/embed/${loom[1]}`;

    return null;
}

export function VideoEmbed({ src, title }: { src: string; title: string }) {
    return (
        <div className="aspect-video w-full overflow-hidden rounded-lg bg-black">
            <iframe
                src={src}
                title={title}
                className="size-full"
                loading="lazy"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowFullScreen
            />
        </div>
    );
}
