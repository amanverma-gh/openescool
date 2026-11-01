import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';
import { useState } from 'react';

interface UnsubscribeProps {
    label: string;
    action: string | null;
    done: boolean;
}

export default function Unsubscribe({ label, action, done }: UnsubscribeProps) {
    const { auth } = usePage<SharedData>().props;
    const [processing, setProcessing] = useState(false);

    return (
        <AppLayout>
            <Head title="Unsubscribe" />
            <div className="bg-card mx-auto mt-10 max-w-md space-y-4 rounded-xl border p-8 text-center shadow-xs">
                <MailCheck className="text-brand mx-auto size-10" />
                {done ? (
                    <>
                        <h1 className="text-xl font-semibold">You're unsubscribed</h1>
                        <p className="text-muted-foreground text-sm">We won't send you {label} any more.</p>
                    </>
                ) : (
                    <>
                        <h1 className="text-xl font-semibold">Unsubscribe?</h1>
                        <p className="text-muted-foreground text-sm">Stop receiving {label}.</p>
                        <Button
                            className="w-full"
                            disabled={processing}
                            onClick={() =>
                                action && router.post(action, {}, { onStart: () => setProcessing(true), onFinish: () => setProcessing(false) })
                            }
                        >
                            Unsubscribe
                        </Button>
                    </>
                )}
                <p className="text-sm">
                    {auth.user ? (
                        <Link href={route('notifications.settings')} className="text-muted-foreground hover:underline">
                            Manage all email settings
                        </Link>
                    ) : (
                        <Link href={route('login')} className="text-muted-foreground hover:underline">
                            Log in to manage all email settings
                        </Link>
                    )}
                </p>
            </div>
        </AppLayout>
    );
}
