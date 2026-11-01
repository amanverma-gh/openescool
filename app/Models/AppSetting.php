<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

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

    /** Hard ceiling for a photo upload; admins can only lower it. */
    public const MAX_UPLOAD_KB = 1024;

    /**
     * Photos and GIFs allowed on one post, set by a platform admin (1–4).
     */
    public static function maxImages(): int
    {
        return max(1, min(Post::MAX_ATTACHMENTS, (int) static::get('max_images', (string) Post::MAX_ATTACHMENTS)));
    }

    /**
     * Largest photo upload in KB, set by a platform admin (never above 1 MB).
     */
    public static function maxUploadKb(): int
    {
        return max(100, min(self::MAX_UPLOAD_KB, (int) static::get('max_upload_kb', (string) self::MAX_UPLOAD_KB)));
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
        } catch (\Throwable) {
            return; // no database or table yet
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
