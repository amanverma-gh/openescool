import { MediaThumb, type MediaItem } from '@/components/about/gallery';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { router, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, ImagePlus, LoaderCircle, Trash2 } from 'lucide-react';
import { useRef } from 'react';

const MAX_ITEMS = 8;

/**
 * Settings section for the About-page gallery: images and video links, in order.
 */
export function GalleryManager({ slug, media }: { slug: string; media: MediaItem[] }) {
    const fileInput = useRef<HTMLInputElement>(null);
    const video = useForm({ type: 'video', url: '' });
    const image = useForm<{ type: string; image: File | null }>({ type: 'image', image: null });
    const full = media.length >= MAX_ITEMS;

    const upload = (file: File | undefined) => {
        if (!file) return;
        image.transform(() => ({ type: 'image', image: file }));
        image.post(route('media.store', slug), {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                if (fileInput.current) fileInput.current.value = '';
            },
        });
    };

    const move = (item: MediaItem, direction: 'left' | 'right') =>
        router.post(route('media.move', [slug, item.id]), { direction }, { preserveScroll: true });

    return (
        <section className="bg-card space-y-4 rounded-xl border p-6 shadow-xs">
            <div>
                <h2 className="text-lg font-semibold">About page gallery</h2>
                <p className="text-muted-foreground text-sm">
                    Up to {MAX_ITEMS} images or videos shown at the top of your About page. Put your best intro video first.
                </p>
            </div>

            {media.length > 0 && (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {media.map((item, i) => (
                        <div key={item.id} className="space-y-1">
                            <MediaThumb item={item} className="aspect-video w-full rounded-md" />
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-xs">{i === 0 ? 'Main' : `#${i + 1}`}</span>
                                <div className="flex">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        disabled={i === 0}
                                        onClick={() => move(item, 'left')}
                                        title="Move left"
                                    >
                                        <ChevronLeft className="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        disabled={i === media.length - 1}
                                        onClick={() => move(item, 'right')}
                                        title="Move right"
                                    >
                                        <ChevronRight className="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        title="Remove"
                                        onClick={() =>
                                            confirm('Remove this item from the gallery?') &&
                                            router.delete(route('media.destroy', [slug, item.id]), { preserveScroll: true })
                                        }
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {full ? (
                <p className="text-muted-foreground text-sm">The gallery is full. Remove an item to add another.</p>
            ) : (
                <div className="flex flex-col gap-3 sm:flex-row">
                    <input ref={fileInput} type="file" accept="image/*" className="hidden" onChange={(e) => upload(e.target.files?.[0])} />
                    <Button type="button" variant="outline" onClick={() => fileInput.current?.click()} disabled={image.processing}>
                        {image.processing ? <LoaderCircle className="size-4 animate-spin" /> : <ImagePlus className="size-4" />}
                        Upload image
                    </Button>
                    <form
                        className="flex flex-1 gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            video.post(route('media.store', slug), { preserveScroll: true, onSuccess: () => video.reset('url') });
                        }}
                    >
                        <Input
                            type="url"
                            placeholder="YouTube, Vimeo or Loom link"
                            value={video.data.url}
                            onChange={(e) => video.setData('url', e.target.value)}
                            required
                        />
                        <Button disabled={video.processing}>Add video</Button>
                    </form>
                </div>
            )}
            <InputError message={video.errors.url || image.errors.image} />
        </section>
    );
}
