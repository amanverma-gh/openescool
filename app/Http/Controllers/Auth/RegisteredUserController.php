<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\Welcome;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register', ['formToken' => Crypt::encryptString((string) now()->timestamp)]);
    }

    /**
     * Bots fill the hidden "website" field, or post without loading the form, or submit
     * in under 3 seconds. Humans never notice either check.
     */
    protected function looksLikeBot(Request $request): bool
    {
        if (filled($request->input('website'))) {
            return true;
        }

        try {
            $startedAt = (int) Crypt::decryptString((string) $request->input('form_token'));
        } catch (DecryptException) {
            return true;
        }

        $elapsed = now()->timestamp - $startedAt;

        return $elapsed < 3 || $elapsed > 86400;
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        if ($this->looksLikeBot($request)) {
            throw ValidationException::withMessages(['email' => 'Something went wrong. Please reload the page and try again.']);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));
        $user->notify(new Welcome);

        Auth::login($user);

        return redirect()->intended(route('dashboard'));
    }
}
