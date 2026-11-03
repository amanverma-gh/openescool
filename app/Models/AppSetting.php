<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * Key/value settings a platform admin manages in the app. Keys listed in
 * SECRETS are encrypted with APP_KEY and never sent to the browser.
 */
class AppSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public const SECRETS = ['giphy_api_key', 'brevo_api_key'];

    /**
     * Upload size limits a platform admin sets in Admin settings, in KB:
     * key => [default, highest allowed]. Everything starts at 1 MB.
     */
    public const UPLOAD_LIMITS = [
        'max_upload_kb' => [1024, 1024],        // photos in posts and comments
        'max_resource_kb' => [1024, 100 * 1024], // lesson resource files
        'max_video_kb' => [1024, 100 * 1024],    // lesson videos
    ];

    /**
     * Photos and GIFs allowed on one post, set by a platform admin (1–4).
     */
    public static function maxImages(): int
    {
        return max(1, min(Post::MAX_ATTACHMENTS, (int) static::get('max_images', (string) Post::MAX_ATTACHMENTS)));
    }

    public static function uploadLimitKb(string $key): int
    {
        [$default, $ceiling] = self::UPLOAD_LIMITS[$key];

        return max(100, min($ceiling, (int) static::get($key, (string) $default)));
    }

    /**
     * Largest photo upload in KB (never above 1 MB).
     */
    public static function maxUploadKb(): int
    {
        return self::uploadLimitKb('max_upload_kb');
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        // '' marks "not set" so a missing value is cached too.
        $value = Cache::rememberForever("app-setting:{$key}", fn () => static::find($key)?->value ?? '');

        if (filled($value) && in_array($key, self::SECRETS, true)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (DecryptException) {
                $value = null; // APP_KEY changed; the admin has to enter it again.
            }
        }

        return filled($value) ? $value : $default;
    }

    /**
     * An on/off setting, stored as "1" or "0".
     */
    public static function flag(string $key, bool $default = false): bool
    {
        return static::get($key, $default ? '1' : '0') === '1';
    }

    public static function logoUrl(): ?string
    {
        $path = static::get('site_logo');

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public static function put(string $key, ?string $value): void
    {
        if (blank($value)) {
            static::whereKey($key)->delete();
        } else {
            static::updateOrCreate(['key' => $key], [
                'value' => in_array($key, self::SECRETS, true) ? Crypt::encryptString($value) : $value,
            ]);
        }

        Cache::forget("app-setting:{$key}");
    }

    /**
     * Point mail and Brevo at the keys saved in the app (they win over .env).
     * Safe to call before migrations have run.
     */
    public static function applyToConfig(): void
    {
        try {
            $brevoKey = static::get('brevo_api_key');
            $fromAddress = static::get('mail_from_address');
            $fromName = static::get('mail_from_name');
            $listId = static::get('brevo_members_list_id');
            $siteName = static::get('site_name');
        } catch (\Throwable) {
            return; // no database or table yet
        }

        if ($siteName) {
            config(['app.name' => $siteName]);
        }

        if ($brevoKey) {
            config(['services.brevo.key' => $brevoKey, 'mail.default' => 'brevo']);
        }
        if ($fromAddress) {
            config(['mail.from.address' => $fromAddress]);
        }
        if ($fromName) {
            config(['mail.from.name' => $fromName]);
        }
        if ($listId) {
            config(['services.brevo.members_list_id' => $listId]);
        }
    }
}
