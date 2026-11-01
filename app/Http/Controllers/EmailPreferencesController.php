<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Unsubscribe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmailPreferencesController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/notifications', [
            'types' => User::EMAIL_TYPES,
            'preferences' => $request->user()->emailPreferences(),
            'digest' => $request->user()->digest_frequency,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*' => ['boolean'],
            'digest' => ['required', Rule::in(User::DIGEST_FREQUENCIES)],
        ]);

        $request->user()->forceFill([
            'email_preferences' => collect(User::EMAIL_TYPES)
                ->map(fn ($label, string $type) => (bool) ($data['preferences'][$type] ?? true))
                ->all(),
            'digest_frequency' => $data['digest'],
        ])->save();

        return back()->with('success', 'Email settings saved.');
    }

    /**
     * Confirmation page for the signed link in emails.
     */
    public function confirm(Request $request, User $user, string $type): Response
    {
        abort_unless(in_array($type, Unsubscribe::types(), true), 404);

        return Inertia::render('unsubscribe', [
            'label' => Unsubscribe::label($type),
            'action' => $request->fullUrl(),
            'done' => false,
        ]);
    }

    /**
     * Also the RFC 8058 one-click target that Gmail and Yahoo post to directly.
     */
    public function unsubscribe(Request $request, User $user, string $type): Response
    {
        abort_unless(in_array($type, Unsubscribe::types(), true), 404);

        Unsubscribe::apply($user, $type);

        return Inertia::render('unsubscribe', [
            'label' => Unsubscribe::label($type),
            'action' => null,
            'done' => true,
        ]);
    }
}
