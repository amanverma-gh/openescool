import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { cn } from '@/lib/utils';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

type Digest = 'daily' | 'weekly' | 'off';

interface NotificationsProps {
    types: Record<string, string>;
    preferences: Record<string, boolean>;
    digest: Digest;
}

const digestOptions: { value: Digest; title: string; description: string }[] = [
    { value: 'daily', title: 'Daily', description: 'Every morning' },
    { value: 'weekly', title: 'Weekly', description: 'Monday mornings' },
    { value: 'off', title: 'Off', description: 'No digest' },
];

export default function Notifications({ types, preferences, digest }: NotificationsProps) {
    const form = useForm({ preferences, digest });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('notifications.settings.update'), { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="Email settings" />
            <SettingsLayout>
                <form onSubmit={submit} className="space-y-8">
                    <div className="space-y-4">
                        <HeadingSmall title="Digest" description="A summary of popular posts and upcoming events from your communities" />
                        <div className="grid grid-cols-3 gap-2">
                            {digestOptions.map((option) => (
                                <button
                                    type="button"
                                    key={option.value}
                                    onClick={() => form.setData('digest', option.value)}
                                    aria-pressed={form.data.digest === option.value}
                                    className={cn(
                                        'rounded-lg border p-3 text-left text-sm transition',
                                        form.data.digest === option.value ? 'border-brand ring-brand ring-1' : 'hover:bg-muted',
                                    )}
                                >
                                    <div className="font-medium">{option.title}</div>
                                    <div className="text-muted-foreground text-xs">{option.description}</div>
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="space-y-4">
                        <HeadingSmall title="Email me about" description="You'll always see these in your notifications inside the app" />
                        <div className="space-y-3">
                            {Object.entries(types).map(([type, label]) => (
                                <div key={type} className="flex items-center gap-3">
                                    <Checkbox
                                        id={`pref-${type}`}
                                        checked={form.data.preferences[type]}
                                        onCheckedChange={(checked) =>
                                            form.setData('preferences', { ...form.data.preferences, [type]: checked === true })
                                        }
                                    />
                                    <Label htmlFor={`pref-${type}`} className="font-normal">
                                        {label}
                                    </Label>
                                </div>
                            ))}
                        </div>
                        <p className="text-muted-foreground text-xs">Payment receipts and account security emails are always sent.</p>
                    </div>

                    <Button disabled={form.processing}>
                        {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                        Save
                    </Button>
                </form>
            </SettingsLayout>
        </AppLayout>
    );
}
