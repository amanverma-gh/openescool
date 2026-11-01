<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Billing\AccessService;
use Illuminate\Console\Command;

/**
 * Safety net for missed webhooks: end subscriptions whose paid period
 * (plus the grace window) is over.
 */
class ExpireSubscriptions extends Command
{
    protected $signature = 'app:expire-subscriptions';

    protected $description = 'Remove access for subscriptions past their period end';

    public function handle(AccessService $access): int
    {
        $count = 0;

        Subscription::where('status', '!=', Subscription::ENDED)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now()->subDays(Subscription::GRACE_DAYS))
            ->with(['user', 'community'])
            ->each(function (Subscription $subscription) use ($access, &$count) {
                $access->end($subscription);
                $count++;
            });

        $this->info("Ended {$count} subscription(s).");

        return self::SUCCESS;
    }
}
