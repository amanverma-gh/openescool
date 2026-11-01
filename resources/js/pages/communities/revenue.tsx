import { UserAvatar } from '@/components/community/user-avatar';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CommunityLayout from '@/layouts/community-layout';
import { cn, formatMoney, intervalLabel, plural } from '@/lib/utils';
import { type Community, type Paginated, type Plan, type PublicUser } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Eye, EyeOff, LoaderCircle, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

interface RevenueProps {
    community: Community;
    accessType: 'free' | 'paid';
    plans: (Plan & { active_count: number })[];
    currencies: string[];
    gateways: { stripe: boolean; razorpay: boolean };
    stats: {
        mrr: Record<string, number>;
        last30: Record<string, number>;
        paying: number;
        canceling: number;
    };
    payments: Paginated<{
        id: number;
        amount: number;
        currency: string;
        description: string | null;
        paid_at: string;
        gateway: string;
        user: PublicUser | null;
    }>;
}

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none';

export default function Revenue({ community, accessType, plans, currencies, gateways, stats, payments }: RevenueProps) {
    const form = useForm({ name: 'Monthly', interval: 'month', price: '', currency: 'USD' });

    const addPlan: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('plans.store', community.slug), { preserveScroll: true, onSuccess: () => form.reset('price') });
    };

    const setAccess = (access_type: 'free' | 'paid') =>
        router.post(route('communities.access', community.slug), { access_type }, { preserveScroll: true });

    const noGateway = !gateways.stripe && !gateways.razorpay;
    const inrWithoutRazorpay = form.data.currency === 'INR' && !gateways.razorpay;

    return (
        <CommunityLayout community={community}>
            <Head title={`Revenue · ${community.name}`} />

            <div className="mx-auto max-w-4xl space-y-6">
                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard label="Monthly recurring revenue" values={stats.mrr} />
                    <StatCard label="Last 30 days" values={stats.last30} />
                    <div className="bg-card rounded-xl border p-5 shadow-xs">
                        <div className="text-muted-foreground text-sm">Paying members</div>
                        <div className="mt-1 text-2xl font-bold">{stats.paying}</div>
                        {stats.canceling > 0 && <div className="text-muted-foreground text-xs">{stats.canceling} canceling at period end</div>}
                    </div>
                </div>

                {noGateway && (
                    <div className="flex gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                        <p>
                            No payment gateway is connected yet. Add your Stripe keys (and Razorpay keys for INR) to the <code>.env</code> file on the
                            server. Members can't pay until then.
                        </p>
                    </div>
                )}

                <section className="bg-card space-y-4 rounded-xl border p-6 shadow-xs">
                    <div>
                        <h2 className="text-lg font-semibold">Access</h2>
                        <p className="text-muted-foreground text-sm">Existing members keep their access when you switch to paid.</p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        {(['free', 'paid'] as const).map((type) => (
                            <button
                                key={type}
                                onClick={() => type !== accessType && setAccess(type)}
                                className={cn(
                                    'rounded-lg border p-4 text-left transition',
                                    accessType === type ? 'border-brand ring-brand ring-1' : 'hover:bg-muted',
                                )}
                            >
                                <div className="font-medium">{type === 'free' ? 'Free' : 'Paid'}</div>
                                <div className="text-muted-foreground text-sm">
                                    {type === 'free' ? 'Anyone can join (or request to join).' : 'New members pay with one of your plans.'}
                                </div>
                            </button>
                        ))}
                    </div>
                </section>

                <section className="bg-card space-y-4 rounded-xl border p-6 shadow-xs">
                    <div>
                        <h2 className="text-lg font-semibold">Plans</h2>
                        <p className="text-muted-foreground text-sm">
                            INR plans use Razorpay (UPI, Indian cards) when it's connected; every other currency uses Stripe.
                        </p>
                    </div>

                    {plans.length > 0 && (
                        <ul className="divide-y rounded-lg border">
                            {plans.map((plan) => (
                                <li key={plan.id} className={cn('flex items-center gap-3 px-4 py-3', !plan.is_active && 'opacity-60')}>
                                    <div className="min-w-0 flex-1">
                                        <div className="font-medium">
                                            {plan.name}
                                            {!plan.is_active && <span className="text-muted-foreground ml-2 text-xs">(hidden)</span>}
                                        </div>
                                        <div className="text-muted-foreground text-sm">
                                            {formatMoney(plan.amount, plan.currency)}
                                            {intervalLabel(plan.interval)} · {plan.gateway === 'razorpay' ? 'Razorpay' : 'Stripe'} ·{' '}
                                            {plural(plan.active_count, 'subscriber')}
                                        </div>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        title={plan.is_active ? 'Hide from new members' : 'Show to new members'}
                                        onClick={() =>
                                            router.patch(
                                                route('plans.update', [community.slug, plan.id]),
                                                { is_active: !plan.is_active },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {plan.is_active ? <Eye className="size-4" /> : <EyeOff className="size-4" />}
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        title="Delete plan"
                                        onClick={() =>
                                            confirm(`Delete the ${plan.name} plan?`) &&
                                            router.delete(route('plans.destroy', [community.slug, plan.id]), { preserveScroll: true })
                                        }
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}

                    <form onSubmit={addPlan} className="grid gap-3 sm:grid-cols-[1fr_1fr_1fr_110px_auto] sm:items-end">
                        <div className="grid gap-1.5">
                            <Label htmlFor="plan-name">Name</Label>
                            <Input
                                id="plan-name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                maxLength={60}
                                required
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="plan-interval">Billing</Label>
                            <select
                                id="plan-interval"
                                className={selectClass}
                                value={form.data.interval}
                                onChange={(e) => form.setData('interval', e.target.value)}
                            >
                                <option value="month">Monthly</option>
                                <option value="year">Yearly</option>
                                <option value="one_time">One-time (lifetime)</option>
                            </select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="plan-price">Price</Label>
                            <Input
                                id="plan-price"
                                type="number"
                                min="1"
                                step="0.01"
                                inputMode="decimal"
                                placeholder="49"
                                value={form.data.price}
                                onChange={(e) => form.setData('price', e.target.value)}
                                required
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="plan-currency">Currency</Label>
                            <select
                                id="plan-currency"
                                className={selectClass}
                                value={form.data.currency}
                                onChange={(e) => form.setData('currency', e.target.value)}
                            >
                                {currencies.map((c) => (
                                    <option key={c}>{c}</option>
                                ))}
                            </select>
                        </div>
                        <Button disabled={form.processing}>
                            {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                            Add plan
                        </Button>
                    </form>
                    <InputError message={form.errors.name || form.errors.price || form.errors.interval || form.errors.currency} />
                    {inrWithoutRazorpay && (
                        <p className="text-muted-foreground text-xs">Razorpay isn't connected, so this INR plan will be charged through Stripe.</p>
                    )}
                    <p className="text-muted-foreground text-xs">
                        Prices can't change after a plan is created. To change a price, add a new plan and hide the old one.
                    </p>
                </section>

                <section className="bg-card rounded-xl border shadow-xs">
                    <h2 className="p-6 pb-3 text-lg font-semibold">Payments</h2>
                    {payments.data.length === 0 ? (
                        <p className="text-muted-foreground px-6 pb-6 text-sm">No payments yet.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-muted-foreground border-y text-left text-xs">
                                    <tr>
                                        <th className="px-6 py-2 font-medium">Member</th>
                                        <th className="px-3 py-2 font-medium">Item</th>
                                        <th className="px-3 py-2 font-medium">Date</th>
                                        <th className="px-6 py-2 text-right font-medium">Amount</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {payments.data.map((p) => (
                                        <tr key={p.id}>
                                            <td className="px-6 py-3">
                                                {p.user ? (
                                                    <Link
                                                        href={route('users.show', p.user.username)}
                                                        className="flex items-center gap-2 hover:underline"
                                                    >
                                                        <UserAvatar user={p.user} size="sm" />
                                                        <span className="truncate">{p.user.name}</span>
                                                    </Link>
                                                ) : (
                                                    <span className="text-muted-foreground">Deleted user</span>
                                                )}
                                            </td>
                                            <td className="text-muted-foreground px-3 py-3">{p.description ?? 'Membership'}</td>
                                            <td className="text-muted-foreground px-3 py-3 whitespace-nowrap">
                                                {new Date(p.paid_at).toLocaleDateString()}
                                            </td>
                                            <td className="px-6 py-3 text-right font-medium whitespace-nowrap">
                                                {formatMoney(p.amount, p.currency)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {payments.last_page > 1 && (
                        <div className="flex justify-between border-t px-6 py-3 text-sm">
                            {payments.prev_page_url ? (
                                <Link href={payments.prev_page_url} preserveScroll>
                                    ← Newer
                                </Link>
                            ) : (
                                <span />
                            )}
                            {payments.next_page_url && (
                                <Link href={payments.next_page_url} preserveScroll>
                                    Older →
                                </Link>
                            )}
                        </div>
                    )}
                </section>
            </div>
        </CommunityLayout>
    );
}

function StatCard({ label, values }: { label: string; values: Record<string, number> }) {
    const entries = Object.entries(values);

    return (
        <div className="bg-card rounded-xl border p-5 shadow-xs">
            <div className="text-muted-foreground text-sm">{label}</div>
            {entries.length === 0 ? (
                <div className="mt-1 text-2xl font-bold">0</div>
            ) : (
                entries.map(([currency, amount]) => (
                    <div key={currency} className="mt-1 text-2xl font-bold">
                        {formatMoney(amount, currency)}
                    </div>
                ))
            )}
        </div>
    );
}
