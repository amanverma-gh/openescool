<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Brevo\BrevoClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Keeps a user's Brevo contact in sync: name attributes plus membership of
 * the "all members" list and each joined community's list (if configured).
 */
class SyncBrevoContact implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public User $user) {}

    public function handle(BrevoClient $brevo): void
    {
        if (! $brevo->enabled()) {
            return;
        }

        [$firstName, $lastName] = array_pad(explode(' ', $this->user->name, 2), 2, '');

        $listIds = array_filter([(int) config('services.brevo.members_list_id')]);

        $brevo->upsertContact($this->user->email, [
            'FIRSTNAME' => $firstName,
            'LASTNAME' => $lastName,
            'COMMUNITIES' => $this->user->communities()->pluck('name')->implode(', '),
        ], array_values($listIds));
    }
}
