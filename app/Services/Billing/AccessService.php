<?php

namespace App\Services\Billing;

use App\Jobs\SyncBrevoContact;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\AccessEnded;
use App\Notifications\PaymentReceipt;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Turns gateway events into local state: subscriptions, payments and membership.
 * Every method is idempotent, because webhooks and success redirects race and repeat.
 */
class AccessService
{
    /**
     * Create or update the local subscription and make sure the member has access.
     */
    public function activate(
        User $user,
        Community $community,
        ?Plan $plan,
        string $gateway,
        string $gatewayId,
        ?CarbonInterface $periodEnd,
        string $status = Subscription::ACTIVE,
    ): Subscription {
        return DB::transaction(function () use ($user, $community, $plan, $gateway, $gatewayId, $periodEnd, $status) {
            $subscription = Subscription::firstOrNew(['gateway' => $gateway, 'gateway_id' => $gatewayId]);

            $subscription->fill([
                'user_id' => $user->id,
                'community_id' => $community->id,
                'plan_id' => $plan?->id ?? $subscription->plan_id,
                'status' => $status,
                'current_period_end' => $periodEnd,
                'ended_at' => null,
            ])->save();

            $this->grant($community, $user);

            return $subscription;
        });
    }

    /**
     * Sync status fields from the gateway without touching membership.
     */
    public function update(Subscription $subscription, string $status, ?CarbonInterface $periodEnd, bool $cancelAtPeriodEnd): void
    {
        if ($subscription->status === Subscription::ENDED) {
            return;
        }

        $subscription->update([
            'status' => $status,
            'current_period_end' => $periodEnd ?? $subscription->current_period_end,
            'cancel_at_period_end' => $cancelAtPeriodEnd,
        ]);
    }

    /**
     * Store a payment once; the receipt email goes out only on first sight.
     */
    public function recordPayment(
        User $user,
        Community $community,
        ?Subscription $subscription,
        string $gateway,
        string $gatewayPaymentId,
        int $amount,
        string $currency,
        ?string $description = null,
    ): Payment {
        $payment = Payment::firstOrCreate(
            ['gateway' => $gateway, 'gateway_payment_id' => $gatewayPaymentId],
            [
                'user_id' => $user->id,
                'community_id' => $community->id,
                'subscription_id' => $subscription?->id,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'description' => $description ?? $subscription?->plan?->name,
                'paid_at' => now(),
            ],
        );

        if ($payment->wasRecentlyCreated && $amount > 0) {
            $user->notify(new PaymentReceipt($payment));
        }

        return $payment;
    }

    /**
     * Subscription is over: remove access unless another subscription still covers it.
     */
    public function end(Subscription $subscription): void
    {
        if ($subscription->status === Subscription::ENDED) {
            return;
        }

        $subscription->update(['status' => Subscription::ENDED, 'ended_at' => now(), 'cancel_at_period_end' => false]);

        $stillCovered = Subscription::where('user_id', $subscription->user_id)
            ->where('community_id', $subscription->community_id)
            ->whereKeyNot($subscription->id)
            ->where('status', '!=', Subscription::ENDED)
            ->get()
            ->contains(fn (Subscription $other) => $other->grantsAccess());

        if ($stillCovered) {
            return;
        }

        $member = CommunityMember::where('community_id', $subscription->community_id)
            ->where('user_id', $subscription->user_id)
            ->first();

        // Staff keep their seat; only regular paying members lose access.
        if (! $member || ! $member->isActive() || $member->role !== CommunityMember::ROLE_MEMBER) {
            return;
        }

        $member->update(['status' => CommunityMember::STATUS_EXPIRED]);
        $subscription->community->forgetMembership($subscription->user);
        $subscription->community->refreshMembersCount();
        SyncBrevoContact::dispatch($subscription->user);

        $subscription->user->notify(new AccessEnded($subscription));
    }

    /**
     * Resolve the ids we attach to every checkout (Stripe metadata, Razorpay notes).
     *
     * @param  array<string, mixed>  $metadata
     * @return array{0: User, 1: Community, 2: Plan|null}|null
     */
    public function resolve(array $metadata): ?array
    {
        $user = User::find($metadata['user_id'] ?? null);
        $community = Community::find($metadata['community_id'] ?? null);

        if (! $user || ! $community) {
            return null;
        }

        $plan = Plan::where('community_id', $community->id)->find($metadata['plan_id'] ?? null);

        return [$user, $community, $plan];
    }

    protected function grant(Community $community, User $user): void
    {
        $member = CommunityMember::firstOrNew(['community_id' => $community->id, 'user_id' => $user->id]);

        if ($member->exists && in_array($member->status, [CommunityMember::STATUS_ACTIVE, CommunityMember::STATUS_BANNED], true)) {
            return;
        }

        $member->fill([
            'role' => $member->role ?? CommunityMember::ROLE_MEMBER,
            'status' => CommunityMember::STATUS_ACTIVE,
            'joined_at' => $member->joined_at ?? now(),
        ])->save();

        $community->forgetMembership($user);
        $community->refreshMembersCount();
        SyncBrevoContact::dispatch($user);
    }
}
