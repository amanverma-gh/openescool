<?php

namespace App\Services\Billing;

use App\Models\Community;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Razorpay over its REST API (no SDK): subscriptions for recurring plans,
 * orders for one-time purchases, Checkout.js in the browser.
 */
class RazorpayGateway
{
    public function __construct(protected AccessService $access) {}

    public static function enabled(): bool
    {
        return filled(config('services.razorpay.key_id')) && filled(config('services.razorpay.key_secret'));
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl('https://api.razorpay.com/v1/')
            ->withBasicAuth((string) config('services.razorpay.key_id'), (string) config('services.razorpay.key_secret'))
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->throw();
    }

    /**
     * Create the subscription/order and return the options Checkout.js needs.
     *
     * @return array<string, mixed>
     */
    public function checkoutOptions(Community $community, Plan $plan, User $user): array
    {
        $notes = ['user_id' => $user->id, 'community_id' => $community->id, 'plan_id' => $plan->id];

        $options = [
            'key' => config('services.razorpay.key_id'),
            'name' => $community->name,
            'description' => $plan->name,
            'image' => $community->icon,
            'prefill' => ['name' => $user->name, 'email' => $user->email],
            'notes' => $notes,
        ];

        if ($plan->isRecurring()) {
            $subscription = $this->http()->post('subscriptions', [
                'plan_id' => $this->ensurePlan($plan, $community),
                'total_count' => $plan->interval === Plan::YEAR ? 10 : 120, // Razorpay requires an end; ~10 years
                'quantity' => 1,
                'customer_notify' => 1,
                'notes' => $notes,
            ])->json();

            return $options + ['subscription_id' => $subscription['id']];
        }

        $order = $this->http()->post('orders', [
            'amount' => $plan->amount,
            'currency' => $plan->currency,
            'receipt' => "u{$user->id}-p{$plan->id}-".now()->timestamp,
            'notes' => $notes,
        ])->json();

        return $options + ['order_id' => $order['id'], 'amount' => $plan->amount, 'currency' => $plan->currency];
    }

    /**
     * Verify the Checkout.js success handler payload and grant access right away.
     *
     * @param  array<string, string>  $data
     */
    public function confirm(array $data, User $user): Subscription
    {
        $paymentId = $data['razorpay_payment_id'];
        $signed = isset($data['razorpay_subscription_id'])
            ? $paymentId.'|'.$data['razorpay_subscription_id']
            : ($data['razorpay_order_id'] ?? '').'|'.$paymentId;

        if (! hash_equals(hash_hmac('sha256', $signed, (string) config('services.razorpay.key_secret')), $data['razorpay_signature'])) {
            throw ValidationException::withMessages(['payment' => 'We could not verify this payment. If you were charged, access will be granted automatically within a few minutes.']);
        }

        $entity = isset($data['razorpay_subscription_id'])
            ? $this->http()->get("subscriptions/{$data['razorpay_subscription_id']}")->json()
            : $this->http()->get("orders/{$data['razorpay_order_id']}")->json();

        $context = $this->access->resolve($entity['notes'] ?? []);

        if (! $context || $context[0]->isNot($user)) {
            throw ValidationException::withMessages(['payment' => 'This payment belongs to a different account.']);
        }

        [$user, $community, $plan] = $context;

        $subscription = $this->access->activate(
            $user, $community, $plan, 'razorpay', $entity['id'],
            isset($data['razorpay_subscription_id']) ? $this->periodEnd($entity, $plan) : null,
        );

        $this->access->recordPayment(
            $user, $community, $subscription, 'razorpay', $paymentId,
            $plan?->amount ?? (int) ($entity['amount'] ?? 0), $plan?->currency ?? ($entity['currency'] ?? 'INR'),
        );

        return $subscription;
    }

    public function cancelAtPeriodEnd(Subscription $subscription): void
    {
        $this->http()->post("subscriptions/{$subscription->gateway_id}/cancel", ['cancel_at_cycle_end' => 1]);

        $subscription->update(['cancel_at_period_end' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload  verified webhook body
     */
    public function handleEvent(array $payload): void
    {
        $subscriptionEntity = Arr::get($payload, 'payload.subscription.entity');
        $paymentEntity = Arr::get($payload, 'payload.payment.entity');

        match ($payload['event'] ?? null) {
            'subscription.activated', 'subscription.charged' => $this->handleCharged($subscriptionEntity, $paymentEntity),
            'subscription.pending', 'subscription.halted' => $this->handleStatus($subscriptionEntity, Subscription::PAST_DUE),
            'subscription.cancelled', 'subscription.completed' => $this->handleStatus($subscriptionEntity, Subscription::ENDED),
            'payment.captured' => $this->handleOrderPayment($paymentEntity),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  array<string, mixed>|null  $payment
     */
    protected function handleCharged(array $entity, ?array $payment): void
    {
        if (! $context = $this->access->resolve($entity['notes'] ?? [])) {
            return;
        }

        [$user, $community, $plan] = $context;

        $subscription = $this->access->activate($user, $community, $plan, 'razorpay', $entity['id'], $this->periodEnd($entity, $plan));

        if ($payment) {
            $this->access->recordPayment($user, $community, $subscription, 'razorpay', $payment['id'], $payment['amount'], $payment['currency']);
        }
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    protected function handleStatus(array $entity, string $status): void
    {
        $local = Subscription::where('gateway', 'razorpay')->where('gateway_id', $entity['id'])->first();

        if (! $local) {
            return;
        }

        $status === Subscription::ENDED
            ? $this->access->end($local)
            : $this->access->update($local, $status, null, $local->cancel_at_period_end);
    }

    /**
     * One-time purchases. Subscription payments also fire payment.captured; those carry an invoice id.
     *
     * @param  array<string, mixed>|null  $payment
     */
    protected function handleOrderPayment(?array $payment): void
    {
        if (! $payment || empty($payment['order_id']) || ! empty($payment['invoice_id'])) {
            return;
        }

        if (! $context = $this->access->resolve($payment['notes'] ?? [])) {
            return;
        }

        [$user, $community, $plan] = $context;

        $subscription = $this->access->activate($user, $community, $plan, 'razorpay', $payment['order_id'], null);
        $this->access->recordPayment($user, $community, $subscription, 'razorpay', $payment['id'], $payment['amount'], $payment['currency']);
    }

    protected function ensurePlan(Plan $plan, Community $community): string
    {
        if ($plan->razorpay_plan_id) {
            return $plan->razorpay_plan_id;
        }

        $remote = $this->http()->post('plans', [
            'period' => $plan->interval === Plan::YEAR ? 'yearly' : 'monthly',
            'interval' => 1,
            'item' => ['name' => "{$community->name} · {$plan->name}", 'amount' => $plan->amount, 'currency' => $plan->currency],
        ])->json();

        $plan->forceFill(['razorpay_plan_id' => $remote['id']])->save();

        return $remote['id'];
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    protected function periodEnd(array $entity, ?Plan $plan): CarbonImmutable
    {
        if (! empty($entity['current_end'])) {
            return CarbonImmutable::createFromTimestamp($entity['current_end']);
        }

        // Right after the first charge Razorpay may not have filled current_end yet.
        return $plan?->interval === Plan::YEAR ? now()->toImmutable()->addYear() : now()->toImmutable()->addMonth();
    }
}
