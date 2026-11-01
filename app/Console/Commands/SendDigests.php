<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\Digest;
use App\Services\DigestBuilder;
use Illuminate\Console\Command;

class SendDigests extends Command
{
    protected $signature = 'app:send-digests {frequency : daily or weekly}';

    protected $description = 'Email members a summary of popular posts and upcoming events';

    public function handle(DigestBuilder $builder): int
    {
        $frequency = $this->argument('frequency');

        if (! in_array($frequency, ['daily', 'weekly'], true)) {
            $this->error('Frequency must be daily or weekly.');

            return self::INVALID;
        }

        $window = $frequency === 'daily' ? now()->subDay() : now()->subWeek();
        $eventsUntil = ($frequency === 'daily' ? now()->addDay() : now()->addWeek())->toImmutable();
        // Re-runs (e.g. a cron hiccup) don't send twice within the same period.
        $notBefore = $window->copy()->addHours(2);
        $sent = 0;

        User::where('digest_frequency', $frequency)
            ->where(fn ($q) => $q->whereNull('last_digest_at')->orWhere('last_digest_at', '<', $notBefore))
            ->whereHas('memberships', fn ($q) => $q->where('status', 'active'))
            ->chunkById(200, function ($users) use ($builder, $frequency, $window, $eventsUntil, &$sent) {
                foreach ($users as $user) {
                    // Never resend what an earlier digest already covered.
                    $since = $user->last_digest_at && $user->last_digest_at->gt($window) ? $user->last_digest_at : $window;
                    $sections = $builder->build($user, $since->toImmutable(), $eventsUntil);

                    $user->forceFill(['last_digest_at' => now()])->save();

                    if ($sections) {
                        $user->notify(new Digest($frequency, $sections));
                        $sent++;
                    }
                }
            });

        $this->info("Sent {$sent} {$frequency} digest(s).");

        return self::SUCCESS;
    }
}
