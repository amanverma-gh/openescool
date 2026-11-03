<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Compares this copy's version with the latest GitHub release.
 */
class UpdateChecker
{
    /**
     * @return array{current: string, latest: string|null, available: bool, notes: string|null, url: string|null, checked: bool}
     */
    public function status(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget('openescool:latest-release');
        }

        // Checked at most every 6 hours; a failed check is remembered for 1 hour.
        $release = Cache::get('openescool:latest-release');
        if ($release === null) {
            $release = $this->fetchLatest();
            Cache::put('openescool:latest-release', $release, $release ? now()->addHours(6) : now()->addHour());
        }

        $current = (string) config('openescool.version');
        $latest = $release['version'] ?? null;

        return [
            'current' => $current,
            'latest' => $latest,
            'available' => $latest !== null && version_compare($latest, $current, '>'),
            'notes' => $release['notes'] ?? null,
            'url' => $release['url'] ?? null,
            'checked' => (bool) $release,
        ];
    }

    /**
     * @return array{version: string, notes: string|null, url: string}|false
     */
    protected function fetchLatest(): array|false
    {
        try {
            $response = Http::timeout(5)->acceptJson()
                ->get('https://api.github.com/repos/'.config('openescool.repository').'/releases/latest');
        } catch (\Throwable) {
            return false;
        }

        if (! $response->successful() || ! $response->json('tag_name')) {
            return false;
        }

        return [
            'version' => ltrim((string) $response->json('tag_name'), 'vV'),
            'notes' => str((string) $response->json('body'))->limit(3000)->toString() ?: null,
            'url' => (string) $response->json('html_url'),
        ];
    }
}
