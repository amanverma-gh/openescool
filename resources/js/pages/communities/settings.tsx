import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import CommunityLayout from '@/layouts/community-layout';
import { plural } from '@/lib/utils';
import { type Category, type Community } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { LoaderCircle, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

interface SettingsProps {
    community: Community;
    settings: {
        name: string;
        tagline: string | null;
        about: string | null;
        privacy: 'public' | 'private';
        requires_approval: boolean;
        join_questions: string[];
    };
    categories: (Category & { posts_count: number; position: number })[];
}

export default function Settings({ community, settings, categories }: SettingsProps) {
    const form = useForm({
        name: settings.name,
        tagline: settings.tagline ?? '',
        about: settings.about ?? '',
        privacy: settings.privacy,
        requires_approval: settings.requires_approval,
        join_questions: settings.join_questions,
        icon: null as File | null,
        cover: null as File | null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('communities.settings.update', community.slug), { forceFormData: true, preserveScroll: true });
    };

    return (
        <CommunityLayout community={community}>
            <Head title={`Settings · ${community.name}`} />

            <div className="mx-auto max-w-3xl space-y-6">
                <form onSubmit={submit} className="bg-card space-y-5 rounded-xl border p-6 shadow-xs">
                    <h2 className="text-lg font-semibold">General</h2>

                    <Field label="Name" error={form.errors.name}>
                        <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} maxLength={60} required />
                    </Field>
                    <Field label="Tagline" error={form.errors.tagline}>
                        <Input value={form.data.tagline} onChange={(e) => form.setData('tagline', e.target.value)} maxLength={140} />
                    </Field>
                    <Field label="About page" error={form.errors.about}>
                        <Textarea value={form.data.about} onChange={(e) => form.setData('about', e.target.value)} rows={6} />
                    </Field>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Icon (square, max 2 MB)" error={form.errors.icon}>
                            <Input type="file" accept="image/*" onChange={(e) => form.setData('icon', e.target.files?.[0] ?? null)} />
                        </Field>
                        <Field label="Cover image (max 5 MB)" error={form.errors.cover}>
                            <Input type="file" accept="image/*" onChange={(e) => form.setData('cover', e.target.files?.[0] ?? null)} />
                        </Field>
                    </div>

                    <Field label="Privacy">
                        <div className="flex gap-4 text-sm">
                            {(['public', 'private'] as const).map((p) => (
                                <label key={p} className="flex items-center gap-2 capitalize">
                                    <input
                                        type="radio"
                                        name="privacy"
                                        checked={form.data.privacy === p}
                                        onChange={() => form.setData('privacy', p)}
                                    />
                                    {p}
                                </label>
                            ))}
                        </div>
                    </Field>

                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={form.data.requires_approval} onCheckedChange={(v) => form.setData('requires_approval', v === true)} />
                        Admins must approve new members {form.data.privacy === 'private' && '(always on for private communities)'}
                    </label>

                    <Field label="Membership questions (up to 3)" error={form.errors.join_questions}>
                        <div className="space-y-2">
                            {form.data.join_questions.map((q, i) => (
                                <div key={i} className="flex gap-2">
                                    <Input
                                        value={q}
                                        onChange={(e) =>
                                            form.setData(
                                                'join_questions',
                                                form.data.join_questions.map((x, j) => (j === i ? e.target.value : x)),
                                            )
                                        }
                                        maxLength={200}
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() =>
                                            form.setData(
                                                'join_questions',
                                                form.data.join_questions.filter((_, j) => j !== i),
                                            )
                                        }
                                        aria-label="Remove question"
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            ))}
                            {form.data.join_questions.length < 3 && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => form.setData('join_questions', [...form.data.join_questions, ''])}
                                >
                                    <Plus /> Add question
                                </Button>
                            )}
                        </div>
                    </Field>

                    <Button disabled={form.processing}>
                        {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                        Save changes
                    </Button>
                </form>

                <CategoryManager community={community} categories={categories} />
            </div>
        </CommunityLayout>
    );
}

function CategoryManager({ community, categories }: { community: Community; categories: SettingsProps['categories'] }) {
    const form = useForm({ name: '', emoji: '', admin_only: false });

    return (
        <div className="bg-card space-y-4 rounded-xl border p-6 shadow-xs">
            <h2 className="text-lg font-semibold">Categories</h2>

            <ul className="divide-y rounded-lg border">
                {categories.map((c) => (
                    <li key={c.id} className="flex items-center gap-3 px-3 py-2 text-sm">
                        <span className="w-6 text-center">{c.emoji}</span>
                        <span className="flex-1 font-medium">{c.name}</span>
                        <label className="text-muted-foreground flex items-center gap-1.5 text-xs">
                            <Checkbox
                                checked={!!c.admin_only}
                                onCheckedChange={(v) =>
                                    router.patch(
                                        route('categories.update', [community.slug, c.id]),
                                        { name: c.name, emoji: c.emoji, admin_only: v === true },
                                        { preserveScroll: true },
                                    )
                                }
                            />
                            Admins only
                        </label>
                        <span className="text-muted-foreground w-16 text-right text-xs">{plural(c.posts_count, 'post')}</span>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={`Delete ${c.name}`}
                            onClick={() =>
                                confirm(`Delete "${c.name}"? Its posts stay in the feed without a category.`) &&
                                router.delete(route('categories.destroy', [community.slug, c.id]), { preserveScroll: true })
                            }
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </li>
                ))}
            </ul>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('categories.store', community.slug), { preserveScroll: true, onSuccess: () => form.reset() });
                }}
                className="flex flex-wrap items-end gap-2"
            >
                <Field label="Emoji" className="w-20">
                    <Input value={form.data.emoji} onChange={(e) => form.setData('emoji', e.target.value)} maxLength={16} placeholder="💡" />
                </Field>
                <Field label="New category" error={form.errors.name} className="flex-1">
                    <Input
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        maxLength={40}
                        placeholder="Questions"
                        required
                    />
                </Field>
                <Button disabled={form.processing}>
                    <Plus /> Add
                </Button>
            </form>
        </div>
    );
}

function Field({ label, error, className, children }: { label: string; error?: string; className?: string; children: React.ReactNode }) {
    return (
        <div className={`grid gap-2 ${className ?? ''}`}>
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
