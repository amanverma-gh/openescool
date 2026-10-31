import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head, useForm } from '@inertiajs/react';
import { Globe, LoaderCircle, Lock } from 'lucide-react';
import { FormEventHandler } from 'react';

const slugify = (value: string) =>
    value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 40);

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        slug: '',
        tagline: '',
        privacy: 'public' as 'public' | 'private',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('communities.store'));
    };

    return (
        <AppLayout>
            <Head title="Create a community" />

            <div className="bg-card mx-auto max-w-xl rounded-xl border p-6 shadow-xs sm:p-8">
                <h1 className="text-2xl font-bold">Create a community</h1>
                <p className="text-muted-foreground mt-1 text-sm">You can change all of this later in settings.</p>

                <form onSubmit={submit} className="mt-6 space-y-5">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Community name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => {
                                const wasAuto = data.slug === slugify(data.name);
                                setData((d) => ({ ...d, name: e.target.value, slug: wasAuto ? slugify(e.target.value) : d.slug }));
                            }}
                            placeholder="Creator Academy"
                            maxLength={60}
                            required
                            autoFocus
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="slug">URL</Label>
                        <div className="flex items-center">
                            <span className="bg-muted text-muted-foreground border-input flex h-9 items-center rounded-l-md border border-r-0 px-3 text-sm">
                                {window.location.host}/c/
                            </span>
                            <Input
                                id="slug"
                                value={data.slug}
                                onChange={(e) => setData('slug', slugify(e.target.value))}
                                className="rounded-l-none"
                                required
                            />
                        </div>
                        <InputError message={errors.slug} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="tagline">Tagline</Label>
                        <Input
                            id="tagline"
                            value={data.tagline}
                            onChange={(e) => setData('tagline', e.target.value)}
                            placeholder="One sentence about who this community is for"
                            maxLength={140}
                        />
                        <InputError message={errors.tagline} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Privacy</Label>
                        <div className="grid grid-cols-2 gap-3">
                            {(
                                [
                                    ['public', Globe, 'Public', 'Anyone can see posts and join.'],
                                    ['private', Lock, 'Private', 'Only members see content. Admins approve requests.'],
                                ] as const
                            ).map(([value, Icon, title, description]) => (
                                <button
                                    type="button"
                                    key={value}
                                    onClick={() => setData('privacy', value)}
                                    className={cn(
                                        'rounded-lg border p-3 text-left transition-colors',
                                        data.privacy === value ? 'border-foreground ring-foreground ring-1' : 'hover:bg-accent',
                                    )}
                                >
                                    <Icon className="mb-1 size-4" />
                                    <div className="text-sm font-medium">{title}</div>
                                    <div className="text-muted-foreground text-xs">{description}</div>
                                </button>
                            ))}
                        </div>
                    </div>

                    <Button className="w-full" disabled={processing}>
                        {processing && <LoaderCircle className="size-4 animate-spin" />}
                        Create community
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
