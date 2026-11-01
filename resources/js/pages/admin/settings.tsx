import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, ExternalLink, LoaderCircle, Settings2 } from 'lucide-react';
import { FormEventHandler } from 'react';

interface AdminSettingsProps {
    giphy: { set: boolean; hint: string | null };
}

export default function AdminSettings({ giphy }: AdminSettingsProps) {
    const form = useForm({ giphy_api_key: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.settings.update'), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <AppLayout>
            <Head title="Admin settings" />

            <div className="mx-auto max-w-2xl">
                <h1 className="mb-1 flex items-center gap-2 text-2xl font-bold">
                    <Settings2 className="size-6" /> Admin settings
                </h1>
                <p className="text-muted-foreground mb-6 text-sm">Platform-wide settings. Only platform admins can see this page.</p>

                <section className="bg-card space-y-4 rounded-xl border p-6 shadow-xs">
                    <div>
                        <h2 className="text-lg font-semibold">GIF picker (GIPHY)</h2>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Lets members search and add GIFs, like in WhatsApp. Results are rated PG-13 or lower. Each community still decides whether
                            to allow GIFs in its settings.
                        </p>
                    </div>

                    {giphy.set ? (
                        <div className="flex items-center justify-between gap-3 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">
                            <span className="flex items-center gap-2">
                                <CheckCircle2 className="size-4" /> Key saved ({giphy.hint})
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    if (confirm('Remove the GIPHY key? The GIF picker will stop working.')) {
                                        router.post(route('admin.settings.update'), { remove_giphy: true }, { preserveScroll: true });
                                    }
                                }}
                            >
                                Remove
                            </Button>
                        </div>
                    ) : (
                        <ol className="text-muted-foreground list-decimal space-y-1 pl-5 text-sm">
                            <li>
                                Create a free account at{' '}
                                <a
                                    href="https://developers.giphy.com/dashboard/"
                                    target="_blank"
                                    rel="noopener"
                                    className="text-foreground underline"
                                >
                                    developers.giphy.com <ExternalLink className="inline size-3" />
                                </a>
                            </li>
                            <li>Create an app, choose “API”, and copy its API key.</li>
                            <li>Paste it below. It is stored encrypted and never shown in full again.</li>
                        </ol>
                    )}

                    <form onSubmit={submit} className="space-y-2">
                        <label htmlFor="giphy_api_key" className="text-sm font-medium">
                            {giphy.set ? 'Replace key' : 'GIPHY API key'}
                        </label>
                        <div className="flex gap-2">
                            <Input
                                id="giphy_api_key"
                                type="password"
                                autoComplete="off"
                                value={form.data.giphy_api_key}
                                onChange={(e) => form.setData('giphy_api_key', e.target.value.trim())}
                                placeholder="Paste your key"
                            />
                            <Button disabled={form.processing || !form.data.giphy_api_key}>
                                {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                                Save
                            </Button>
                        </div>
                        <InputError message={form.errors.giphy_api_key} />
                    </form>
                </section>
            </div>
        </AppLayout>
    );
}
