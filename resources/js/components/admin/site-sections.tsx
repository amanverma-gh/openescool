import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowUpCircle, CheckCircle2, ExternalLink, LifeBuoy, LoaderCircle, RefreshCw, Terminal } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

export interface SiteSettings {
    name: string;
    logo: string | null;
    seo_index: boolean;
}

export interface HomeSettings {
    mode: 'discover' | 'community';
    community_id: number | null;
    headline: string;
    subtext: string;
    button: string;
    show_communities: boolean;
    communities_title: string;
    community_ids: number[];
}

export interface UpdateRun {
    running: boolean;
    ok: boolean | null;
    step: string | null;
    finished_at: string | null;
    log: string;
}

export interface UpdateState {
    current: string;
    latest: string | null;
    available: boolean;
    notes: string | null;
    url: string | null;
    checked: boolean;
    blocker: string | null;
    run: UpdateRun;
}

type CommunityOption = { id: number; name: string; privacy: string };

const sectionClass = 'bg-card space-y-4 rounded-xl border p-6 shadow-xs';

export function HelpSection({ url }: { url: string }) {
    return (
        <section className={`${sectionClass} flex flex-wrap items-center gap-4`}>
            <LifeBuoy className="text-brand size-8 shrink-0" />
            <div className="min-w-0 flex-1">
                <h2 className="text-lg font-semibold">Need help?</h2>
                <p className="text-muted-foreground text-sm">Questions, bugs or ideas for OpenEscool? Ask the OpenEscool team.</p>
            </div>
            <Button asChild variant="outline">
                <a href={url} target="_blank" rel="noopener">
                    Get help <ExternalLink />
                </a>
            </Button>
        </section>
    );
}

export function BrandingSection({ site }: { site: SiteSettings }) {
    const form = useForm({ site_name: site.name, site_logo: null as File | null, remove_logo: false });
    const [preview, setPreview] = useState<string | null>(site.logo);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.settings.site'), { preserveScroll: true, forceFormData: true, onSuccess: () => form.setData('site_logo', null) });
    };

    return (
        <section className={sectionClass}>
            <div>
                <h2 className="text-lg font-semibold">Site name and logo</h2>
                <p className="text-muted-foreground mt-1 text-sm">Shown in the top bar, on sign-in pages, in browser tabs and in emails.</p>
            </div>
            <form onSubmit={submit} className="space-y-3">
                <div className="space-y-1">
                    <label htmlFor="site_name" className="text-sm font-medium">
                        Site name
                    </label>
                    <Input
                        id="site_name"
                        value={form.data.site_name}
                        onChange={(e) => form.setData('site_name', e.target.value)}
                        maxLength={40}
                        required
                    />
                    <InputError message={form.errors.site_name} />
                </div>
                <div className="space-y-1">
                    <span className="text-sm font-medium">Logo</span>
                    <div className="flex items-center gap-3">
                        {preview ? (
                            <img src={preview} alt="" className="size-12 rounded-lg border object-cover" />
                        ) : (
                            <span className="bg-foreground text-background flex size-12 items-center justify-center rounded-lg text-lg font-bold">
                                {form.data.site_name.charAt(0) || '?'}
                            </span>
                        )}
                        <Input
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            className="max-w-xs"
                            onChange={(e) => {
                                const file = e.target.files?.[0] ?? null;
                                form.setData((d) => ({ ...d, site_logo: file, remove_logo: false }));
                                setPreview(file ? URL.createObjectURL(file) : site.logo);
                            }}
                        />
                        {preview && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    form.setData((d) => ({ ...d, site_logo: null, remove_logo: true }));
                                    setPreview(null);
                                }}
                            >
                                Remove
                            </Button>
                        )}
                    </div>
                    <p className="text-muted-foreground text-xs">
                        Square PNG, JPG or WebP under 1 MB. Without a logo, the first letter of the name is shown.
                    </p>
                    <InputError message={form.errors.site_logo} />
                </div>
                <Button disabled={form.processing}>
                    {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                    Save
                </Button>
            </form>
        </section>
    );
}

export function HomePageSection({ home, communities }: { home: HomeSettings; communities: CommunityOption[] }) {
    const form = useForm({
        home_mode: home.mode,
        home_community_id: home.community_id ? String(home.community_id) : '',
        home_headline: home.headline,
        home_subtext: home.subtext,
        home_button: home.button,
        home_show_communities: home.show_communities,
        home_communities_title: home.communities_title,
        home_community_ids: home.community_ids,
    });
    const publicCommunities = communities.filter((c) => c.privacy === 'public');

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, home_community_id: d.home_mode === 'community' ? d.home_community_id || null : null }));
        form.post(route('admin.settings.site'), { preserveScroll: true });
    };

    const toggleFeatured = (id: number, on: boolean) =>
        form.setData('home_community_ids', on ? [...form.data.home_community_ids, id] : form.data.home_community_ids.filter((x) => x !== id));

    return (
        <section className={sectionClass}>
            <div>
                <h2 className="text-lg font-semibold">Home page</h2>
                <p className="text-muted-foreground mt-1 text-sm">What people see when they open your main website address.</p>
            </div>
            <form onSubmit={submit} className="space-y-4">
                <div className="space-y-2">
                    <label className="flex items-start gap-2 text-sm">
                        <input
                            type="radio"
                            name="home_mode"
                            className="mt-1"
                            checked={form.data.home_mode === 'discover'}
                            onChange={() => form.setData('home_mode', 'discover')}
                        />
                        <span>
                            <span className="font-medium">Discover page</span>
                            <span className="text-muted-foreground block text-xs">A welcome message and a list of communities.</span>
                        </span>
                    </label>
                    <label className="flex items-start gap-2 text-sm">
                        <input
                            type="radio"
                            name="home_mode"
                            className="mt-1"
                            checked={form.data.home_mode === 'community'}
                            onChange={() => form.setData('home_mode', 'community')}
                        />
                        <span>
                            <span className="font-medium">Open one community</span>
                            <span className="text-muted-foreground block text-xs">
                                Visitors land on its About page (where they can join); members go straight to its feed.
                            </span>
                        </span>
                    </label>
                </div>

                {form.data.home_mode === 'community' ? (
                    <div className="space-y-1">
                        <label htmlFor="home_community_id" className="text-sm font-medium">
                            Community
                        </label>
                        <select
                            id="home_community_id"
                            value={form.data.home_community_id}
                            onChange={(e) => form.setData('home_community_id', e.target.value)}
                            className="border-input bg-background block h-9 w-full rounded-md border px-2 text-sm"
                        >
                            <option value="">Choose a community</option>
                            {communities.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                    {c.privacy === 'private' ? ' (private)' : ''}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.home_community_id} />
                    </div>
                ) : (
                    <div className="space-y-3 border-l-2 pl-4">
                        <p className="text-muted-foreground text-xs">Leave a box empty to use the default text.</p>
                        <TextField
                            label="Heading"
                            value={form.data.home_headline}
                            onChange={(v) => form.setData('home_headline', v)}
                            max={120}
                            error={form.errors.home_headline}
                        />
                        <div className="space-y-1">
                            <label htmlFor="home_subtext" className="text-sm font-medium">
                                Text under the heading
                            </label>
                            <Textarea
                                id="home_subtext"
                                value={form.data.home_subtext}
                                onChange={(e) => form.setData('home_subtext', e.target.value)}
                                maxLength={300}
                                rows={2}
                            />
                            <InputError message={form.errors.home_subtext} />
                        </div>
                        <TextField
                            label="Sign-up button"
                            value={form.data.home_button}
                            onChange={(v) => form.setData('home_button', v)}
                            max={40}
                            error={form.errors.home_button}
                        />
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.home_show_communities}
                                onCheckedChange={(v) => form.setData('home_show_communities', v === true)}
                            />
                            Show a list of communities
                        </label>
                        {form.data.home_show_communities && (
                            <div className="ml-6 space-y-3">
                                <TextField
                                    label="List title"
                                    value={form.data.home_communities_title}
                                    onChange={(v) => form.setData('home_communities_title', v)}
                                    max={60}
                                    error={form.errors.home_communities_title}
                                />
                                {publicCommunities.length > 0 && (
                                    <div className="space-y-1">
                                        <span className="text-sm font-medium">Which communities</span>
                                        <p className="text-muted-foreground text-xs">
                                            None ticked shows every public community, most members first. Ticked ones show in the order you tick them.
                                        </p>
                                        {publicCommunities.map((c) => (
                                            <label key={c.id} className="flex items-center gap-2 text-sm">
                                                <Checkbox
                                                    checked={form.data.home_community_ids.includes(c.id)}
                                                    onCheckedChange={(v) => toggleFeatured(c.id, v === true)}
                                                />
                                                {c.name}
                                            </label>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-2">
                    <Button disabled={form.processing}>
                        {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                        Save
                    </Button>
                    <Button type="button" variant="ghost" asChild>
                        <a href={route('home')} target="_blank" rel="noopener">
                            View home page <ExternalLink />
                        </a>
                    </Button>
                </div>
            </form>
        </section>
    );
}

export function SeoSection({ site }: { site: SiteSettings }) {
    const form = useForm({ seo_index: site.seo_index });

    return (
        <section className={sectionClass}>
            <div>
                <h2 className="text-lg font-semibold">Search engines (SEO)</h2>
                <p className="text-muted-foreground mt-1 text-sm">
                    Whether Google and other search engines may show this site. Each community can then choose for itself, and for its posts, in its
                    own settings. Private communities and personal pages are never indexed.
                </p>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('admin.settings.site'), { preserveScroll: true });
                }}
                className="space-y-3"
            >
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox checked={form.data.seo_index} onCheckedChange={(v) => form.setData('seo_index', v === true)} />
                    Allow search engines to index this site
                </label>
                {!form.data.seo_index && (
                    <p className="text-muted-foreground text-xs">Every page gets “noindex” and robots.txt asks search engines to stay away.</p>
                )}
                <div className="flex flex-wrap items-center gap-2">
                    <Button disabled={form.processing}>Save</Button>
                    <a href={route('sitemap')} target="_blank" rel="noopener" className="text-muted-foreground text-xs underline">
                        sitemap.xml
                    </a>
                    <a href={route('robots')} target="_blank" rel="noopener" className="text-muted-foreground text-xs underline">
                        robots.txt
                    </a>
                </div>
            </form>
        </section>
    );
}

export function UpdatesSection({ update }: { update: UpdateState }) {
    const [run, setRun] = useState<UpdateRun>(update.run);
    const [confirmed, setConfirmed] = useState(false);
    const [starting, setStarting] = useState(false);

    // Follow a running update. The site is briefly in maintenance mode, so failed polls just retry.
    useEffect(() => {
        if (!run.running) return;
        const timer = setInterval(() => {
            fetch(route('admin.update.status'), { headers: { Accept: 'application/json' } })
                .then((r) => (r.ok ? r.json() : null))
                .then((status: UpdateRun | null) => status && setRun(status))
                .catch(() => {});
        }, 3000);
        return () => clearInterval(timer);
    }, [run.running]);

    const start = () =>
        router.post(
            route('admin.update.run'),
            { backup_confirmed: confirmed },
            {
                preserveScroll: true,
                onStart: () => setStarting(true),
                onFinish: () => setStarting(false),
                onSuccess: () => setRun({ running: true, ok: null, step: 'Starting', finished_at: null, log: '' }),
            },
        );

    return (
        <section className={sectionClass}>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-lg font-semibold">Updates</h2>
                    <p className="text-muted-foreground mt-1 text-sm">
                        You are on version <strong>{update.current}</strong>.
                    </p>
                </div>
                <Button variant="outline" size="sm" onClick={() => router.post(route('admin.update.check'), {}, { preserveScroll: true })}>
                    <RefreshCw /> Check for updates
                </Button>
            </div>

            {update.available ? (
                <div className="rounded-lg bg-sky-50 p-4 text-sm text-sky-950 dark:bg-sky-950 dark:text-sky-50">
                    <p className="flex items-center gap-2 font-semibold">
                        <ArrowUpCircle className="size-4" /> Version {update.latest} is available
                    </p>
                    {update.notes && <p className="mt-2 line-clamp-6 whitespace-pre-line opacity-90">{update.notes}</p>}
                    {update.url && (
                        <a href={update.url} target="_blank" rel="noopener" className="mt-2 inline-flex items-center gap-1 underline">
                            What changed <ExternalLink className="size-3" />
                        </a>
                    )}
                </div>
            ) : (
                update.checked && (
                    <p className="flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-400">
                        <CheckCircle2 className="size-4" /> You have the latest version.
                    </p>
                )
            )}

            <div className="space-y-2">
                <h3 className="flex items-center gap-2 text-sm font-semibold">
                    <Terminal className="size-4" /> Safe way: update with a command (recommended)
                </h3>
                <p className="text-muted-foreground text-sm">Back up first, then on your server, in the site folder, run:</p>
                <pre className="bg-muted overflow-x-auto rounded-md px-3 py-2 text-xs">php artisan app:update</pre>
                <p className="text-muted-foreground text-xs">
                    It downloads the new code, installs packages, updates the database and rebuilds the site, and shows every step, so you can see and
                    fix any problem.
                </p>
            </div>

            <div className="space-y-3 rounded-lg border border-red-200 p-4 dark:border-red-900">
                <h3 className="text-sm font-semibold">One-click update</h3>
                <div className="flex gap-2 rounded-md bg-red-50 p-3 text-sm text-red-900 dark:bg-red-950 dark:text-red-100">
                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                    <div className="space-y-1">
                        <p className="font-semibold">Warning: an update can fail and crash your site.</p>
                        <p>
                            Please take a full backup (database and files) before you update. If something goes wrong in the middle, the site may stop
                            working until you restore the backup. Updating with the command above is safer.
                        </p>
                    </div>
                </div>

                {update.blocker ? (
                    <p className="text-muted-foreground text-sm">{update.blocker}</p>
                ) : (
                    <>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={confirmed} onCheckedChange={(v) => setConfirmed(v === true)} disabled={run.running} />I have taken a
                            backup and understand the risk
                        </label>
                        <Button variant="destructive" disabled={!confirmed || run.running || starting} onClick={start}>
                            {(run.running || starting) && <LoaderCircle className="size-4 animate-spin" />}
                            {run.running ? 'Updating…' : 'Update now'}
                        </Button>
                    </>
                )}

                {(run.running || run.log) && (
                    <div className="space-y-1">
                        <p className="text-sm">
                            {run.running
                                ? `Working: ${run.step ?? 'starting'}… The site shows a short maintenance page meanwhile.`
                                : run.ok
                                  ? 'Last update finished successfully. Reload the page to use the new version.'
                                  : `Last update failed${run.step ? ` (${run.step})` : ''}. Read the log below; restore your backup if the site is broken.`}
                        </p>
                        {run.log && <pre className="bg-muted max-h-64 overflow-auto rounded-md px-3 py-2 text-xs whitespace-pre-wrap">{run.log}</pre>}
                    </div>
                )}
            </div>
        </section>
    );
}

function TextField({
    label,
    value,
    onChange,
    max,
    error,
}: {
    label: string;
    value: string;
    onChange: (v: string) => void;
    max: number;
    error?: string;
}) {
    const id = `field-${label.toLowerCase().replace(/\W+/g, '-')}`;

    return (
        <div className="space-y-1">
            <label htmlFor={id} className="text-sm font-medium">
                {label}
            </label>
            <Input id={id} value={value} onChange={(e) => onChange(e.target.value)} maxLength={max} />
            <InputError message={error} />
        </div>
    );
}
