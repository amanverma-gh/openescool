<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Runs an update: git pull, composer install, migrate, rebuild the frontend.
 * Progress is written to storage/logs/update.log and a small status file so
 * the Admin settings page can follow a one-click update.
 */
class AppUpdater
{
    public function statusPath(): string
    {
        return storage_path('app/update-status.json');
    }

    public function logPath(): string
    {
        return storage_path('logs/update.log');
    }

    /**
     * @return array{running: bool, ok: bool|null, step: string|null, finished_at: string|null, log: string}
     */
    public function status(): array
    {
        $status = File::exists($this->statusPath()) ? (json_decode(File::get($this->statusPath()), true) ?: []) : [];
        $log = File::exists($this->logPath()) ? File::get($this->logPath()) : '';

        return [
            'running' => (bool) ($status['running'] ?? false),
            'ok' => $status['ok'] ?? null,
            'step' => $status['step'] ?? null,
            'finished_at' => $status['finished_at'] ?? null,
            'log' => mb_substr($log, -8000),
        ];
    }

    /**
     * Why this server can't update itself, or null when it can.
     */
    public function blocker(): ?string
    {
        if (! File::isDirectory(base_path('.git'))) {
            return 'This copy was not installed with git, so it can’t pull updates. Download the new release and replace the files instead.';
        }
        if (! $this->exists('git')) {
            return 'git is not installed on this server.';
        }

        return null;
    }

    /**
     * Start `php artisan app:update` in the background (for the one-click button).
     */
    public function startInBackground(): void
    {
        $this->writeStatus(['running' => true, 'ok' => null, 'step' => 'Starting']);

        $php = escapeshellarg(PHP_BINARY);
        $artisan = escapeshellarg(base_path('artisan'));

        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen("start /B \"\" {$php} {$artisan} app:update --force > NUL 2>&1", 'r'));
        } else {
            exec("nohup {$php} {$artisan} app:update --force > /dev/null 2>&1 &");
        }
    }

    /**
     * @param  Closure(string): void  $output
     */
    public function run(bool $force, Closure $output): bool
    {
        $say = function (string $line) use ($output) {
            $output($line);
            File::append($this->logPath(), $line.PHP_EOL);
        };

        // The command writes its own log, so the background run sends stdout nowhere.
        File::put($this->logPath(), '');

        if ($blocker = $this->blocker()) {
            $say($blocker);
            $this->writeStatus(['running' => false, 'ok' => false, 'step' => 'Not possible', 'finished_at' => now()->toIso8601String()]);

            return false;
        }

        $changed = trim(Process::path(base_path())->run('git status --porcelain --untracked-files=no')->output());
        if ($changed !== '' && ! $force) {
            $say("Files were changed on this server, so git can't update them safely:\n{$changed}\nRun again with --force to try anyway.");
            $this->writeStatus(['running' => false, 'ok' => false, 'step' => 'Local changes', 'finished_at' => now()->toIso8601String()]);

            return false;
        }

        $php = escapeshellarg(PHP_BINARY);
        $steps = [
            'Downloading the new code' => 'git pull --ff-only',
            'Installing PHP packages' => $this->exists('composer') ? 'composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader' : null,
            'Updating the database' => "{$php} artisan migrate --force",
            'Building the site' => $this->exists('npm') ? 'npm ci --no-audit --no-fund && npm run build' : null,
            'Clearing caches' => "{$php} artisan optimize:clear",
            'Restarting background jobs' => "{$php} artisan queue:restart",
        ];

        // Visitors see a short "be right back" page; the status check keeps working.
        Artisan::call('down', ['--retry' => 30]);
        $ok = true;

        foreach ($steps as $step => $command) {
            $this->writeStatus(['running' => true, 'ok' => null, 'step' => $step]);
            $say("== {$step}");

            if ($command === null) {
                $say('   skipped: the tool for this step is not installed on the server.');

                continue;
            }

            $result = Process::path(base_path())->timeout(900)->run($command, fn (string $type, string $buffer) => $say(rtrim($buffer)));
            if ($result->failed()) {
                $say("   failed (exit code {$result->exitCode()}).");
                $ok = false;
                break;
            }
        }

        Artisan::call('up');
        $this->writeStatus(['running' => false, 'ok' => $ok, 'step' => $ok ? 'Done' : 'Failed', 'finished_at' => now()->toIso8601String()]);

        return $ok;
    }

    protected function exists(string $binary): bool
    {
        $finder = PHP_OS_FAMILY === 'Windows' ? "where {$binary}" : "command -v {$binary}";

        return Process::run($finder)->successful();
    }

    /**
     * @param  array<string, mixed>  $status
     */
    protected function writeStatus(array $status): void
    {
        File::ensureDirectoryExists(dirname($this->statusPath()));
        File::put($this->statusPath(), json_encode($status));
    }
}
