<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\AccessEnded;
use App\Notifications\PaymentReceipt;
use App\Services\Billing\AccessService;
use App\Services\Billing\StripeGateway;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected Community $community;

    protected User $owner;

    protected User $buyer;

    protected Plan $monthly;

    protected Plan $lifetime;

    protected Plan $inr;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config([
            'services.stripe.secret' => 'sk_test_x',
            'services.stripe.webhook_secret' => 'whsec_test',
            'services.razorpay.key_id' => 'rzp_test_x',
            'services.razorpay.key_secret' => 'rzp_secret',
            'services.razorpay.webhook_secret' => 'rzp_whsec',
        ]);

        $this->owner = User::factory()->create();
        $this->community = new Community(['name' => 'Paid', 'slug' => 'paid', 'privacy' => Community::PRIVACY_PUBLIC, 'access_type' => Community::ACCESS_PAID]);
        $this->community->owner()->associate($this->owner)->save();
        app(MembershipService::class)->addOwner($this->community, $this->owner);

        $this->monthly = $this->community->plans()->create(['name' => 'Monthly', 'interval' => 'month', 'amount' => 4900, 'currency' => 'USD']);
        $this->lifetime = $this->community->plans()->create(['name' => 'Lifetime', 'interval' => 'one_time', 'amount' => 29900, 'currency' => 'USD']);
        $this->inr = $this->community->plans()->create(['name' => 'India', 'interval' => 'month', 'amount' => 99900, 'currency' => 'INR']);

        $this->buyer = User::factory()->create();
    }

    protected function member(?User $user = null): ?CommunityMember
    {
        return CommunityMember::where('community_id', $this->community->id)->where('user_id', ($user ?? $this->buyer)->id)->first();
    }

    protected function stripeWebhook(string $type, array $object, ?string $id = null)
    {
        $payload = json_encode(['id' => $id ?? 'evt_'.uniqid(), 'object' => 'event', 'type' => $type, 'data' => ['object' => $object]]);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_test');

        return $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    protected function razorpayWebhook(array $body, string $secret = 'rzp_whsec')
    {
        $payload = json_encode($body);

        return $this->call('POST', '/webhooks/razorpay', [], [], [], [
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $payload, $secret),
            'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_'.uniqid(),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    protected function metadata(Plan $plan): array
    {
        return ['user_id' => (string) $this->buyer->id, 'community_id' => (string) $this->community->id, 'plan_id' => (string) $plan->id];
    }

    public function test_joining_a_paid_community_goes_to_checkout(): void
    {
        $this->actingAs($this->buyer)->post('/c/paid/join')->assertRedirect('/c/paid/checkout');
        $this->assertNull($this->member());

        $this->get('/c/paid/checkout')->assertInertia(fn (Assert $page) => $page
            ->component('billing/checkout')
            ->has('plans', 3)
            ->where('plans.0.available', true));
    }

    public function test_inr_plans_use_razorpay_only_when_configured(): void
    {
        $this->assertSame('razorpay', $this->inr->gateway());
        config(['services.razorpay.key_id' => null]);
        $this->assertSame('stripe', $this->inr->gateway());
        $this->assertSame('stripe', $this->monthly->gateway());
    }

    public function test_stripe_checkout_redirects_to_hosted_page(): void
    {
        $this->mock(StripeGateway::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('checkoutUrl')->once()->andReturn('https://checkout.stripe.com/c/pay/cs_test'));

        $this->actingAs($this->buyer)
            ->post("/c/paid/checkout/{$this->monthly->id}")
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test');
    }

    public function test_owner_manages_plans_and_access(): void
    {
        $this->actingAs($this->owner);

        $this->post('/c/paid/plans', ['name' => 'Yearly', 'interval' => 'year', 'price' => '490.50', 'currency' => 'EUR'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('plans', ['name' => 'Yearly', 'amount' => 49050, 'currency' => 'EUR']);

        $this->patch("/c/paid/plans/{$this->monthly->id}", ['is_active' => false]);
        $this->assertFalse($this->monthly->fresh()->is_active);

        $this->post('/c/paid/access', ['access_type' => 'free']);
        $this->assertFalse($this->community->fresh()->isPaid());

        $this->get('/c/paid/revenue')->assertInertia(fn (Assert $page) => $page->component('communities/revenue')->has('plans', 4));

        $this->actingAs($this->buyer)->get('/c/paid/revenue')->assertForbidden();
    }

    public function test_switching_to_paid_requires_an_active_plan(): void
    {
        $this->community->plans()->update(['is_active' => false]);
        $this->community->update(['access_type' => 'free']);

        $this->actingAs($this->owner)->post('/c/paid/access', ['access_type' => 'paid'])->assertSessionHas('error');
        $this->assertFalse($this->community->fresh()->isPaid());
    }

    public function test_stripe_webhook_rejects_bad_signatures(): void
    {
        $this->call('POST', '/webhooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't=1,v1=bad'], '{}')->assertStatus(400);
    }

    public function test_stripe_one_time_purchase_grants_lifetime_access_once(): void
    {
        $session = [
            'id' => 'cs_1', 'mode' => 'payment', 'payment_status' => 'paid', 'payment_intent' => 'pi_1',
            'amount_total' => 29900, 'currency' => 'usd', 'metadata' => $this->metadata($this->lifetime),
        ];

        $this->stripeWebhook('checkout.session.completed', $session, 'evt_same')->assertOk();
        $this->stripeWebhook('checkout.session.completed', $session, 'evt_same')->assertOk();
        $this->stripeWebhook('checkout.session.completed', $session)->assertOk(); // different delivery, same payment

        $this->assertTrue($this->member()->isActive());
        $this->assertSame(1, Subscription::count());
        $this->assertTrue(Subscription::first()->isLifetime());
        $this->assertSame(1, Payment::count());
        Notification::assertSentToTimes($this->buyer, PaymentReceipt::class, 1);
    }

    public function test_stripe_subscription_lifecycle(): void
    {
        $periodEnd = now()->addMonth()->timestamp;

        $this->stripeWebhook('checkout.session.completed', [
            'id' => 'cs_2', 'mode' => 'subscription', 'payment_status' => 'paid', 'invoice' => 'in_1',
            'amount_total' => 4900, 'currency' => 'usd', 'metadata' => $this->metadata($this->monthly),
            'subscription' => ['id' => 'sub_1', 'status' => 'active', 'items' => ['data' => [['current_period_end' => $periodEnd]]]],
        ])->assertOk();

        $subscription = Subscription::firstWhere('gateway_id', 'sub_1');
        $this->assertSame($periodEnd, $subscription->current_period_end->timestamp);
        $this->assertTrue($this->member()->isActive());

        // First invoice arrives too: same invoice id, no duplicate payment.
        $this->stripeWebhook('invoice.paid', [
            'id' => 'in_1', 'amount_paid' => 4900, 'currency' => 'usd',
            'parent' => ['subscription_details' => ['subscription' => 'sub_1']],
            'lines' => ['data' => [['period' => ['end' => $periodEnd]]]],
        ]);
        $this->assertSame(1, Payment::count());

        // Renewal.
        $nextEnd = now()->addMonths(2)->timestamp;
        $this->stripeWebhook('invoice.paid', [
            'id' => 'in_2', 'amount_paid' => 4900, 'currency' => 'usd',
            'parent' => ['subscription_details' => ['subscription' => 'sub_1']],
            'lines' => ['data' => [['period' => ['end' => $nextEnd]]]],
        ]);
        $this->assertSame(2, Payment::count());
        $this->assertSame($nextEnd, $subscription->fresh()->current_period_end->timestamp);

        // Member cancels in the portal.
        $this->stripeWebhook('customer.subscription.updated', [
            'id' => 'sub_1', 'status' => 'active', 'cancel_at_period_end' => true,
            'items' => ['data' => [['current_period_end' => $nextEnd]]],
        ]);
        $this->assertTrue($subscription->fresh()->cancel_at_period_end);
        $this->assertTrue($this->member()->isActive());

        // Period ends.
        $this->stripeWebhook('customer.subscription.deleted', ['id' => 'sub_1', 'status' => 'canceled']);
        $this->assertSame(Subscription::ENDED, $subscription->fresh()->status);
        $this->assertSame(CommunityMember::STATUS_EXPIRED, $this->member()->status);
        $this->assertSame(1, $this->community->fresh()->members_count);
        Notification::assertSentTo($this->buyer, AccessEnded::class);

        // Expired members are sent back to checkout.
        $this->actingAs($this->buyer)->get('/c/paid')->assertRedirect('/c/paid/about');
        $this->post('/c/paid/join')->assertRedirect('/c/paid/checkout');
    }

    public function test_razorpay_checkout_confirmation_verifies_signature(): void
    {
        Http::fake([
            'api.razorpay.com/v1/plans' => Http::response(['id' => 'plan_rzp']),
            'api.razorpay.com/v1/subscriptions' => Http::response(['id' => 'sub_rzp']),
            'api.razorpay.com/v1/subscriptions/sub_rzp' => Http::response([
                'id' => 'sub_rzp', 'current_end' => now()->addMonth()->timestamp, 'notes' => $this->metadata($this->inr),
            ]),
        ]);

        $this->actingAs($this->buyer)
            ->postJson("/c/paid/checkout/{$this->inr->id}")
            ->assertOk()
            ->assertJson(['subscription_id' => 'sub_rzp', 'key' => 'rzp_test_x']);
        $this->assertSame('plan_rzp', $this->inr->fresh()->razorpay_plan_id);

        $this->post('/c/paid/checkout/razorpay/confirm', [
            'razorpay_payment_id' => 'pay_1', 'razorpay_subscription_id' => 'sub_rzp', 'razorpay_signature' => 'forged',
        ])->assertSessionHasErrors('payment');
        $this->assertNull($this->member());

        $this->post('/c/paid/checkout/razorpay/confirm', [
            'razorpay_payment_id' => 'pay_1', 'razorpay_subscription_id' => 'sub_rzp',
            'razorpay_signature' => hash_hmac('sha256', 'pay_1|sub_rzp', 'rzp_secret'),
        ])->assertRedirect('/c/paid');

        $this->assertTrue($this->member()->isActive());
        $this->assertDatabaseHas('payments', ['gateway_payment_id' => 'pay_1', 'amount' => 99900, 'currency' => 'INR']);
    }

    public function test_razorpay_webhooks(): void
    {
        $this->razorpayWebhook(['event' => 'subscription.charged'], 'wrong')->assertStatus(400);

        $entity = ['id' => 'sub_rzp', 'current_end' => now()->addMonth()->timestamp, 'notes' => $this->metadata($this->inr)];

        $this->razorpayWebhook(['event' => 'subscription.charged', 'payload' => [
            'subscription' => ['entity' => $entity],
            'payment' => ['entity' => ['id' => 'pay_9', 'amount' => 99900, 'currency' => 'INR']],
        ]])->assertOk();

        $this->assertTrue($this->member()->isActive());
        $this->assertDatabaseHas('payments', ['gateway' => 'razorpay', 'gateway_payment_id' => 'pay_9']);

        $this->razorpayWebhook(['event' => 'subscription.cancelled', 'payload' => ['subscription' => ['entity' => $entity]]])->assertOk();
        $this->assertSame(CommunityMember::STATUS_EXPIRED, $this->member()->status);
    }

    public function test_expire_command_ends_overdue_subscriptions_but_keeps_staff(): void
    {
        $access = app(AccessService::class);
        $access->activate($this->buyer, $this->community, $this->monthly, 'stripe', 'sub_old', now()->subDays(Subscription::GRACE_DAYS + 1));
        $access->activate($this->owner, $this->community, $this->monthly, 'stripe', 'sub_owner', now()->subDays(10));
        $fresh = User::factory()->create();
        $access->activate($fresh, $this->community, $this->monthly, 'stripe', 'sub_new', now()->subDay()); // inside grace

        $this->artisan('app:expire-subscriptions')->assertSuccessful();

        $this->assertSame(CommunityMember::STATUS_EXPIRED, $this->member()->status);
        $this->assertTrue($this->member($this->owner)->isActive());
        $this->assertTrue($this->member($fresh)->isActive());
    }

    public function test_member_billing_page_and_cancel(): void
    {
        $subscription = app(AccessService::class)->activate($this->buyer, $this->community, $this->monthly, 'stripe', 'sub_1', now()->addMonth());

        $this->mock(StripeGateway::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('setCancelAtPeriodEnd')->once()
            ->andReturnUsing(fn (Subscription $s, bool $cancel) => $s->update(['cancel_at_period_end' => $cancel])));

        $this->actingAs($this->buyer)->get('/settings/billing')->assertInertia(fn (Assert $page) => $page
            ->component('settings/billing')
            ->has('subscriptions', 1));

        $this->post("/settings/billing/{$subscription->id}/cancel")->assertSessionHas('success');
        $this->assertTrue($subscription->fresh()->cancel_at_period_end);

        $other = User::factory()->create();
        $this->actingAs($other)->post("/settings/billing/{$subscription->id}/cancel")->assertNotFound();
    }
}
