<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->string('access_type')->default('free')->after('privacy'); // free | paid
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('google_id');
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('interval'); // month | year | one_time
            $table->unsignedInteger('amount'); // minor units (cents, paise)
            $table->char('currency', 3);
            $table->string('razorpay_plan_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway'); // stripe | razorpay
            $table->string('gateway_id'); // subscription id, or payment/order id for one-time purchases
            $table->string('status'); // active | past_due | ended
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('current_period_end')->nullable(); // null = lifetime access
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_id']);
            $table->index(['community_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway');
            $table->string('gateway_payment_id');
            $table->unsignedInteger('amount');
            $table->char('currency', 3);
            $table->string('description')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->unique(['gateway', 'gateway_payment_id']);
            $table->index(['community_id', 'paid_at']);
        });

        // Webhook ids already processed, so retried deliveries are ignored.
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway');
            $table->string('event_id');
            $table->timestamps();

            $table->unique(['gateway', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('stripe_customer_id'));
        Schema::table('communities', fn (Blueprint $table) => $table->dropColumn('access_type'));
    }
};
