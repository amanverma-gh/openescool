<?php

namespace App\Console\Commands;

use App\Services\AppUpdater;
use Illuminate\Console\Command;

/**
 * Update this copy of OpenEscool to the latest code on GitHub.
 * The safe way to update; the one-click button in Admin settings runs this too.
 */
class UpdateApp extends Command
{
    protected $signature = 'app:update {--force : Update even if files were changed on the server}';

    protected $description = 'Pull the latest OpenEscool code, install dependencies, migrate and rebuild';

    public function handle(AppUpdater $updater): int
    {
        $this->warn('Back up your database and files before updating.');

        $ok = $updater->run(
            force: (bool) $this->option('force'),
            output: fn (string $line) => $this->line($line),
        );

        if ($ok) {
            $this->info('Update finished.');

            return self::SUCCESS;
        }

        $this->error('Update failed. See the messages above (also saved in storage/logs/update.log). Restore your backup if the site is broken.');

        return self::FAILURE;
    }
}
