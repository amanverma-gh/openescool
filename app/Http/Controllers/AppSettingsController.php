<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform settings for the site owner, e.g. the GIPHY key for the GIF picker.
 */
class AppSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->is_admin, 403);
        $key = AppSetting::get('giphy_api_key');

        return Inertia::render('admin/settings', [
            // Only a hint of the saved key is ever shown.
            'giphy' => $key ? ['set' => true, 'hint' => '••••'.substr($key, -4)] : ['set' => false, 'hint' => null],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $data = $request->validate([
            'giphy_api_key' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9]+$/'],
            'remove_giphy' => ['boolean'],
        ]);

        if ($request->boolean('remove_giphy')) {
            AppSetting::put('giphy_api_key', null);

            return back()->with('success', 'GIPHY key removed. The GIF picker is off.');
        }

        if (filled($data['giphy_api_key'] ?? null)) {
            // Check the key works before saving it.
            $ok = Http::timeout(5)->get('https://api.giphy.com/v1/gifs/trending', ['api_key' => $data['giphy_api_key'], 'limit' => 1])->successful();

            if (! $ok) {
                return back()->withErrors(['giphy_api_key' => 'GIPHY did not accept this key. Check it and try again.']);
            }

            AppSetting::put('giphy_api_key', $data['giphy_api_key']);
        }

        return back()->with('success', 'Settings saved.');
    }
}
