<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\URL;

class Unsubscribe
{
    /**
     * Signed link that works without logging in (emails are often opened on another device).
     */
    public static function url(User $user, string $type): string
    {
        return URL::signedRoute('email.unsubscribe', ['user' => $user->id, 'type' => $type]);
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [...array_keys(User::EMAIL_TYPES), 'digest', 'all'];
    }

    public static function label(string $type): string
    {
        return match ($type) {
            'digest' => 'community digest emails',
            'all' => 'all emails except receipts and account security',
            default => strtolower(User::EMAIL_TYPES[$type]).' emails',
        };
    }

    public static function apply(User $user, string $type): void
    {
        $preferences = $user->emailPreferences();

        if ($type === 'digest' || $type === 'all') {
            $user->digest_frequency = 'off';
        }

        foreach (array_keys($preferences) as $key) {
            if ($type === 'all' || $type === $key) {
                $preferences[$key] = false;
            }
        }

        $user->forceFill(['email_preferences' => $preferences])->save();
    }
}
