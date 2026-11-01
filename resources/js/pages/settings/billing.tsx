import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { formatMoney, intervalLabel } from '@/lib/utils';
import { type Plan } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { CreditCard } from 'lucide-react';

interface BillingProps {
    subscriptions: {
        id: number;
        community: { name: string; slug: string; icon: string | null };
        plan: Pick<Plan, 'name' | 'interval' | 'amount' | 'currency'> | null;
        gateway: 'stripe' | 'razorpay';
        status: 'active' | 'past_due' | 'ended';
        lifetime: boolean;
        cancel_at_period_end: boolean;
        current_period_end: string | null;
        ended_at: string | null;
    }[];
    payments: {
        id: number;
        amount: number;
        currency: string;
        description: string | null;
        paid_at: string;
        community: string;
        gateway_payment_id: string;
    }[];
    hasStripeCustomer: boolean;
}

const date = (value: string) => new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });

export default function Billing({ subscriptions, payments, hasStripeCustomer }: BillingProps) {
    const cancel = (id: number, name: string) => {
        if (confirm(`Cancel your ${name} membership? You keep access until the end of the paid period.`)) {
            router.post(route('billing.cancel', id), {}, { preserveScroll: true });
        }
    };

    return (
        <AppLayout>
            <Head title="Billing" />
            <SettingsLayout>
                <div className="space-y-6">
                    <div className="flex items-start justify-between gap-4">
                        <HeadingSmall title="Memberships" description="Paid communities you belong to" />
                        {hasStripeCustomer && (
                            <Button variant="outline" size="sm" onClick={() => router.post(route('billing.portal'))}>
                                <CreditCard className="size-4" />
                                Payment methods
                            </Button>
                        )}
                    </div>

                    {subscriptions.length === 0 ? (
                        <p className="text-muted-foreground text-sm">You don't have any paid memberships.</p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {subscriptions.map((s) => (
                                <li key={s.id} className="flex flex-wrap items-center gap-3 p-4">
                                    <div className="min-w-0 flex-1">
                                        <Link href={route('communities.feed', s.community.slug)} className="font-medium hover:underline">
                                            {s.community.name}
                                        </Link>
                                        <div className="text-muted-foreground text-sm">
                                            {s.plan
                                                ? `${s.plan.name} · ${formatMoney(s.plan.amount, s.plan.currency)}${intervalLabel(s.plan.interval)}`
                                                : 'Membership'}
                                        </div>
                                        <div className="text-muted-foreground text-xs">{statusLine(s)}</div>
                                    </div>
                                    {s.status !== 'ended' && !s.lifetime && !s.cancel_at_period_end && (
                                        <Button variant="ghost" size="sm" onClick={() => cancel(s.id, s.community.name)}>
                                            Cancel
                                        </Button>
                                    )}
                                    {s.status !== 'ended' && s.cancel_at_period_end && s.gateway === 'stripe' && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.post(route('billing.resume', s.id), {}, { preserveScroll: true })}
                                        >
                                            Resume
                                        </Button>
                                    )}
                                    {s.status === 'ended' && (
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={route('billing.checkout', s.community.slug)}>Rejoin</Link>
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}

                    <HeadingSmall title="Receipts" description="Your last 50 payments" />
                    {payments.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No payments yet.</p>
                    ) : (
                        <ul className="divide-y rounded-lg border text-sm">
                            {payments.map((p) => (
                                <li key={p.id} className="flex items-center gap-3 px-4 py-3">
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate font-medium">{p.community}</div>
                                        <div className="text-muted-foreground truncate text-xs">
                                            {date(p.paid_at)} · {p.description ?? 'Membership'} · Ref {p.gateway_payment_id}
                                        </div>
                                    </div>
                                    <div className="font-medium whitespace-nowrap">{formatMoney(p.amount, p.currency)}</div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

function statusLine(s: BillingProps['subscriptions'][number]): string {
    if (s.status === 'ended') return `Ended ${s.ended_at ? date(s.ended_at) : ''}`;
    if (s.lifetime) return 'Lifetime access';
    if (s.status === 'past_due') return 'Payment failed. Update your payment method to keep access.';
    if (s.cancel_at_period_end && s.current_period_end) return `Cancelled · access until ${date(s.current_period_end)}`;
    return s.current_period_end ? `Renews ${date(s.current_period_end)}` : 'Active';
}
