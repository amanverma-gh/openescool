<?php

namespace App\Http\Controllers;

use App\Models\WebhookEvent;
use App\Services\Billing\RazorpayGateway;
use App\Services\Billing\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Signature-verified, idempotent webhook endpoints. A failure rolls back the
 * "processed" marker so the gateway's retry gets another chance.
 */
class WebhookController extends Controller
{
    public function stripe(Request $request, StripeGateway $stripe): Response
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                (string) config('services.stripe.webhook_secret'),
            );
        } catch (SignatureVerificationException|\UnexpectedValueException) {
            return response('Invalid signature', 400);
        }

        DB::transaction(function () use ($event, $stripe) {
            if (WebhookEvent::firstDelivery('stripe', $event->id)) {
                $stripe->handleEvent($event->toArray());
            }
        });

        return response('OK');
    }

    public function razorpay(Request $request, RazorpayGateway $razorpay): Response
    {
        $body = $request->getContent();
        $secret = (string) config('services.razorpay.webhook_secret');

        if ($secret === '' || ! hash_equals(hash_hmac('sha256', $body, $secret), (string) $request->header('X-Razorpay-Signature'))) {
            return response('Invalid signature', 400);
        }

        $eventId = $request->header('X-Razorpay-Event-Id') ?: hash('sha256', $body);

        DB::transaction(function () use ($eventId, $body, $razorpay) {
            if (WebhookEvent::firstDelivery('razorpay', $eventId)) {
                $razorpay->handleEvent(json_decode($body, true) ?: []);
            }
        });

        return response('OK');
    }
}
