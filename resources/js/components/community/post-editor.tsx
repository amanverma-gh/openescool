import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { type Category, type Community, type Post } from '@/types';
import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

interface PostEditorProps {
    community: Community;
    categories: Category[];
    post?: Post;
    onDone: () => void;
    autoFocus?: boolean;
}

/**
 * Create or edit a post. Admin-only categories are hidden from regular members.
 */
export function PostEditor({ community, categories, post, onDone, autoFocus }: PostEditorProps) {
    const available = categories.filter((c) => !c.admin_only || community.can.moderate || c.id === post?.category?.id);

    const {
        data,
        setData,
        post: submitPost,
        patch,
        processing,
        errors,
        reset,
    } = useForm({
        title: post?.title ?? '',
        body: post?.body ?? '',
        category_id: post?.category?.id ?? available[0]?.id ?? null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onDone();
            },
        };

        if (post) {
            patch(route('posts.update', [community.slug, post.id]), options);
        } else {
            submitPost(route('posts.store', community.slug), options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-3">
            <div>
                <Input
                    value={data.title}
                    onChange={(e) => setData('title', e.target.value)}
                    placeholder="Title"
                    className="h-11 border-0 px-0 text-lg font-semibold shadow-none focus-visible:ring-0"
                    autoFocus={autoFocus}
                    maxLength={200}
                    required
                />
                <InputError message={errors.title} />
            </div>
            <div>
                <Textarea
                    value={data.body}
                    onChange={(e) => setData('body', e.target.value)}
                    placeholder="Write something… Paste a YouTube, Vimeo or Loom link to embed a video. Use @username to mention someone."
                    className="min-h-36 resize-y border-0 px-0 shadow-none focus-visible:ring-0"
                    required
                />
                <InputError message={errors.body} />
            </div>
            <div className="flex flex-wrap items-center gap-2 border-t pt-3">
                {available.length > 0 && (
                    <Select value={data.category_id ? String(data.category_id) : undefined} onValueChange={(v) => setData('category_id', Number(v))}>
                        <SelectTrigger className="h-9 w-48">
                            <SelectValue placeholder="Select a category" />
                        </SelectTrigger>
                        <SelectContent>
                            {available.map((c) => (
                                <SelectItem key={c.id} value={String(c.id)}>
                                    {c.emoji} {c.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}
                <InputError message={errors.category_id} />
                <div className="ml-auto flex gap-2">
                    <Button type="button" variant="ghost" onClick={onDone}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={processing || !data.title.trim() || !data.body.trim()}>
                        {processing && <LoaderCircle className="size-4 animate-spin" />}
                        {post ? 'Save' : 'Post'}
                    </Button>
                </div>
            </div>
        </form>
    );
}
