<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\Welcome;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        abort_unless(config('services.google.client_id'), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        abort_unless(config('services.google.client_id'), 404);

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            return to_route('login')->withErrors(['email' => 'Google sign-in failed. Please try again.']);
        }

        $user = User::where('google_id', $google->getId())->first()
            ?? User::where('email', strtolower($google->getEmail()))->first();

        if ($user) {
            $user->forceFill(['google_id' => $google->getId()])->save();
            $user->email_verified_at ??= now();
            $user->avatar_path ??= $google->getAvatar();
            $user->save();
        } else {
            $user = User::create([
                'name' => $google->getName() ?: strtok($google->getEmail(), '@'),
                'email' => strtolower($google->getEmail()),
                'google_id' => $google->getId(),
                'avatar_path' => $google->getAvatar(),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->makeAdminIfFirst();

            event(new Registered($user));
            $user->notify(new Welcome);
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }
}
