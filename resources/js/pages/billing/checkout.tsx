import { CommunityIcon } from '@/components/community/top-bar';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatMoney, intervalLabel } from '@/lib/utils';
import { type Community, type Plan } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check, LoaderCircle, Lock } from 'lucide-react';
import { useState } from 'react';

interface CheckoutProps {
    community: Community & { about: string | null };
    plans: (Plan & { available: boolean })[];
}

type RazorpayResponse = Record<string, string>;

declare global {
    interface Window {
        Razorpay?: new (options: Record<string, unknown>) => { open: () => void; on: (event: string, cb: () => void) => void };
    }
}

function loadRazorpay(): Promise<void> {
    if (window.Razorpay) return Promise.resolve();

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://checkout.razorpay.com/v1/checkout.js';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Could not load Razorpay.'));
        document.body.appendChild(script);
    });
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export default function Checkout({ community, plans }: CheckoutProps) {
    const { errors } = usePage().props;
    const [busy, setBusy] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);

    const buy = async (plan: Plan) => {
        setError(null);
        setBusy(plan.id);
        const url = route('billing.checkout.store', [community.slug, plan.id]);

        if (plan.gateway === 'stripe') {
            // Server answers with a redirect to Stripe's hosted page.
            router.post(url, {}, { onFinish: () => setBusy(null) });
            return;
        }

        try {
            const [response] = await Promise.all([
                fetch(url, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
                }),
                loadRazorpay(),
            ]);
            const options = await response.json();
            if (!response.ok) throw new Error(options.message ?? 'Could not start the payment.');

            const checkout = new window.Razorpay!({
                ...options,
                theme: { color: getComputedStyle(document.documentElement).getPropertyValue('--brand').trim() || undefined },
                handler: (result: RazorpayResponse) => router.post(route('billing.razorpay.confirm', community.slug), result),
                modal: { ondismiss: () => setBusy(null) },
            });
            checkout.on('payment.failed', () => setError('The payment failed. No money was taken; please try again.'));
            checkout.open();
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Could not start the payment.');
            setBusy(null);
        }
    };

    return (
        <AppLayout community={community}>
            <Head title={`Join ${community.name}`} />

            <div className="mx-auto max-w-3xl py-6">
                <div className="mb-8 text-center">
                    <CommunityIcon community={community} className="mx-auto mb-3 size-14" />
                    <h1 className="text-2xl font-bold">Join {community.name}</h1>
                    {community.about && <p className="text-muted-foreground mt-1">{community.about}</p>}
                </div>

                {plans.length === 0 ? (
                    <p className="bg-card text-muted-foreground rounded-xl border p-8 text-center">
                        Membership isn't open right now. Check back soon.
                    </p>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2">
                        {plans.map((plan) => (
                            <div key={plan.id} className="bg-card flex flex-col rounded-xl border p-6 shadow-xs">
                                <div className="text-muted-foreground text-sm font-medium">{plan.name}</div>
                                <div className="mt-2 flex items-baseline gap-1">
                                    <span className="text-3xl font-bold">{formatMoney(plan.amount, plan.currency)}</span>
                                    <span className="text-muted-foreground text-sm">{intervalLabel(plan.interval)}</span>
                                </div>
                                <ul className="text-muted-foreground mt-4 flex-1 space-y-2 text-sm">
                                    <Perk>Full access to the community, classroom and calendar</Perk>
                                    <Perk>{plan.interval === 'one_time' ? 'Pay once, keep access' : 'Cancel any time'}</Perk>
                                    {plan.gateway === 'razorpay' && <Perk>UPI, cards, netbanking and wallets</Perk>}
                                </ul>
                                <Button className="mt-6 w-full" size="lg" disabled={!plan.available || busy !== null} onClick={() => buy(plan)}>
                                    {busy === plan.id && <LoaderCircle className="size-4 animate-spin" />}
                                    {plan.available ? 'Continue to payment' : 'Unavailable'}
                                </Button>
                            </div>
                        ))}
                    </div>
                )}

                {(error || errors.payment) && <p className="mt-4 text-center text-sm text-red-600">{error ?? errors.payment}</p>}

                <p className="text-muted-foreground mt-8 flex items-center justify-center gap-1.5 text-xs">
                    <Lock className="size-3.5" />
                    Secure payment by {plans.some((p) => p.gateway === 'razorpay') ? 'Stripe and Razorpay' : 'Stripe'}. We never see your card.
                </p>
                <p className="mt-2 text-center text-sm">
                    <Link href={route('communities.about', community.slug)} className="text-muted-foreground hover:underline">
                        Back to {community.name}
                    </Link>
                </p>
            </div>
        </AppLayout>
    );
}

function Perk({ children }: { children: React.ReactNode }) {
    return (
        <li className="flex gap-2">
            <Check className="text-brand mt-0.5 size-4 shrink-0" />
            <span>{children}</span>
        </li>
    );
}
