<?php

namespace App\Services\Billing;

use App\Models\Community;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Stripe\StripeClient;

class StripeGateway
{
    public function __construct(protected AccessService $access) {}

    public static function enabled(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    protected function client(): StripeClient
    {
        return app(StripeClient::class);
    }

    /**
     * Start a hosted Checkout Session and return its URL.
     */
    public function checkoutUrl(Community $community, Plan $plan, User $user): string
    {
        $recurring = $plan->isRecurring();
        $metadata = ['user_id' => $user->id, 'community_id' => $community->id, 'plan_id' => $plan->id];

        $session = $this->client()->checkout->sessions->create([
            'mode' => $recurring ? 'subscription' : 'payment',
            'customer' => $this->customerId($user),
            'client_reference_id' => (string) $user->id,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => array_filter([
                    'currency' => strtolower($plan->currency),
                    'unit_amount' => $plan->amount,
                    'product_data' => ['name' => "{$community->name} · {$plan->name}"],
                    'recurring' => $recurring ? ['interval' => $plan->interval] : null,
                ]),
            ]],
            'metadata' => $metadata,
            ($recurring ? 'subscription_data' : 'payment_intent_data') => ['metadata' => $metadata],
            'allow_promotion_codes' => true,
            'success_url' => route('billing.stripe.success', $community).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('billing.checkout', $community),
        ]);

        return $session->url;
    }

    /**
     * Called from the success redirect so access is instant even before the webhook lands.
     */
    public function fulfill(string $sessionId): ?Subscription
    {
        $session = $this->client()->checkout->sessions->retrieve($sessionId, ['expand' => ['subscription']]);

        return $this->handleCheckoutSession($session->toArray());
    }

    public function portalUrl(User $user, string $returnUrl): string
    {
        return $this->client()->billingPortal->sessions->create([
            'customer' => $this->customerId($user),
            'return_url' => $returnUrl,
        ])->url;
    }

    public function setCancelAtPeriodEnd(Subscription $subscription, bool $cancel): void
    {
        $this->client()->subscriptions->update($subscription->gateway_id, ['cancel_at_period_end' => $cancel]);

        $subscription->update(['cancel_at_period_end' => $cancel]);
    }

    /**
     * @param  array<string, mixed>  $event  verified Stripe event
     */
    public function handleEvent(array $event): void
    {
        $object = $event['data']['object'];

        match ($event['type']) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $this->handleCheckoutSession($object),
            'invoice.paid' => $this->handleInvoicePaid($object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($object),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $stripeSubscription
     */
    protected function handleSubscriptionDeleted(array $stripeSubscription): void
    {
        if ($local = $this->localSubscription($stripeSubscription['id'])) {
            $this->access->end($local);
        }
    }

    /**
     * @param  array<string, mixed>  $session
     */
    protected function handleCheckoutSession(array $session): ?Subscription
    {
        if (! in_array($session['payment_status'] ?? null, ['paid', 'no_payment_required'], true)) {
            return null; // async payment methods finish later via async_payment_succeeded
        }

        if (! $context = $this->access->resolve($session['metadata'] ?? [])) {
            return null;
        }

        [$user, $community, $plan] = $context;

        if (($session['mode'] ?? null) === 'subscription') {
            $stripeSubscription = is_array($session['subscription'])
                ? $session['subscription']
                : $this->client()->subscriptions->retrieve($session['subscription'])->toArray();

            $subscription = $this->access->activate(
                $user, $community, $plan, 'stripe', $stripeSubscription['id'],
                $this->periodEnd($stripeSubscription), $this->mapStatus($stripeSubscription['status']),
            );
            $paymentId = $session['invoice'] ?? null;
            $paymentId = is_array($paymentId) ? $paymentId['id'] : $paymentId;
        } else {
            $paymentId = Arr::get($session, 'payment_intent.id', $session['payment_intent'] ?? $session['id']);
            $subscription = $this->access->activate($user, $community, $plan, 'stripe', $paymentId, null);
        }

        if ($paymentId && ($session['amount_total'] ?? 0) > 0) {
            $this->access->recordPayment(
                $user, $community, $subscription, 'stripe', $paymentId,
                $session['amount_total'], $session['currency'],
            );
        }

        return $subscription;
    }

    /**
     * Renewals: extend the period and log the payment.
     *
     * @param  array<string, mixed>  $invoice
     */
    protected function handleInvoicePaid(array $invoice): void
    {
        // API 2025-03+ moved the subscription under parent.subscription_details.
        $stripeSubscriptionId = Arr::get($invoice, 'parent.subscription_details.subscription', $invoice['subscription'] ?? null);

        if (! $stripeSubscriptionId) {
            return;
        }

        $local = $this->localSubscription($stripeSubscriptionId);

        if ($local) {
            [$user, $community, $plan] = [$local->user, $local->community, $local->plan];
        } elseif ($context = $this->access->resolve($this->subscriptionMetadata($invoice, $stripeSubscriptionId))) {
            [$user, $community, $plan] = $context;
        } else {
            return;
        }

        $periodEnd = Arr::get($invoice, 'lines.data.0.period.end');

        $subscription = $this->access->activate(
            $user, $community, $plan, 'stripe', $stripeSubscriptionId,
            $periodEnd ? CarbonImmutable::createFromTimestamp($periodEnd) : $local?->current_period_end,
        );

        if (($invoice['amount_paid'] ?? 0) > 0) {
            $this->access->recordPayment(
                $user, $community, $subscription, 'stripe', $invoice['id'],
                $invoice['amount_paid'], $invoice['currency'],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $stripeSubscription
     */
    protected function handleSubscriptionUpdated(array $stripeSubscription): void
    {
        if (! $local = $this->localSubscription($stripeSubscription['id'])) {
            return;
        }

        $status = $this->mapStatus($stripeSubscription['status']);

        if ($status === Subscription::ENDED) {
            $this->access->end($local);

            return;
        }

        $this->access->update($local, $status, $this->periodEnd($stripeSubscription), (bool) ($stripeSubscription['cancel_at_period_end'] ?? false));
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @return array<string, mixed>
     */
    protected function subscriptionMetadata(array $invoice, string $stripeSubscriptionId): array
    {
        return Arr::get($invoice, 'parent.subscription_details.metadata')
            ?: Arr::get($invoice, 'subscription_details.metadata')
            ?: $this->client()->subscriptions->retrieve($stripeSubscriptionId)->metadata->toArray();
    }

    protected function localSubscription(string $stripeSubscriptionId): ?Subscription
    {
        return Subscription::where('gateway', 'stripe')->where('gateway_id', $stripeSubscriptionId)->first();
    }

    /**
     * @param  array<string, mixed>  $stripeSubscription
     */
    protected function periodEnd(array $stripeSubscription): ?CarbonImmutable
    {
        // API 2025-03+ reports the period per subscription item.
        $timestamp = Arr::get($stripeSubscription, 'items.data.0.current_period_end', $stripeSubscription['current_period_end'] ?? null);

        return $timestamp ? CarbonImmutable::createFromTimestamp($timestamp) : null;
    }

    protected function mapStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'active', 'trialing' => Subscription::ACTIVE,
            'canceled', 'incomplete_expired' => Subscription::ENDED,
            default => Subscription::PAST_DUE, // past_due, unpaid, incomplete, paused
        };
    }

    protected function customerId(User $user): string
    {
        if ($user->stripe_customer_id) {
            return $user->stripe_customer_id;
        }

        $customer = $this->client()->customers->create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => ['user_id' => $user->id],
        ]);

        $user->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }
}
