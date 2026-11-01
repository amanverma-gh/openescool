<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\Plan;
use App\Services\Billing\RazorpayGateway;
use App\Services\Billing\StripeGateway;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckoutController extends Controller
{
    public function show(Request $request, Community $community): Response|RedirectResponse
    {
        if (! $community->isPaid()) {
            return to_route('communities.about', $community);
        }

        if ($community->membershipFor($request->user())?->isActive()) {
            return to_route('communities.feed', $community);
        }

        return Inertia::render('billing/checkout', [
            'community' => $this->communityProps($community) + ['about' => $community->tagline],
            'plans' => $community->plans()->where('is_active', true)->get()
                ->map(fn (Plan $plan) => $plan->toPublicArray() + ['available' => $this->gatewayEnabled($plan)]),
        ]);
    }

    /**
     * Stripe: redirect to hosted Checkout. Razorpay: return options for Checkout.js.
     */
    public function store(Request $request, Community $community, Plan $plan, StripeGateway $stripe, RazorpayGateway $razorpay): SymfonyResponse
    {
        abort_unless($plan->community_id === $community->id && $plan->is_active && $community->isPaid(), 404);
        abort_if($community->membershipFor($request->user())?->status === 'banned', 403);

        if (! $this->gatewayEnabled($plan)) {
            return back()->with('error', 'Payments are not set up yet. Please try again later.');
        }

        try {
            if ($plan->gateway() === 'razorpay') {
                return new JsonResponse($razorpay->checkoutOptions($community, $plan, $request->user()));
            }

            return Inertia::location($stripe->checkoutUrl($community, $plan, $request->user()));
        } catch (ApiErrorException|RequestException $e) {
            report($e);

            return $request->expectsJson()
                ? new JsonResponse(['message' => 'The payment provider is unavailable. Please try again.'], 502)
                : back()->with('error', 'The payment provider is unavailable. Please try again.');
        }
    }

    public function stripeSuccess(Request $request, Community $community, StripeGateway $stripe): RedirectResponse
    {
        $sessionId = (string) $request->query('session_id');

        try {
            $subscription = $sessionId ? $stripe->fulfill($sessionId) : null;
        } catch (ApiErrorException $e) {
            report($e);
            $subscription = null;
        }

        if ($subscription && $subscription->user_id === $request->user()->id) {
            return to_route('communities.feed', $community)->with('success', "Payment received. Welcome to {$community->name}!");
        }

        // The webhook will finish the job; nothing for the member to do.
        return to_route('communities.about', $community)->with('success', 'Payment is processing. Your access will open in a minute.');
    }

    public function razorpayConfirm(Request $request, Community $community, RazorpayGateway $razorpay): RedirectResponse
    {
        $data = $request->validate([
            'razorpay_payment_id' => ['required', 'string', 'max:64'],
            'razorpay_signature' => ['required', 'string', 'max:128'],
            'razorpay_subscription_id' => ['required_without:razorpay_order_id', 'string', 'max:64'],
            'razorpay_order_id' => ['required_without:razorpay_subscription_id', 'string', 'max:64'],
        ]);

        $razorpay->confirm($data, $request->user());

        return to_route('communities.feed', $community)->with('success', "Payment received. Welcome to {$community->name}!");
    }

    protected function gatewayEnabled(Plan $plan): bool
    {
        return $plan->gateway() === 'razorpay' ? RazorpayGateway::enabled() : StripeGateway::enabled();
    }
}
