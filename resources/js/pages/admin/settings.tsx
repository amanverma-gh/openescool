import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, ExternalLink, LoaderCircle, Mail, Settings2 } from 'lucide-react';
import { FormEventHandler } from 'react';

interface SecretState {
    set: boolean;
    hint: string | null;
}

interface AdminSettingsProps {
    giphy: SecretState;
    brevo: SecretState & { from_address: string | null; from_name: string | null; list_id: string | null; mailer: string };
}

export default function AdminSettings({ giphy, brevo }: AdminSettingsProps) {
    return (
        <AppLayout>
            <Head title="Admin settings" />

            <div className="mx-auto max-w-2xl space-y-6">
                <div>
                    <h1 className="mb-1 flex items-center gap-2 text-2xl font-bold">
                        <Settings2 className="size-6" /> Admin settings
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Platform-wide settings. Only platform admins can see this page. Keys are stored encrypted.
                    </p>
                </div>

                <BrevoSection brevo={brevo} />
                <GiphySection giphy={giphy} />
            </div>
        </AppLayout>
    );
}

function BrevoSection({ brevo }: { brevo: AdminSettingsProps['brevo'] }) {
    const form = useForm({
        brevo_api_key: '',
        mail_from_address: brevo.from_address ?? '',
        mail_from_name: brevo.from_name ?? '',
        brevo_members_list_id: brevo.list_id ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.settings.update'), { preserveScroll: true, onSuccess: () => form.setData('brevo_api_key', '') });
    };

    return (
        <section className="bg-card space-y-4 rounded-xl border p-6 shadow-xs">
            <div>
                <h2 className="text-lg font-semibold">Email (Brevo)</h2>
                <p className="text-muted-foreground mt-1 text-sm">
                    Sends every email (sign-up, notifications, digests, post emails) through Brevo and keeps members in your Brevo contacts. Only the
                    API key is needed, no SMTP login.
                </p>
            </div>

            {brevo.set ? (
                <KeyStatus
                    hint={brevo.hint}
                    onRemove={() => remove('brevo_api_key', 'Remove the Brevo key? Email will stop sending through Brevo.')}
                />
            ) : (
                <ol className="text-muted-foreground list-decimal space-y-1 pl-5 text-sm">
                    <li>
                        In Brevo, open{' '}
                        <a href="https://app.brevo.com/settings/keys/api" target="_blank" rel="noopener" className="text-foreground underline">
                            SMTP &amp; API → API keys <ExternalLink className="inline size-3" />
                        </a>{' '}
                        and create a key.
                    </li>
                    <li>
                        Add and verify your sender email under{' '}
                        <a href="https://app.brevo.com/senders" target="_blank" rel="noopener" className="text-foreground underline">
                            Senders <ExternalLink className="inline size-3" />
                        </a>
                        .
                    </li>
                    <li>Fill in the form below, save, then send a test email.</li>
                </ol>
            )}

            <form onSubmit={submit} className="space-y-3">
                <div className="space-y-1">
                    <label htmlFor="brevo_api_key" className="text-sm font-medium">
                        {brevo.set ? 'Replace API key' : 'Brevo API key'}
                    </label>
                    <Input
                        id="brevo_api_key"
                        type="password"
                        autoComplete="off"
                        value={form.data.brevo_api_key}
                        onChange={(e) => form.setData('brevo_api_key', e.target.value.trim())}
                        placeholder={brevo.set ? 'Leave empty to keep the current key' : 'xkeysib-…'}
                    />
                    <InputError message={form.errors.brevo_api_key} />
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1">
                        <label htmlFor="mail_from_address" className="text-sm font-medium">
                            Sender email
                        </label>
                        <Input
                            id="mail_from_address"
                            type="email"
                            value={form.data.mail_from_address}
                            onChange={(e) => form.setData('mail_from_address', e.target.value)}
                            required
                        />
                        <InputError message={form.errors.mail_from_address} />
                    </div>
                    <div className="space-y-1">
                        <label htmlFor="mail_from_name" className="text-sm font-medium">
                            Sender name
                        </label>
                        <Input
                            id="mail_from_name"
                            value={form.data.mail_from_name}
                            onChange={(e) => form.setData('mail_from_name', e.target.value)}
                            required
                        />
                        <InputError message={form.errors.mail_from_name} />
                    </div>
                </div>
                <div className="space-y-1">
                    <label htmlFor="brevo_members_list_id" className="text-sm font-medium">
                        Members list ID <span className="text-muted-foreground font-normal">(optional)</span>
                    </label>
                    <Input
                        id="brevo_members_list_id"
                        inputMode="numeric"
                        value={form.data.brevo_members_list_id}
                        onChange={(e) => form.setData('brevo_members_list_id', e.target.value.replace(/\D/g, ''))}
                        placeholder="e.g. 3"
                        className="w-40"
                    />
                    <p className="text-muted-foreground text-xs">New members are added to this Brevo list (Contacts → Lists).</p>
                    <InputError message={form.errors.brevo_members_list_id} />
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Button disabled={form.processing}>
                        {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                        Save
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => router.post(route('admin.settings.test-email'), {}, { preserveScroll: true })}
                    >
                        <Mail /> Send test email
                    </Button>
                    {brevo.mailer === 'log' && <span className="text-muted-foreground text-xs">Email is in log mode until a key is saved.</span>}
                </div>
            </form>
        </section>
    );
}

function GiphySection({ giphy }: { giphy: SecretState }) {
    const form = useForm({ giphy_api_key: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.settings.update'), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <section className="bg-card space-y-4 rounded-xl border p-6 shadow-xs">
            <div>
                <h2 className="text-lg font-semibold">GIF picker (GIPHY)</h2>
                <p className="text-muted-foreground mt-1 text-sm">
                    Lets members search and add GIFs, like in WhatsApp. Results are rated PG-13 or lower. Each community still decides whether to
                    allow GIFs in its settings.
                </p>
            </div>

            {giphy.set ? (
                <KeyStatus hint={giphy.hint} onRemove={() => remove('giphy_api_key', 'Remove the GIPHY key? The GIF picker will stop working.')} />
            ) : (
                <ol className="text-muted-foreground list-decimal space-y-1 pl-5 text-sm">
                    <li>
                        Create a free account at{' '}
                        <a href="https://developers.giphy.com/dashboard/" target="_blank" rel="noopener" className="text-foreground underline">
                            developers.giphy.com <ExternalLink className="inline size-3" />
                        </a>
                    </li>
                    <li>Create an app, choose “API”, and copy its API key.</li>
                    <li>Paste it below.</li>
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
    );
}

function KeyStatus({ hint, onRemove }: { hint: string | null; onRemove: () => void }) {
    return (
        <div className="flex items-center justify-between gap-3 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">
            <span className="flex items-center gap-2">
                <CheckCircle2 className="size-4" /> Key saved ({hint})
            </span>
            <Button variant="outline" size="sm" onClick={onRemove}>
                Remove
            </Button>
        </div>
    );
}

function remove(key: string, question: string) {
    if (confirm(question)) {
        router.post(route('admin.settings.update'), { remove: key }, { preserveScroll: true });
    }
}
