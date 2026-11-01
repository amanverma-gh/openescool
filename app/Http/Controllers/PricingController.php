<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\RazorpayGateway;
use App\Services\Billing\StripeGateway;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Owner-facing pricing (plans, free/paid switch) and the revenue dashboard.
 */
class PricingController extends Controller
{
    public function revenue(Request $request, Community $community): Response
    {
        $this->authorize('manage', $community);

        $live = $community->subscriptions()
            ->where('status', '!=', Subscription::ENDED)
            ->with('plan')
            ->get()
            ->filter(fn (Subscription $s) => $s->grantsAccess());

        $recurring = $live->filter(fn (Subscription $s) => ! $s->isLifetime() && ! $s->cancel_at_period_end && $s->plan);

        return Inertia::render('communities/revenue', [
            'community' => $this->communityProps($community),
            'accessType' => $community->access_type,
            'plans' => $community->plans()->withCount(['subscriptions as active_count' => fn ($q) => $q->where('status', '!=', Subscription::ENDED)])->get()
                ->map(fn (Plan $plan) => $plan->toPublicArray() + ['active_count' => $plan->active_count]),
            'currencies' => Plan::CURRENCIES,
            'gateways' => ['stripe' => StripeGateway::enabled(), 'razorpay' => RazorpayGateway::enabled()],
            'stats' => [
                // Per currency: we never convert between currencies.
                'mrr' => $recurring->groupBy(fn (Subscription $s) => $s->plan->currency)
                    ->map(fn ($subs) => $subs->sum(fn (Subscription $s) => $s->plan->monthlyAmount())),
                'last30' => $community->payments()->where('paid_at', '>=', now()->subDays(30))
                    ->selectRaw('currency, SUM(amount) as total')->groupBy('currency')->pluck('total', 'currency')
                    ->map(fn ($total) => (int) $total),
                'paying' => $live->pluck('user_id')->unique()->count(),
                'canceling' => $live->where('cancel_at_period_end', true)->count(),
            ],
            'payments' => $community->payments()->with('user:id,name,username,avatar_path')->latest('paid_at')->paginate(20)
                ->through(fn (Payment $p) => $p->only(['id', 'amount', 'currency', 'description', 'paid_at', 'gateway']) + [
                    'user' => $p->user?->toPublicArray(),
                ]),
        ]);
    }

    public function updateAccess(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('manage', $community);

        $data = $request->validate(['access_type' => ['required', Rule::in([Community::ACCESS_FREE, Community::ACCESS_PAID])]]);

        if ($data['access_type'] === Community::ACCESS_PAID && ! $community->plans()->where('is_active', true)->exists()) {
            return back()->with('error', 'Add at least one active plan before making the community paid.');
        }

        $community->update($data);

        return back()->with('success', $community->isPaid()
            ? 'New members now pay to join. Existing members keep their access.'
            : 'The community is free to join again.');
    }

    public function store(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('manage', $community);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'interval' => ['required', Rule::in([Plan::MONTH, Plan::YEAR, Plan::ONE_TIME])],
            'price' => ['required', 'numeric', 'min:1', 'max:100000'],
            'currency' => ['required', Rule::in(Plan::CURRENCIES)],
        ]);

        $community->plans()->create([
            'name' => $data['name'],
            'interval' => $data['interval'],
            'amount' => Money::toMinor($data['price']),
            'currency' => $data['currency'],
        ]);

        return back()->with('success', 'Plan added.');
    }

    /**
     * Prices are fixed once created (subscribers are on them); rename or retire instead.
     */
    public function update(Request $request, Community $community, Plan $plan): RedirectResponse
    {
        $this->authorize('manage', $community);
        abort_unless($plan->community_id === $community->id, 404);

        $plan->update($request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return back()->with('success', 'Plan updated.');
    }

    public function destroy(Community $community, Plan $plan): RedirectResponse
    {
        $this->authorize('manage', $community);
        abort_unless($plan->community_id === $community->id, 404);

        if ($plan->subscriptions()->exists()) {
            return back()->with('error', 'This plan has subscribers. Hide it instead so nobody new can buy it.');
        }

        $plan->delete();

        return back()->with('success', 'Plan deleted.');
    }
}
