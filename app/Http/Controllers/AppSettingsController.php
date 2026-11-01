<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform settings for the site owner: Brevo (email + contacts) and GIPHY.
 * Keys are checked with the provider before saving and stored encrypted.
 */
class AppSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $this->ensureAdmin($request);

        return Inertia::render('admin/settings', [
            'giphy' => $this->secretState('giphy_api_key'),
            'brevo' => $this->secretState('brevo_api_key') + [
                'from_address' => AppSetting::get('mail_from_address') ?? config('mail.from.address'),
                'from_name' => AppSetting::get('mail_from_name') ?? config('mail.from.name'),
                'list_id' => AppSetting::get('brevo_members_list_id'),
                // Mail still goes to the log file until a key is saved (or MAIL_MAILER is set).
                'mailer' => config('mail.default'),
            ],
            'photos' => [
                'max_images' => AppSetting::maxImages(),
                'max_upload_mb' => round(AppSetting::maxUploadKb() / 1024, 2),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'giphy_api_key' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9]+$/'],
            'remove' => ['nullable', 'in:giphy_api_key,brevo_api_key'],
            'brevo_api_key' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_-]+$/'],
            'mail_from_address' => ['sometimes', 'required', 'email', 'max:200'],
            'mail_from_name' => ['sometimes', 'required', 'string', 'max:100'],
            'brevo_members_list_id' => ['nullable', 'integer', 'min:1'],
            'max_images' => ['sometimes', 'integer', 'between:1,'.Post::MAX_ATTACHMENTS],
            // In MB, e.g. 0.5. Never above 1 MB.
            'max_upload_mb' => ['sometimes', 'numeric', 'min:0.1', 'max:'.AppSetting::MAX_UPLOAD_KB / 1024],
        ], [
            'max_upload_mb.max' => 'Max photo size can be 1 MB at most. You can set a lower limit, e.g. 0.5.',
            'max_upload_mb.min' => 'Max photo size must be at least 0.1 MB.',
            'max_images.between' => 'Choose 1 to '.Post::MAX_ATTACHMENTS.' photos per post.',
        ]);

        if (array_key_exists('max_images', $data)) {
            AppSetting::put('max_images', (string) $data['max_images']);
        }
        if (array_key_exists('max_upload_mb', $data)) {
            AppSetting::put('max_upload_kb', (string) (int) round($data['max_upload_mb'] * 1024));
        }

        if (isset($data['remove'])) {
            AppSetting::put($data['remove'], null);

            return back()->with('success', 'Key removed.');
        }

        if (filled($data['giphy_api_key'] ?? null)) {
            $ok = Http::timeout(5)->get('https://api.giphy.com/v1/gifs/trending', ['api_key' => $data['giphy_api_key'], 'limit' => 1])->successful();
            if (! $ok) {
                return back()->withErrors(['giphy_api_key' => 'GIPHY did not accept this key. Check it and try again.']);
            }
            AppSetting::put('giphy_api_key', $data['giphy_api_key']);
        }

        if (filled($data['brevo_api_key'] ?? null)) {
            $ok = Http::timeout(5)->withHeaders(['api-key' => $data['brevo_api_key']])->acceptJson()->get('https://api.brevo.com/v3/account')->successful();
            if (! $ok) {
                return back()->withErrors(['brevo_api_key' => 'Brevo did not accept this key. Use an API key (not the SMTP key) from Brevo → SMTP & API → API keys.']);
            }
            AppSetting::put('brevo_api_key', $data['brevo_api_key']);
        }

        foreach (['mail_from_address', 'mail_from_name', 'brevo_members_list_id'] as $key) {
            if (array_key_exists($key, $data)) {
                AppSetting::put($key, $data[$key] === null ? null : (string) $data[$key]);
            }
        }

        return back()->with('success', 'Settings saved.');
    }

    /**
     * Send a test email to the signed-in admin with the current mail settings.
     */
    public function testEmail(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);
        AppSetting::applyToConfig();

        try {
            Mail::raw('Your email settings work. Members will get notifications, digests and post emails from this address.', function ($message) use ($request) {
                $message->to($request->user()->email)->subject('Test email from '.config('app.name'));
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Sending failed: '.str($e->getMessage())->limit(200));
        }

        return back()->with('success', config('mail.default') === 'log'
            ? 'Mail is in log mode, so the test email was written to storage/logs/laravel.log. Save a Brevo key to send real email.'
            : "Test email sent to {$request->user()->email}.");
    }

    /**
     * @return array{set: bool, hint: string|null}
     */
    protected function secretState(string $key): array
    {
        $value = AppSetting::get($key);

        // Only a hint of a saved key is ever shown.
        return ['set' => (bool) $value, 'hint' => $value ? '••••'.substr($value, -4) : null];
    }

    protected function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()->is_admin, 403);
    }
}
