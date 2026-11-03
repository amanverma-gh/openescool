<?php

namespace App\Http\Controllers;

use App\Services\AppUpdater;
use App\Services\UpdateChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin settings → Updates: check GitHub for a new release and, if the admin
 * accepts the risk, run the update from the browser.
 */
class UpdateController extends Controller
{
    public function check(Request $request, UpdateChecker $checker): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);
        $status = $checker->status(fresh: true);

        return back()->with($status['checked'] ? 'success' : 'error', match (true) {
            ! $status['checked'] => 'Could not reach GitHub to check for updates. Try again later.',
            $status['available'] => "Version {$status['latest']} is available.",
            default => "You're on the latest version ({$status['current']}).",
        });
    }

    public function run(Request $request, AppUpdater $updater): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);
        $request->validate(['backup_confirmed' => ['accepted']], ['backup_confirmed.accepted' => 'Please confirm you have a backup first.']);

        if ($blocker = $updater->blocker()) {
            return back()->with('error', $blocker);
        }
        if ($updater->status()['running']) {
            return back()->with('error', 'An update is already running.');
        }

        $updater->startInBackground();

        return back()->with('success', 'Update started. Keep this page open; it can take a few minutes.');
    }

    public function status(Request $request, AppUpdater $updater): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        return response()->json($updater->status());
    }
}
