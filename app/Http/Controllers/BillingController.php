<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Billing\RazorpayGateway;
use App\Services\Billing\StripeGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The member's own subscriptions and receipts (/settings/billing).
 */
class BillingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/billing', [
            'subscriptions' => $user->subscriptions()
                ->with(['community:id,name,slug,icon_path', 'plan:id,name,interval,amount,currency'])
                ->latest()
                ->get()
                ->map(fn (Subscription $s) => [
                    'id' => $s->id,
                    'community' => $s->community->only(['name', 'slug', 'icon']),
                    'plan' => $s->plan?->only(['name', 'interval', 'amount', 'currency']),
                    'gateway' => $s->gateway,
                    'status' => $s->status,
                    'lifetime' => $s->isLifetime(),
                    'cancel_at_period_end' => $s->cancel_at_period_end,
                    'current_period_end' => $s->current_period_end,
                    'ended_at' => $s->ended_at,
                ]),
            'payments' => $user->payments()
                ->with('community:id,name,slug')
                ->latest('paid_at')
                ->limit(50)
                ->get()
                ->map(fn (Payment $p) => $p->only(['id', 'amount', 'currency', 'description', 'paid_at', 'gateway', 'gateway_payment_id']) + [
                    'community' => $p->community->name,
                ]),
            'hasStripeCustomer' => filled($user->stripe_customer_id) && StripeGateway::enabled(),
        ]);
    }

    public function cancel(Request $request, Subscription $subscription, StripeGateway $stripe, RazorpayGateway $razorpay): RedirectResponse
    {
        $this->ensureCancellable($request, $subscription);

        $subscription->gateway === 'razorpay'
            ? $razorpay->cancelAtPeriodEnd($subscription)
            : $stripe->setCancelAtPeriodEnd($subscription, true);

        return back()->with('success', 'Cancelled. You keep access until '.$subscription->current_period_end->toFormattedDateString().'.');
    }

    /**
     * Undo a pending cancellation (Stripe only; Razorpay cancellations are final).
     */
    public function resume(Request $request, Subscription $subscription, StripeGateway $stripe): RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->id && $subscription->gateway === 'stripe', 404);
        abort_unless($subscription->cancel_at_period_end && $subscription->status !== Subscription::ENDED, 422);

        $stripe->setCancelAtPeriodEnd($subscription, false);

        return back()->with('success', 'Your subscription will renew as usual.');
    }

    /**
     * Stripe's Billing Portal: update card, download invoices.
     */
    public function portal(Request $request, StripeGateway $stripe): SymfonyResponse
    {
        abort_unless(StripeGateway::enabled() && $request->user()->stripe_customer_id, 404);

        return Inertia::location($stripe->portalUrl($request->user(), route('billing.index')));
    }

    protected function ensureCancellable(Request $request, Subscription $subscription): void
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);
        abort_if($subscription->isLifetime() || $subscription->status === Subscription::ENDED || $subscription->cancel_at_period_end, 422);
    }
}
