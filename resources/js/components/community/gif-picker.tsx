import { Dialog, DialogContent, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { LoaderCircle, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

export interface GiphyGif {
    id: string;
    preview: string;
    title: string;
}

/**
 * WhatsApp-style GIF picker: trending GIFs, plus search as you type. Results
 * come from GIPHY through our server so the API key stays private.
 */
export function GifPicker({ onPick, disabled, compact = false }: { onPick: (gif: GiphyGif) => void; disabled?: boolean; compact?: boolean }) {
    const [open, setOpen] = useState(false);
    const [q, setQ] = useState('');
    const [gifs, setGifs] = useState<GiphyGif[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open) return;
        const controller = new AbortController();
        const timer = setTimeout(async () => {
            setLoading(true);
            setError(null);
            try {
                const res = await fetch(route('giphy.search', q.trim() ? { q: q.trim() } : {}), {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                if (!res.ok) throw new Error(res.status === 503 ? 'GIF search is not set up yet.' : 'Could not load GIFs. Try again.');
                setGifs((await res.json()).data);
            } catch (e) {
                if (!controller.signal.aborted) setError(e instanceof Error ? e.message : 'Could not load GIFs.');
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 300);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [q, open]);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger
                type="button"
                disabled={disabled}
                title="Add a GIF"
                aria-label="Add a GIF"
                className={`hover:bg-accent text-muted-foreground hover:text-foreground inline-flex items-center justify-center rounded-md font-bold disabled:pointer-events-none disabled:opacity-50 ${compact ? 'h-7 px-1 text-[10px]' : 'h-9 px-2 text-xs'}`}
            >
                <span className="rounded border-2 border-current px-1 leading-4">GIF</span>
            </DialogTrigger>
            <DialogContent className="max-w-md gap-2 p-4">
                <DialogTitle className="text-base">Choose a GIF</DialogTitle>
                <div className="relative mb-2">
                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                    <input
                        autoFocus
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Search GIPHY"
                        className="bg-muted focus:ring-ring h-9 w-full rounded-md pr-2 pl-8 text-sm outline-none focus:ring-2"
                    />
                </div>
                <div className="h-80 overflow-y-auto">
                    {error ? (
                        <p className="text-muted-foreground p-4 text-center text-sm">{error}</p>
                    ) : loading && gifs.length === 0 ? (
                        <LoaderCircle className="text-muted-foreground mx-auto mt-10 size-6 animate-spin" />
                    ) : gifs.length === 0 ? (
                        <p className="text-muted-foreground p-4 text-center text-sm">No GIFs found.</p>
                    ) : (
                        <div className="columns-2 gap-1.5 [&>*]:mb-1.5">
                            {gifs.map((gif) => (
                                <button
                                    key={gif.id}
                                    type="button"
                                    onClick={() => {
                                        onPick(gif);
                                        setOpen(false);
                                    }}
                                    className="focus:ring-ring block w-full overflow-hidden rounded-md focus:ring-2 focus:outline-none"
                                >
                                    <img src={gif.preview} alt={gif.title} loading="lazy" className="w-full" />
                                </button>
                            ))}
                        </div>
                    )}
                </div>
                <p className="text-muted-foreground mt-1 text-right text-[10px] font-semibold tracking-wide uppercase">Powered by GIPHY</p>
            </DialogContent>
        </Dialog>
    );
}

export function giphyUrl(id: string): string {
    return `https://media.giphy.com/media/${id}/giphy.gif`;
}
