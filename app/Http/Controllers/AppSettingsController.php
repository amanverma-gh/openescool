<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Community;
use App\Models\Post;
use App\Services\AppUpdater;
use App\Services\UpdateChecker;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform settings for the site owner: Brevo (email + contacts) and GIPHY.
 * Keys are checked with the provider before saving and stored encrypted.
 */
class AppSettingsController extends Controller
{
    public function edit(Request $request, UpdateChecker $checker, AppUpdater $updater): Response
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
            'uploads' => ['max_images' => AppSetting::maxImages()] + collect(AppSetting::UPLOAD_LIMITS)
                ->mapWithKeys(fn ($limits, $key) => [
                    str_replace('_kb', '_mb', $key) => round(AppSetting::uploadLimitKb($key) / 1024, 2),
                ])->all(),
            'uploadCeilings' => collect(AppSetting::UPLOAD_LIMITS)
                ->mapWithKeys(fn ($limits, $key) => [str_replace('_kb', '_mb', $key) => $limits[1] / 1024])->all(),
            // What this server's PHP accepts; uploads above it fail before Laravel sees them.
            'serverMaxMb' => round(min($this->iniBytes('upload_max_filesize'), $this->iniBytes('post_max_size')) / 1024 / 1024, 1),
            'site' => [
                'name' => config('app.name'),
                'logo' => AppSetting::logoUrl(),
                'seo_index' => Seo::siteIndexed(),
            ],
            'home' => CommunityController::homeSettings(),
            'communities' => Community::orderBy('name')->get(['id', 'name', 'privacy']),
            'helpUrl' => config('openescool.help_url'),
            'update' => $checker->status() + [
                'blocker' => $updater->blocker(),
                'run' => $updater->status(),
            ],
        ]);
    }

    /**
     * Site name and logo, the front page, and search engines.
     */
    public function updateSite(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'site_name' => ['sometimes', 'required', 'string', 'max:40'],
            'site_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['boolean'],
            'home_mode' => ['sometimes', 'in:discover,community'],
            'home_community_id' => ['nullable', 'required_if:home_mode,community', 'integer', 'exists:communities,id'],
            'home_headline' => ['sometimes', 'nullable', 'string', 'max:120'],
            'home_subtext' => ['sometimes', 'nullable', 'string', 'max:300'],
            'home_button' => ['sometimes', 'nullable', 'string', 'max:40'],
            'home_show_communities' => ['sometimes', 'boolean'],
            'home_communities_title' => ['sometimes', 'nullable', 'string', 'max:60'],
            'home_community_ids' => ['sometimes', 'array', 'max:30'],
            'home_community_ids.*' => ['integer', 'exists:communities,id'],
            'seo_index' => ['sometimes', 'boolean'],
        ], [
            'home_community_id.required_if' => 'Choose which community the home page opens.',
        ]);

        if ($request->hasFile('site_logo') || ($data['remove_logo'] ?? false)) {
            if ($old = AppSetting::get('site_logo')) {
                Storage::disk('public')->delete($old);
            }
            AppSetting::put('site_logo', $request->hasFile('site_logo') ? $request->file('site_logo')->store('site', 'public') : null);
        }

        foreach (['site_name', 'home_mode', 'home_community_id', 'home_headline', 'home_subtext', 'home_button', 'home_communities_title'] as $key) {
            if (array_key_exists($key, $data)) {
                // Empty text falls back to the default wording.
                AppSetting::put($key, $data[$key] === null ? null : trim((string) $data[$key]));
            }
        }
        foreach (['home_show_communities', 'seo_index'] as $key) {
            if (array_key_exists($key, $data)) {
                AppSetting::put($key, $data[$key] ? '1' : '0');
            }
        }
        if (array_key_exists('home_community_ids', $data)) {
            AppSetting::put('home_community_ids', $data['home_community_ids'] ? json_encode(array_values(array_map('intval', $data['home_community_ids']))) : null);
        }

        return back()->with('success', 'Settings saved.');
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
            // Sizes in MB, e.g. 0.5; each has its own ceiling.
            'max_upload_mb' => ['sometimes', 'numeric', 'min:0.1', 'max:'.AppSetting::UPLOAD_LIMITS['max_upload_kb'][1] / 1024],
            'max_resource_mb' => ['sometimes', 'numeric', 'min:0.1', 'max:'.AppSetting::UPLOAD_LIMITS['max_resource_kb'][1] / 1024],
            'max_video_mb' => ['sometimes', 'numeric', 'min:0.1', 'max:'.AppSetting::UPLOAD_LIMITS['max_video_kb'][1] / 1024],
        ], [
            'max_upload_mb.max' => 'Max photo size can be 1 MB at most. You can set a lower limit, e.g. 0.5.',
            'max_resource_mb.max' => 'Max resource file size can be 100 MB at most.',
            'max_video_mb.max' => 'Max video size can be 100 MB at most.',
            '*.min' => 'Upload sizes must be at least 0.1 MB.',
            'max_images.between' => 'Choose 1 to '.Post::MAX_ATTACHMENTS.' photos per post.',
        ]);

        if (array_key_exists('max_images', $data)) {
            AppSetting::put('max_images', (string) $data['max_images']);
        }
        foreach (array_keys(AppSetting::UPLOAD_LIMITS) as $key) {
            $field = str_replace('_kb', '_mb', $key);
            if (array_key_exists($field, $data)) {
                AppSetting::put($key, (string) (int) round($data[$field] * 1024));
            }
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

    /**
     * php.ini sizes like "2M" or "8G" in bytes.
     */
    protected function iniBytes(string $option): int
    {
        $value = trim((string) ini_get($option));
        $number = (int) $value;

        return match (strtoupper(substr($value, -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number ?: PHP_INT_MAX,
        };
    }

    protected function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()->is_admin, 403);
    }
}
