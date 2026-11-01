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

    public const SECRETS = ['giphy_api_key'];

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = Cache::rememberForever("app-setting:{$key}", fn () => static::find($key)?->value);

        if ($value !== null && in_array($key, self::SECRETS, true)) {
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
}
