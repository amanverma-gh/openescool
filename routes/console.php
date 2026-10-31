<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:make-admin {email}', function (string $email) {
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("No user with email {$email}. Register first, then run this again.");

        return 1;
    }

    $user->forceFill(['is_admin' => true])->save();
    $this->info("{$user->name} can now create communities.");
})->purpose('Allow a user to create communities');

// Shared hosting has no supervisor: cron runs `schedule:run` every minute,
// which drains the queue (emails, Brevo sync) and then exits.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();
